<template>
  <div class="emoji-mart emoji-mart-static" :style="customStyles">
    <div class="emoji-mart-bar emoji-mart-bar-anchors" v-if="showCategories">
      <anchors
        :data="data"
        :i18n="mergedI18n"
        :color="color"
        :categories="categories"
        :active-category="activeCategory"
        @click="onAnchorClick"
      />
    </div>

    <slot
      name="searchTemplate"
      :data="data"
      :i18n="i18n"
      :auto-focus="autoFocus"
      :on-search="onSearch"
    >
      <search
        v-if="showSearch"
        ref="search"
        :data="data"
        :i18n="mergedI18n"
        :auto-focus="autoFocus"
        :on-search="onSearch"
        @search="onSearch"
      />
    </slot>

    <div class="emoji-mart-scroll" ref="scroll" @scroll="onScroll">
      <category
        v-show="searchEmojis"
        :data="data"
        :i18n="mergedI18n"
        id="search"
        name="Search"
        :emojis="searchEmojis"
        :emoji-props="emojiProps"
      />
      <category
        v-for="category in filteredCategories"
        v-show="!searchEmojis && (infiniteScroll || category == activeCategory)"
        ref="categories"
        :key="category.id"
        :data="data"
        :i18n="mergedI18n"
        :id="category.id"
        :name="category.name"
        :emojis="category.emojis"
        :emoji-props="emojiProps"
      />
    </div>

    <slot
      name="previewTemplate"
      :data="data"
      :title="title"
      :emoji="previewEmoji"
      :idle-emoji="idleEmoji"
      :show-skin-tones="showSkinTones"
      :emoji-props="emojiProps"
      :skin-props="skinProps"
      :on-skin-change="onSkinChange"
    >
      <div class="emoji-mart-bar emoji-mart-bar-preview" v-if="showPreview">
        <preview
          :data="data"
          :title="title"
          :emoji="previewEmoji"
          :idle-emoji="idleEmoji"
          :show-skin-tones="showSkinTones"
          :emoji-props="emojiProps"
          :skin-props="skinProps"
          :on-skin-change="onSkinChange"
        />
      </div>
    </slot>
  </div>
</template>

<script>
import '../vendor/raf-polyfill'
import store from '../utils/store'
import frequently from '../utils/frequently'
import { deepMerge, measureScrollbar } from '../utils'
import { PickerProps } from '../utils/shared-props'
import Anchors from './anchors'
import Category from './category'
import Preview from './preview'
import Search from './search'

/*
 * Note about `buffer` setting for DynamicScroller: this is a
 * fix for #49 - when clicking on the "Flags" category for the first
 * time, the category is not scrolled to the top of the component.
 * This is because the last category size is not calculated yet and
 * virtual scroller takes 'minItemSize' as category height.
 *
 * Virtual scroller (RecycleScroller component) uses `buffer` value
 * to  decide how many components to render intitially depending on
 * the scroll area size + buffer*2 (and all categories initially
 * have min size, 60px).
 *
 * By increasing buffer to 400px, we make the scroller to perform
 * size calculation for all categories and the following
 * scrollToItem() calls work correctly.
 */

import { DynamicScroller, DynamicScrollerItem } from 'vue-virtual-scroller'
// import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

const I18N = {
  search: 'Search',
  notfound: 'No Emoji Found',
  categories: {
    search: 'Search Results',
    recent: 'Frequently Used',
    people: 'Smileys & People',
    nature: 'Animals & Nature',
    foods: 'Food & Drink',
    activity: 'Activity',
    places: 'Travel & Places',
    objects: 'Objects',
    symbols: 'Symbols',
    flags: 'Flags',
    custom: 'Custom',
  },
}

