<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Configuration of users');
$page['file'] = 'users.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$themes = array_keys(Z::getThemes());
$themes[] = THEME_DEFAULT;

//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	// users
	'userid' =>				array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
	'group_userid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'filter_usrgrpid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'alias' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Alias')),
	'name' =>				array(T_ZBX_STR, O_OPT, null,	null,		null, _x('Name', 'user first name')),
	'surname' =>			array(T_ZBX_STR, O_OPT, null,	null,		null, _('Surname')),
	'password1' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({form})&&{form}!="update"&&isset({change_password})'),
	'password2' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({form})&&{form}!="update"&&isset({change_password})'),
	'user_type' =>			array(T_ZBX_INT, O_OPT, null,	IN('1,2,3'),'isset({save})'),
	'user_groups' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'user_groups_to_del' =>	array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'user_medias' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null),
	'user_medias_to_del' =>	array(T_ZBX_STR, O_OPT, null,	DB_ID,		null),
	'new_groups' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'new_media' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'enable_media' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'disable_media' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'lang' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'theme' =>				array(T_ZBX_STR, O_OPT, null,	IN('"'.implode('","', $themes).'"'), 'isset({save})'),
	'autologin' =>			array(T_ZBX_INT, O_OPT, null,	IN('1'),	null),
	'autologout' => 		array(T_ZBX_INT, O_OPT, null,	BETWEEN(90, 10000), null, _('Auto-logout (min 90 seconds)')),
	'autologout_visible' =>	array(T_ZBX_STR, O_OPT, P_SYS, null, null, 'isset({save})'),
	'url' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'refresh' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, SEC_PER_HOUR), 'isset({save})', _('Refresh (in seconds)')),
	'rows_per_page' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 999999),'isset({save})', _('Rows per page')),
	// actions
	'action' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	IN('"user.massdelete","user.massunblock"'),	null),
	'register' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	IN('"add permission","delete permission"'), null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete_selected' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'del_user_group' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'del_user_media' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'del_group_user' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'change_password' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	// form
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,			null,	null),
	// sort and sortorder
	'sort' =>				array(T_ZBX_STR, O_OPT, P_SYS, IN('"alias","name","surname","type"'),		null),
	'sortorder' =>			array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['userid'])) {
	$users = API::User()->get(array(
		'userids' => getRequest('userid'),
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));
	if (!$users) {
		access_deny();
	}
}
if (getRequest('filter_usrgrpid') && !API::UserGroup()->isWritable(array($_REQUEST['filter_usrgrpid']))) {
	access_deny();
}

if (hasRequest('action')) {
	if (!hasRequest('group_userid') || !is_array(getRequest('group_userid'))) {
		access_deny();
	}
	else {
		$usersChk = API::User()->get(array(
			'output' => array('userid'),
			'userids' => getRequest('group_userid'),
			'countOutput' => true,
			'editable' => true
		));
		if ($usersChk != count(getRequest('group_userid'))) {
			access_deny();
		}
	}
}

/*
 * Actions
 */

