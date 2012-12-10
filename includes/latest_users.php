<?php
/**
*
* @package phpBB3
* @version $Id:
* @copyright (c) 2012
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

function get_latest_users($limit = 10, $cache_secs = 300)
{
	global $auth, $cache, $config, $user, $db, $template;

    $user->add_lang('mods/latest_users_lang');

	// an array of user types we dont' bother with
	// could add board founder (USER_FOUNDER) if wanted
	$ignore_users = array(USER_IGNORE, USER_INACTIVE);

	// grab auths that allow a user to read a forum
	$forum_array = array_unique(array_keys($auth->acl_getf('!f_read', true)));

	// we have auths, change the sql query below
	$sql_and = '';
	if (sizeof($forum_array))
	{
		$sql_and = ' AND ' . $db->sql_in_set('p.forum_id', $forum_array, true);
	}

    // ==== LATEST USERS ====
	if (($latest_users = $cache->get('_latest_users')) === false)
	{
		$latest_users = array();

		// Grab the most recent registered users
		$sql = 'SELECT user_id, username, user_colour, user_regdate
			FROM ' . USERS_TABLE . '
			WHERE ' . $db->sql_in_set('user_type', $ignore_users, true) . '
				AND user_inactive_reason = 0
			ORDER BY user_regdate DESC';
		$result = $db->sql_query_limit($sql, $limit);

		while ($row = $db->sql_fetchrow($result))
		{
			$latest_users[$row['user_id']] = array(
				'user_id'				=> $row['user_id'],
				'username'				=> $row['username'],
				'user_colour'			=> $row['user_colour'],
				'user_regdate'			=> $row['user_regdate'],
			);
		}
		$db->sql_freeresult($result);

		// cache this data for $cache_secs, this improves performance
		$cache->put('_latest_users', $latest_users, $cache_secs);
	}

	foreach ($latest_users as $row)
	{
		$username_string = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);

		$template->assign_block_vars('latest_users',array(
			'REG_DATE'			=> $user->format_date($row['user_regdate'], $format = 'M d'),
			'USERNAME_FULL'		=> $username_string
		));
	}
}
?>