export default {
  props: {
    ...PickerProps,
    data: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      activeSkin: this.skin || store.get('skin') || this.defaultSkin,
      activeCategory: null,
      previewEmoji: null,
      searchEmojis: null,
    }
  },
  computed: {
    customStyles() {
      return {
        width: this.calculateWidth + 'px',
        ...this.pickerStyles,
      }
    },
    emojiProps() {
      return {
        native: this.native,
        skin: this.activeSkin,
        set: this.set,
        emojiTooltip: this.emojiTooltip,
        emojiSize: this.emojiSize,
        onEnter: this.onEmojiEnter.bind(this),
        onLeave: this.onEmojiLeave.bind(this),
        onClick: this.onEmojiClick.bind(this),
      }
    },
    skinProps() {
      return {
        skin: this.activeSkin,
      }
    },
    calculateWidth() {
      return this.perLine * (this.emojiSize + 12) + 12 + 2 + measureScrollbar()
    },
    filteredCategories() {
      return this.categories.filter((category) => {
        let isIncluded =
          this.include && this.include.length
            ? this.include.indexOf(category.id) > -1
            : true
        let isExcluded =
          this.exclude && this.exclude.length
            ? this.exclude.indexOf(category.id) > -1
            : false
        let hasEmojis = category.emojis.length > 0
        if (this.emojisToShowFilter) {
          hasEmojis = category.emojis.some((emoji) => {
            return this.emojisToShowFilter(this.data.emojis[emoji] || emoji)
          })
        }
        return isIncluded && !isExcluded && hasEmojis
      })
    },
    mergedI18n() {
      return Object.freeze(deepMerge(I18N, this.i18n))
    },
    idleEmoji() {
      return this.data.emoji(this.emoji)
    },
  },
  created() {
    this.categories = []
    this.categories.push(...this.data.categories())
    this.categories = this.categories.filter((category) => {
      return category.emojis.length > 0
    })

    this.categories[0].first = true
    Object.freeze(this.categories)
    this.activeCategory = this.categories[0]
    this.skipScrollUpdate = false
  },
  methods: {
    onScroll() {
      if (this.infiniteScroll && !this.waitingForPaint) {
        this.waitingForPaint = true
        window.requestAnimationFrame(this.onScrollPaint.bind(this))
      }
    },
    onScrollPaint() {
      this.waitingForPaint = false
      let scrollTop = this.$refs.scroll.scrollTop,
        activeCategory = this.filteredCategories[0]
      for (let i = 0, l = this.filteredCategories.length; i < l; i++) {
        let category = this.filteredCategories[i],
          component = this.$refs.categories[i]
        // The `-50` offset switches active category (selected in the
        // anchors bar) a bit eariler, before it actually reaches the top.
        if (component && component.$el.offsetTop - 50 > scrollTop) {
          break
        }
        activeCategory = category
      }
      this.activeCategory = activeCategory
    },
    onAnchorClick(category) {
      let i = this.filteredCategories.indexOf(category),
        component = this.$refs.categories[i],
        scrollToComponent = () => {
          if (component) {
            let top = component.$el.offsetTop
            if (category.first) {
              top = 0
            }
            this.$refs.scroll.scrollTop = top
          }
        }
      if (this.searchEmojis) {
        this.onSearch(null)
        this.$refs.search.clear()
        this.$nextTick(scrollToComponent)
      } else if (this.infiniteScroll) {
        scrollToComponent()
      } else {
        this.activeCategory = this.filteredCategories[i]
      }
    },
    onSearch(value) {
      let emojis = this.data.search(value, this.maxSearchResults)
      this.searchEmojis = emojis
    },
    onEmojiEnter(emoji) {
      this.previewEmoji = emoji
    },
    onEmojiLeave(emoji) {
      this.previewEmoji = null
    },
    onEmojiClick(emoji) {
      this.$emit('select', emoji)
      frequently.add(emoji)
    },
    onSkinChange(skin) {
      this.activeSkin = skin
      store.update({ skin })

      this.$emit('skin-change', skin)
    },
  },
  components: {
    Anchors,
    Category,
    Preview,
    Search,
    DynamicScroller,
    DynamicScrollerItem,
  },
}
</script>
