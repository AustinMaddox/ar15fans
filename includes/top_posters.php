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

global $user;
$user->add_lang('mods/top_posters_lang');

function get_top_posters($limit = 10, $cache_secs = 300)
{
	global $cache, $db, $template;

	if (($user_posts = $cache->get('_top_posters')) === false)
	{
		// An array of user types we don`t bother with could add board founder (USER_FOUNDER) if wanted
		$ignore_users = array(USER_IGNORE, USER_INACTIVE);

		$user_posts = array();

		// Grab users with most posts
		$sql = '
			SELECT user_id, username, user_colour, user_posts
			FROM ' . USERS_TABLE . '
			WHERE ' . $db->sql_in_set('user_type', $ignore_users, true) . '
			AND user_posts <> 0
			ORDER BY user_posts DESC
		';
		$result = $db->sql_query_limit($sql, $limit);

		while ($row = $db->sql_fetchrow($result))
		{
			$user_posts[$row['user_id']] = array(
				'user_id'		=> $row['user_id'],
				'username'		=> $row['username'],
				'user_colour'	=> $row['user_colour'],
				'user_posts'    => $row['user_posts'],
			);
		}
		$db->sql_freeresult($result);

		// Cache this data for $cache_secs, this improves performance
		$cache->put('_top_posters', $user_posts, $cache_secs);
	}

	$ranking = 0;

	foreach ($user_posts as $row)
	{
		$username_string = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
		$ranking++;
		$template->assign_block_vars('top_posters',array(
				'S_SEARCH_ACTION'	=> append_sid("{$phpbb_root_path}search.$phpEx", 'author_id=' . $row['user_id'] . '&amp;sr=posts'),
				'RANKING'			=> '#' . $ranking,
				'USERNAME_FULL'		=> $username_string,
				'POSTS' 			=> $row['user_posts'],
		));
	}
}

function get_top_posters_era($limit = 10, $cache_secs = 300, $hours = 24)
{
	global $cache, $db, $user, $template;

	// An array of user types we don`t bother with could add board founder (USER_FOUNDER) if wanted
	$ignore_users = array(USER_IGNORE, USER_INACTIVE);

	$minutes = ($hours * 3600);
	$time = time() - $minutes;

	if (($user_posts = $cache->get('_top_posters_era')) === false)
	{
		// Grab users with the most posts
		$sql = '
			SELECT u.user_id, u.username, u.user_type, u.user_colour, u.user_posts, COUNT(p.post_id) as total_posts
			FROM ' . USERS_TABLE . ' u, ' . POSTS_TABLE . ' p
			WHERE p.post_time > ' . (int) $time . '
			AND u.user_id = p.poster_id
			AND u.user_id <> ' . (int) ANONYMOUS . '
			AND ' . $db->sql_in_set('user_type', $ignore_users, true) . '
			GROUP BY u.user_id
			ORDER BY total_posts DESC
		';
		$result = $db->sql_query_limit($sql, $limit);

		$user_posts = array();

		while ($row = $db->sql_fetchrow($result))
		{
			$user_posts[$row['user_id']] = array(
				'user_id'		=> $row['user_id'],
				'username'		=> $row['username'],
				'user_colour'	=> $row['user_colour'],
				'user_posts'    => $row['user_posts'],
				'total_posts'	=> $row['total_posts'],
			);
		}
		$db->sql_freeresult($result);

		// Cache this data for $cache_secs, this improves performance
		$cache->put('_top_posters_era', $user_posts, $cache_secs);
	}

	$template->assign_vars(array(
		'TOP_POSTERS_ERA_NO_ACTIVITY'	=> sprintf($user->lang['TOP_POSTERS_NO_ACTIVITY'], $hours)
	));

	foreach ($user_posts as $row)
	{
		$username_string = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);

		$template->assign_block_vars('top_posters_era', array(
			'S_SEARCH_ACTION'	=> append_sid("{$phpbb_root_path}search.$phpEx", 'author_id=' . $row['user_id'] . '&amp;sr=posts'),
			'POSTS' 			=> $row['total_posts'],
			'USERNAME_FULL'		=> $username_string
		));
	}
}

?>