<?php
/**
 * Display Forums in the Navigation Menu
 */
function display_navforums()
{
	global $db, $auth, $user, $template, $phpbb_root_path, $phpEx;
	// www.phpBB-SEO.com SEO TOOLKIT BEGIN
	global $phpbb_seo;
	// www.phpBB-SEO.com SEO TOOLKIT END
	$forum_rows = $subforums = $active_forum_ary = array();
	$parent_id = 0;
	$root_data = array('forum_id' => 0);

	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'f.*',
		'FROM'		=> array(
			FORUMS_TABLE	=> 'f'
		),
		'ORDER_BY'	=> 'f.left_id',
	));

	$result = $db->sql_query($sql);

	$branch_root_id = $root_data['forum_id'];

	while ($row = $db->sql_fetchrow($result))
	{
		$forum_id = $row['forum_id'];
		// www.phpBB-SEO.com SEO TOOLKIT BEGIN
		$phpbb_seo->set_url($row['forum_name'], $forum_id, 'forum');
		// www.phpBB-SEO.com SEO TOOLKIT END

		// Category with no members
		if ($row['forum_type'] == FORUM_CAT && ($row['left_id'] + 1 == $row['right_id']))
		{
			continue;
		}

		// Skip branch
		if (isset($right_id))
		{
			if ($row['left_id'] < $right_id)
			{
				continue;
			}
			unset($right_id);
		}

		if (!$auth->acl_get('f_list', $forum_id))
		{
			// If the user does not have permissions to list this forum, skip everything until next branch
			$right_id = $row['right_id'];
			continue;
		}

		if ($row['parent_id'] == $root_data['forum_id'] || $row['parent_id'] == $branch_root_id)
		{
			// Direct child of current branch
			$parent_id = $forum_id;
			$forum_rows[$forum_id] = $row;

			if ($row['forum_type'] == FORUM_CAT && $row['parent_id'] == $root_data['forum_id'])
			{
				$branch_root_id = $forum_id;
			}
		}
		else if ($row['forum_type'] != FORUM_CAT)
		{
			$subforums[$parent_id][$forum_id]['display'] = ($row['display_on_index']) ? true : false;
			$subforums[$parent_id][$forum_id]['name'] = $row['forum_name'];
			$subforums[$parent_id][$forum_id]['children'] = array();

			if (isset($subforums[$parent_id][$row['parent_id']]) && !$row['display_on_index'])
			{
				$subforums[$parent_id][$row['parent_id']]['children'][] = $forum_id;
			}

			$forum_rows[$parent_id]['forum_topics'] += $row['forum_topics'];

			// Do not list redirects in LINK Forums as Posts.
			if ($row['forum_type'] != FORUM_LINK)
			{
				$forum_rows[$parent_id]['forum_posts'] += $row['forum_posts'];
			}

		}
	}
	$db->sql_freeresult($result);

	// Used to tell whatever we have to create a dummy category or not.
	$last_catless = true;
	foreach ($forum_rows as $row)
	{
		// Empty category
		if ($row['parent_id'] == $root_data['forum_id'] && $row['forum_type'] == FORUM_CAT)
		{
			$template->assign_block_vars('nav_forumrow', array(
				'S_IS_CAT'				=> true,
				'FORUM_ID'				=> $row['forum_id'],
				'FORUM_NAME'			=> $row['forum_name'],
				'FORUM_DESC'			=> generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
				'FORUM_FOLDER_IMG'		=> '',
				'FORUM_FOLDER_IMG_SRC'	=> '',
				'FORUM_IMAGE'			=> ($row['forum_image']) ? '<img src="' . $phpbb_root_path . $row['forum_image'] . '" alt="' . $user->lang['FORUM_CAT'] . '" />' : '',
				'FORUM_IMAGE_SRC'		=> ($row['forum_image']) ? $phpbb_root_path . $row['forum_image'] : '',
				'U_VIEWFORUM'			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']))
			);

			continue;
		}

		$forum_id = $row['forum_id'];

		$subforums_list = array();

		// Generate list of subforums if we need to
		if (isset($subforums[$forum_id]))
		{
			foreach ($subforums[$forum_id] as $subforum_id => $subforum_row)
			{
				if ($subforum_row['display'] && $subforum_row['name'])
				{
					$subforums_list[] = array(
						'link'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $subforum_id),
						'name'		=> $subforum_row['name'],
					);
				}
				else
				{
					unset($subforums[$forum_id][$subforum_id]);
				}
			}
		}

		$catless = ($row['parent_id'] == $root_data['forum_id']) ? true : false;

		if ($row['forum_type'] != FORUM_LINK)
		{
			$u_viewforum = append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']);
		}
		else
		{
			// If the forum is a link and we count redirects we need to visit it
			// If the forum is having a password or no read access we do not expose the link, but instead handle it in viewforum
			if (($row['forum_flags'] & FORUM_FLAG_LINK_TRACK) || $row['forum_password'] || !$auth->acl_get('f_read', $forum_id))
			{
				$u_viewforum = append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']);
			}
			else
			{
				$u_viewforum = $row['forum_link'];
			}
		}

		$template->assign_block_vars('nav_forumrow', array(
			'S_IS_CAT'				=> false,
			'S_NO_CAT'				=> $catless && !$last_catless,
			'S_IS_LINK'				=> ($row['forum_type'] == FORUM_LINK) ? true : false,
			'S_AUTH_READ'			=> $auth->acl_get('f_read', $row['forum_id']),
			'S_LIST_SUBFORUMS'		=> ($row['display_subforum_list']) ? true : false,
			'S_SUBFORUMS'			=> (sizeof($subforums_list)) ? true : false,
			'FORUM_ID'				=> $row['forum_id'],
			'FORUM_NAME'			=> $row['forum_name'],
			'FORUM_DESC'			=> generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
			'FORUM_IMAGE'			=> ($row['forum_image']) ? '<img src="' . $phpbb_root_path . $row['forum_image'] . '" alt="" />' : '',
			'FORUM_IMAGE_SRC'		=> ($row['forum_image']) ? $phpbb_root_path . $row['forum_image'] : '',
			'U_VIEWFORUM'			=> $u_viewforum,
		));

		// Assign subforums loop for style authors
		foreach ($subforums_list as $subforum)
		{
			$template->assign_block_vars('nav_forumrow.subforum', array(
				'U_SUBFORUM'	=> $subforum['link'],
				'SUBFORUM_NAME'	=> $subforum['name'],
			));
		}

		$last_catless = $catless;
	}

	return array($active_forum_ary, array());
}

?>
