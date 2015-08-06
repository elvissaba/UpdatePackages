<?php
/* +***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 * *********************************************************************************************************************************** */

function _3_bind_Contacts($user_id, $mail_detail, $folder, $return)
{
	$ModuleName = 'Contacts';
	$ossmailviewTab = 'vtiger_ossmailview_contacts';

	require_once("modules/OSSMailScanner/template_actions/email.php");
	$ids = bind_email($user_id, $mail_detail, $folder, $ModuleName, $ossmailviewTab);
	return Array('bind_Contacts' => $ids);
}