if (isset($_REQUEST['new_groups'])) {
	$_REQUEST['new_groups'] = getRequest('new_groups', array());
	$_REQUEST['user_groups'] = getRequest('user_groups', array());
	$_REQUEST['user_groups'] += $_REQUEST['new_groups'];

	unset($_REQUEST['new_groups']);
}
elseif (isset($_REQUEST['new_media'])) {
	$_REQUEST['user_medias'] = getRequest('user_medias', array());

	array_push($_REQUEST['user_medias'], $_REQUEST['new_media']);
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['enable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['enable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['enable_media']]['active'] = 0;
	}
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['disable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['disable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['disable_media']]['active'] = 1;
	}
}
elseif (isset($_REQUEST['save'])) {
	$config = select_config();

	$isValid = true;

	$usrgrps = getRequest('user_groups', array());

	// authentication type
	if ($usrgrps) {
		$authType = getGroupAuthenticationType($usrgrps, GROUP_GUI_ACCESS_INTERNAL);
	}
	else {
		$authType = hasRequest('userid')
			? getUserAuthenticationType(getRequest('userid'), GROUP_GUI_ACCESS_INTERNAL)
			: $config['authentication_type'];
	}

	// password validation
	if ($authType != ZBX_AUTH_INTERNAL) {
		if (hasRequest('password1')) {
			show_error_message(_s('Password is unavailable for users with %1$s.', authentication2str($authType)));

			$isValid = false;
		}
		else {
			if (hasRequest('userid')) {
				$_REQUEST['password1'] = null;
				$_REQUEST['password2'] = null;
			}
			else {
				$_REQUEST['password1'] = 'zabbix';
				$_REQUEST['password2'] = 'zabbix';
			}
		}
	}
	else {
		$_REQUEST['password1'] = getRequest('password1');
		$_REQUEST['password2'] = getRequest('password2');
	}

	if ($_REQUEST['password1'] != $_REQUEST['password2']) {
		if (isset($_REQUEST['userid'])) {
			show_error_message(_('Cannot update user. Both passwords must be equal.'));
		}
		else {
			show_error_message(_('Cannot add user. Both passwords must be equal.'));
		}

		$isValid = false;
	}
	elseif (isset($_REQUEST['password1']) && $_REQUEST['alias'] == ZBX_GUEST_USER && !zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('For guest, password must be empty'));

		$isValid = false;
	}
	elseif (isset($_REQUEST['password1']) && $_REQUEST['alias'] != ZBX_GUEST_USER && zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('Password should not be empty'));

		$isValid = false;
	}

	if ($isValid) {
		$user = array();
		$user['alias'] = getRequest('alias');
		$user['name'] = getRequest('name');
		$user['surname'] = getRequest('surname');
		$user['passwd'] = getRequest('password1');
		$user['url'] = getRequest('url');
		$user['autologin'] = getRequest('autologin', 0);
		$user['autologout'] = hasRequest('autologout_visible') ? getRequest('autologout') : 0;
		$user['theme'] = getRequest('theme');
		$user['refresh'] = getRequest('refresh');
		$user['rows_per_page'] = getRequest('rows_per_page');
		$user['type'] = getRequest('user_type');
		$user['user_medias'] = getRequest('user_medias', array());
		$user['usrgrps'] = zbx_toObject($usrgrps, 'usrgrpid');

		if (hasRequest('lang')) {
			$user['lang'] = getRequest('lang');
		}

		DBstart();

		if (isset($_REQUEST['userid'])) {
			$user['userid'] = $_REQUEST['userid'];
			$result = API::User()->update(array($user));

			if ($result) {
				$result = API::User()->updateMedia(array(
					'users' => $user,
					'medias' => $user['user_medias']
				));
			}

			$messageSuccess = _('User updated');
			$messageFailed = _('Cannot update user');
			$auditAction = AUDIT_ACTION_UPDATE;
		}
		else {
			$result = API::User()->create($user);

			$messageSuccess = _('User added');
			$messageFailed = _('Cannot add user');
			$auditAction = AUDIT_ACTION_ADD;
		}

		if ($result) {
			add_audit($auditAction, AUDIT_RESOURCE_USER,
				'User alias ['.$_REQUEST['alias'].'] name ['.$_REQUEST['name'].'] surname ['.$_REQUEST['surname'].']'
			);
			unset($_REQUEST['form']);
		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
}
elseif (isset($_REQUEST['del_user_media'])) {
	foreach (getRequest('user_medias_to_del', array()) as $mediaId) {
		if (isset($_REQUEST['user_medias'][$mediaId])) {
			unset($_REQUEST['user_medias'][$mediaId]);
		}
	}
}
elseif (isset($_REQUEST['del_user_group'])) {
	foreach (getRequest('user_groups_to_del', array()) as $groupId) {
		if (isset($_REQUEST['user_groups'][$groupId])) {
			unset($_REQUEST['user_groups'][$groupId]);
		}
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['userid'])) {
	$user = reset($users);

	DBstart();

	$result = API::User()->delete(array($user['userid']));

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		unset($_REQUEST['userid'], $_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('User deleted'), _('Cannot delete user'));
}
elseif (hasRequest('action') && getRequest('action') == 'user.massunblock' && hasRequest('group_userid')) {
	$groupUserId = getRequest('group_userid');

	DBstart();

	$result = unblock_user_login($groupUserId);

	if ($result) {
		$users = API::User()->get(array(
			'userids' => $groupUserId,
			'output' => API_OUTPUT_EXTEND
		));

		foreach ($users as $user) {
			info('User '.$user['alias'].' unblocked');
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER, 'Unblocked user alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']');
		}
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Users unblocked'), _('Cannot unblock users'));
}
elseif (hasRequest('action') && getRequest('action') == 'user.massdelete' && hasRequest('group_userid')) {
	$result = false;

	$groupUserId = getRequest('group_userid');

	$dbUsers = API::User()->get(array(
		'userids' => $groupUserId,
		'output' => API_OUTPUT_EXTEND
	));
	$dbUsers = zbx_toHash($dbUsers, 'userid');

	DBstart();

	foreach ($groupUserId as $userId) {
		if (!isset($dbUsers[$userId])) {
			continue;
		}

		$result |= (bool) API::User()->delete(array($userId));

		if ($result) {
			$userData = $dbUsers[$userId];

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User alias ['.$userData['alias'].'] name ['.$userData['name'].'] surname ['.$userData['surname'].']');
		}
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('User deleted'), _('Cannot delete user'));
}

/*
 * Display
 */
$_REQUEST['filter_usrgrpid'] = getRequest('filter_usrgrpid', CProfile::get('web.users.filter.usrgrpid', 0));
CProfile::update('web.users.filter.usrgrpid', $_REQUEST['filter_usrgrpid'], PROFILE_TYPE_ID);

if (!empty($_REQUEST['form'])) {
	$userId = getRequest('userid');

	$data = getUserFormData($userId);

	$data['userid'] = $userId;
	$data['form'] = getRequest('form');
	$data['form_refresh'] = getRequest('form_refresh', 0);
	$data['autologout'] = getRequest('autologout');

	// render view
	$usersView = new CView('administration.users.edit', $data);
	$usersView->render();
	$usersView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'alias'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = array(
		'config' => $config,
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);

	// get user groups
	$data['userGroups'] = API::UserGroup()->get(array(
		'output' => API_OUTPUT_EXTEND
	));
	order_result($data['userGroups'], 'name');

	// get users
	$data['users'] = API::User()->get(array(
		'usrgrpids' => ($_REQUEST['filter_usrgrpid'] > 0) ? $_REQUEST['filter_usrgrpid'] : null,
		'output' => API_OUTPUT_EXTEND,
		'selectUsrgrps' => API_OUTPUT_EXTEND,
		'getAccess' => 1,
		'limit' => $config['search_limit'] + 1
	));

	// sorting & paging
	order_result($data['users'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['users']);

	// set default lastaccess time to 0
	foreach ($data['users'] as $user) {
		$data['usersSessions'][$user['userid']] = array('lastaccess' => 0);
	}

	$dbSessions = DBselect(
		'SELECT s.userid,MAX(s.lastaccess) AS lastaccess,s.status'.
		' FROM sessions s'.
		' WHERE '.dbConditionInt('s.userid', zbx_objectValues($data['users'], 'userid')).
		' GROUP BY s.userid,s.status'
	);
	while ($session = DBfetch($dbSessions)) {
		if ($data['usersSessions'][$session['userid']]['lastaccess'] < $session['lastaccess']) {
			$data['usersSessions'][$session['userid']] = $session;
		}
	}

	// render view
	$usersView = new CView('administration.users.list', $data);
	$usersView->render();
	$usersView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
