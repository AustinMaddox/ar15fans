<?php
/**
 * Display Forums in Navigation
 */
function display_navforums($root_data = '')
{
	global $db, $auth, $user, $template;
	global $phpbb_root_path, $phpEx, $config;
	// www.phpBB-SEO.com SEO TOOLKIT BEGIN
	global $phpbb_seo;
	// www.phpBB-SEO.com SEO TOOLKIT END
	$forum_rows = $subforums = $forum_ids = $forum_ids_moderator = $forum_moderators = $active_forum_ary = array();
	$parent_id = $visible_forums = 0;
	$sql_from = '';

	// Mark forums read?
	$mark_read = request_var('mark', '');

	if ($mark_read == 'all')
	{
		$mark_read = '';
	}

	if (!$root_data)
	{
		if ($mark_read == 'forums')
		{
			$mark_read = 'all';
		}

		$root_data = array('forum_id' => 0);
		$sql_where = '';
	}
	else
	{
		$sql_where = 'left_id > ' . $root_data['left_id'] . ' AND left_id < ' . $root_data['right_id'];
	}

	// Handle marking everything read
	if ($mark_read == 'all')
	{
		$redirect = build_url(array('mark', 'hash'));
		meta_refresh(3, $redirect);

		if (check_link_hash(request_var('hash', ''), 'global'))
		{
			markread('all');

			trigger_error(
			$user->lang['FORUMS_MARKED'] . '<br /><br />' .
					sprintf($user->lang['RETURN_INDEX'], '<a href="' . $redirect . '">', '</a>')
			);
		}
		else
		{
			trigger_error(sprintf($user->lang['RETURN_PAGE'], '<a href="' . $redirect . '">', '</a>'));
		}
	}

	// Display list of active topics for this category?
	$show_active = (isset($root_data['forum_flags']) && ($root_data['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS)) ? true : false;

	$sql_array = array(
			'SELECT'	=> 'f.*',
			'FROM'		=> array(
					FORUMS_TABLE		=> 'f'
			),
			'LEFT_JOIN'	=> array(),
	);

	if ($config['load_db_lastread'] && $user->data['is_registered'])
	{
		$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TRACK_TABLE => 'ft'), 'ON' => 'ft.user_id = ' . $user->data['user_id'] . ' AND ft.forum_id = f.forum_id');
		$sql_array['SELECT'] .= ', ft.mark_time';
	}
	else if ($config['load_anon_lastread'] || $user->data['is_registered'])
	{
		$tracking_topics = (isset($_COOKIE[$config['cookie_name'] . '_track'])) ? ((STRIP) ? stripslashes($_COOKIE[$config['cookie_name'] . '_track']) : $_COOKIE[$config['cookie_name'] . '_track']) : '';
		$tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();

		if (!$user->data['is_registered'])
		{
			$user->data['user_lastmark'] = (isset($tracking_topics['l'])) ? (int) (base_convert($tracking_topics['l'], 36, 10) + $config['board_startdate']) : 0;
		}
	}

	if ($show_active)
	{
		$sql_array['LEFT_JOIN'][] = array(
				'FROM'	=> array(FORUMS_ACCESS_TABLE => 'fa'),
				'ON'	=> "fa.forum_id = f.forum_id AND fa.session_id = '" . $db->sql_escape($user->session_id) . "'"
		);

		$sql_array['SELECT'] .= ', fa.user_id';
	}
	// www.phpBB-SEO.com SEO TOOLKIT BEGIN -> no dupe
	if (!empty($phpbb_seo->seo_opt['no_dupe']['on'])) {
		$sql_array['SELECT'] .= ', t.topic_id, t.topic_title, t.topic_replies, t.topic_replies_real, t.topic_status, t.topic_type, t.topic_moved_id' . (!empty($phpbb_seo->seo_opt['sql_rewrite']) ? ', t.topic_url ' : ' ');
		$sql_array['LEFT_JOIN'][] = array(
				'FROM'	=> array(TOPICS_TABLE => 't'),
				'ON'	=> "f.forum_last_post_id = t.topic_last_post_id"
		);
	}
	// www.phpBB-SEO.com SEO TOOLKIT END -> no dupe
	$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> $sql_array['SELECT'],
			'FROM'		=> $sql_array['FROM'],
			'LEFT_JOIN'	=> $sql_array['LEFT_JOIN'],

			'WHERE'		=> $sql_where,

			'ORDER_BY'	=> 'f.left_id',
	));

	$result = $db->sql_query($sql);

	$forum_tracking_info = array();
	$branch_root_id = $root_data['forum_id'];

	// Check for unread global announcements (index page only)
	$ga_unread = false;
	if ($root_data['forum_id'] == 0)
	{
		$unread_ga_list = get_unread_topics($user->data['user_id'], 'AND t.forum_id = 0', '', 1);

		if (!empty($unread_ga_list))
		{
			$ga_unread = true;
		}
	}

	while ($row = $db->sql_fetchrow($result))
	{
		$forum_id = $row['forum_id'];
		// www.phpBB-SEO.com SEO TOOLKIT BEGIN
		$phpbb_seo->set_url($row['forum_name'], $forum_id, 'forum');
		// www.phpBB-SEO.com SEO TOOLKIT END
		// Mark forums read?
		if ($mark_read == 'forums')
		{
			if ($auth->acl_get('f_list', $forum_id))
			{
				$forum_ids[] = $forum_id;
			}

			continue;
		}

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
			// if the user does not have permissions to list this forum, skip everything until next branch
			$right_id = $row['right_id'];
			continue;
		}

		if ($config['load_db_lastread'] && $user->data['is_registered'])
		{
			$forum_tracking_info[$forum_id] = (!empty($row['mark_time'])) ? $row['mark_time'] : $user->data['user_lastmark'];
		}
		else if ($config['load_anon_lastread'] || $user->data['is_registered'])
		{
			if (!$user->data['is_registered'])
			{
				$user->data['user_lastmark'] = (isset($tracking_topics['l'])) ? (int) (base_convert($tracking_topics['l'], 36, 10) + $config['board_startdate']) : 0;
			}
			$forum_tracking_info[$forum_id] = (isset($tracking_topics['f'][$forum_id])) ? (int) (base_convert($tracking_topics['f'][$forum_id], 36, 10) + $config['board_startdate']) : $user->data['user_lastmark'];
		}

		// Count the difference of real to public topics, so we can display an information to moderators
		$row['forum_id_unapproved_topics'] = ($auth->acl_get('m_approve', $forum_id) && ($row['forum_topics_real'] != $row['forum_topics'])) ? $forum_id : 0;
		// www.phpBB-SEO.com SEO TOOLKIT BEGIN -> no dupe
		if (!empty($phpbb_seo->seo_opt['no_dupe']['on'])) {
			if ($row['topic_status'] == ITEM_MOVED) {
				$row['topic_id'] = $row['topic_moved_id'];
			}
			$phpbb_seo->seo_opt['topic_forum_name'][$row['topic_id']] = $row['forum_name'];
			if ($auth->acl_get('m_approve', $forum_id)) {
				$row['forum_topics'] = $row['forum_topics_real'];
				$replies = $row['topic_replies_real'];
			} else {
				$row['forum_topics'] = $row['forum_topics'];
				$replies = $row['topic_replies'];
			}
			if (($replies + 1) > $phpbb_seo->seo_opt['topic_per_page']) {
				$phpbb_seo->seo_opt['topic_last_page'][$row['topic_id']] = floor($replies / $phpbb_seo->seo_opt['topic_per_page']) * $phpbb_seo->seo_opt['topic_per_page'];
			}
		} else {
			$row['forum_topics'] = ($auth->acl_get('m_approve', $forum_id)) ? $row['forum_topics_real'] : $row['forum_topics'];
		}
		// www.phpBB-SEO.com SEO TOOLKIT END -> no dupe

		// Display active topics from this forum?
		if ($show_active && $row['forum_type'] == FORUM_POST && $auth->acl_get('f_read', $forum_id) && ($row['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS))
		{
			if (!isset($active_forum_ary['forum_topics']))
			{
				$active_forum_ary['forum_topics'] = 0;
			}

			if (!isset($active_forum_ary['forum_posts']))
			{
				$active_forum_ary['forum_posts'] = 0;
			}

			$active_forum_ary['forum_id'][]		= $forum_id;
			$active_forum_ary['enable_icons'][]	= $row['enable_icons'];
			$active_forum_ary['forum_topics']	+= $row['forum_topics'];
			$active_forum_ary['forum_posts']	+= $row['forum_posts'];

			// If this is a passworded forum we do not show active topics from it if the user is not authorised to view it...
			if ($row['forum_password'] && $row['user_id'] != $user->data['user_id'])
			{
				$active_forum_ary['exclude_forum_id'][] = $forum_id;
			}
		}

		//
		if ($row['parent_id'] == $root_data['forum_id'] || $row['parent_id'] == $branch_root_id)
		{
			if ($row['forum_type'] != FORUM_CAT)
			{
				$forum_ids_moderator[] = (int) $forum_id;
			}

			// Direct child of current branch
			$parent_id = $forum_id;
			$forum_rows[$forum_id] = $row;

			if ($row['forum_type'] == FORUM_CAT && $row['parent_id'] == $root_data['forum_id'])
			{
				$branch_root_id = $forum_id;
			}
			$forum_rows[$parent_id]['forum_id_last_post'] = $row['forum_id'];
			$forum_rows[$parent_id]['orig_forum_last_post_time'] = $row['forum_last_post_time'];
		}
		else if ($row['forum_type'] != FORUM_CAT)
		{
			$subforums[$parent_id][$forum_id]['display'] = ($row['display_on_index']) ? true : false;
			$subforums[$parent_id][$forum_id]['name'] = $row['forum_name'];
			$subforums[$parent_id][$forum_id]['orig_forum_last_post_time'] = $row['forum_last_post_time'];
			$subforums[$parent_id][$forum_id]['children'] = array();

			if (isset($subforums[$parent_id][$row['parent_id']]) && !$row['display_on_index'])
			{
				$subforums[$parent_id][$row['parent_id']]['children'][] = $forum_id;
			}

			if (!$forum_rows[$parent_id]['forum_id_unapproved_topics'] && $row['forum_id_unapproved_topics'])
			{
				$forum_rows[$parent_id]['forum_id_unapproved_topics'] = $forum_id;
			}

			$forum_rows[$parent_id]['forum_topics'] += $row['forum_topics'];

			// Do not list redirects in LINK Forums as Posts.
			if ($row['forum_type'] != FORUM_LINK)
			{
				$forum_rows[$parent_id]['forum_posts'] += $row['forum_posts'];
			}

			if ($row['forum_last_post_time'] > $forum_rows[$parent_id]['forum_last_post_time'])
			{
				$forum_rows[$parent_id]['forum_last_post_id'] = $row['forum_last_post_id'];
				$forum_rows[$parent_id]['forum_last_post_subject'] = $row['forum_last_post_subject'];
				$forum_rows[$parent_id]['forum_last_post_time'] = $row['forum_last_post_time'];
				$forum_rows[$parent_id]['forum_last_poster_id'] = $row['forum_last_poster_id'];
				$forum_rows[$parent_id]['forum_last_poster_name'] = $row['forum_last_poster_name'];
				$forum_rows[$parent_id]['forum_last_poster_colour'] = $row['forum_last_poster_colour'];
				$forum_rows[$parent_id]['forum_id_last_post'] = $forum_id;
				// www.phpBB-SEO.com SEO TOOLKIT BEGIN -> no dupe
				if (!empty($phpbb_seo->seo_opt['no_dupe']['on'])) {
					$forum_rows[$parent_id]['topic_id'] = $row['topic_id'];
					$forum_rows[$parent_id]['topic_title'] = $row['topic_title'];
					$forum_rows[$parent_id]['topic_type'] = $row['topic_type'];
					$forum_rows[$parent_id]['forum_password'] = $row['forum_password'];
					$forum_rows[$parent_id]['topic_url'] = isset($row['topic_url']) ? $row['topic_url'] : '';
				}
				// www.phpBB-SEO.com SEO TOOLKIT END -> no dupe
			}
		}
	}
	$db->sql_freeresult($result);

	// Handle marking posts
	if ($mark_read == 'forums')
	{
		$redirect = build_url(array('mark', 'hash'));
		$token = request_var('hash', '');
		if (check_link_hash($token, 'global'))
		{
			// Add 0 to forums array to mark global announcements correctly
			$forum_ids[] = 0;
			markread('topics', $forum_ids);
			$message = sprintf($user->lang['RETURN_FORUM'], '<a href="' . $redirect . '">', '</a>');
			meta_refresh(3, $redirect);
			trigger_error($user->lang['FORUMS_MARKED'] . '<br /><br />' . $message);
		}
		else
		{
			$message = sprintf($user->lang['RETURN_PAGE'], '<a href="' . $redirect . '">', '</a>');
			meta_refresh(3, $redirect);
			trigger_error($message);
		}

	}

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

		$visible_forums++;
		$forum_id = $row['forum_id'];

		$forum_unread = (isset($forum_tracking_info[$forum_id]) && $row['orig_forum_last_post_time'] > $forum_tracking_info[$forum_id]) ? true : false;

		// Mark the first visible forum on index as unread if there's any unread global announcement
		if ($ga_unread && !empty($forum_ids_moderator) && $forum_id == $forum_ids_moderator[0])
		{
			$forum_unread = true;
		}

		$folder_image = $folder_alt = $l_subforums = '';
		$subforums_list = array();

		// Generate list of subforums if we need to
		if (isset($subforums[$forum_id]))
		{
			foreach ($subforums[$forum_id] as $subforum_id => $subforum_row)
			{
				$subforum_unread = (isset($forum_tracking_info[$subforum_id]) && $subforum_row['orig_forum_last_post_time'] > $forum_tracking_info[$subforum_id]) ? true : false;

				if (!$subforum_unread && !empty($subforum_row['children']))
				{
					foreach ($subforum_row['children'] as $child_id)
					{
						if (isset($forum_tracking_info[$child_id]) && $subforums[$forum_id][$child_id]['orig_forum_last_post_time'] > $forum_tracking_info[$child_id])
						{
							// Once we found an unread child forum, we can drop out of this loop
							$subforum_unread = true;
							break;
						}
					}
				}

				if ($subforum_row['display'] && $subforum_row['name'])
				{
					$subforums_list[] = array(
							'link'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $subforum_id),
							'name'		=> $subforum_row['name'],
							'unread'	=> $subforum_unread,
					);
				}
				else
				{
					unset($subforums[$forum_id][$subforum_id]);
				}

				// If one subforum is unread the forum gets unread too...
				if ($subforum_unread)
				{
					$forum_unread = true;
				}
			}

			$l_subforums = (sizeof($subforums[$forum_id]) == 1) ? $user->lang['SUBFORUM'] . ': ' : $user->lang['SUBFORUMS'] . ': ';
			$folder_image = ($forum_unread) ? 'forum_unread_subforum' : 'forum_read_subforum';
		}
		else
		{
			switch ($row['forum_type'])
			{
				case FORUM_POST:
					$folder_image = ($forum_unread) ? 'forum_unread' : 'forum_read';
					break;

				case FORUM_LINK:
					$folder_image = 'forum_link';
					break;
			}
		}

		// Which folder should we display?
		if ($row['forum_status'] == ITEM_LOCKED)
		{
			$folder_image = ($forum_unread) ? 'forum_unread_locked' : 'forum_read_locked';
			$folder_alt = 'FORUM_LOCKED';
		}
		else
		{
			$folder_alt = ($forum_unread) ? 'UNREAD_POSTS' : 'NO_UNREAD_POSTS';
		}

		// Create last post link information, if appropriate
		if ($row['forum_last_post_id'])
		{
			$last_post_subject = $row['forum_last_post_subject'];
			$last_post_time = $user->format_date($row['forum_last_post_time']);
			// www.phpBB-SEO.com SEO TOOLKIT BEGIN -> no dupe
			if (!empty($phpbb_seo->seo_opt['no_dupe']['on']) && !$row['forum_password'] && $auth->acl_get('f_read', $row['forum_id_last_post'])) {
				$phpbb_seo->prepare_iurl($row, 'topic', $row['topic_type'] == POST_GLOBAL ? $phpbb_seo->seo_static['global_announce'] : $phpbb_seo->seo_url['forum'][$row['forum_id_last_post']]);
				$last_post_url =  append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id_last_post'] . '&amp;t=' . $row['topic_id'] . '&amp;start=' . @intval($phpbb_seo->seo_opt['topic_last_page'][$row['topic_id']]) ) . '#p' . $row['forum_last_post_id'];
				$topic_title = censor_text($row['topic_title']);
				// Limit in chars for the last post link text.
				$char_limit = 25;
				// Limit topic text link to $char_limit, without breacking words
				$topic_text_lilnk = $char_limit > 0 && ( ( $length = utf8_strlen($topic_title) ) > $char_limit ) ? ( utf8_strlen($fragment = utf8_substr($topic_title, 0, $char_limit + 1 - 4)) < $length + 1 ? preg_replace('`\s*\S*$`', '', $fragment) . ' ...' : $topic_title ) : $topic_title;
				$last_post_link = '<a href="' . append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id_last_post'] . '&amp;t=' . $row['topic_id']) . '" title="' . $topic_title . ' : ' . $phpbb_seo->seo_opt['topic_forum_name'][$row['topic_id']] . '">' . $topic_text_lilnk . '</a>';
			} else {
				$last_post_url =  append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id_last_post'] . '&amp;p=' . $row['forum_last_post_id']) . '#p' . $row['forum_last_post_id'];
				$last_post_link = '';
			}
		}
		else
		{
			$last_post_subject = $last_post_time = $last_post_url = $last_post_link = '';
			// www.phpBB-SEO.com SEO TOOLKIT END -> no dupe
		}

		$l_post_click_count = ($row['forum_type'] == FORUM_LINK) ? 'CLICKS' : 'POSTS';
		$post_click_count = ($row['forum_type'] != FORUM_LINK || $row['forum_flags'] & FORUM_FLAG_LINK_TRACK) ? $row['forum_posts'] : '';

		$s_subforums_list = array();
		foreach ($subforums_list as $subforum)
		{
			$s_subforums_list[] = '<li><a href="' . $subforum['link'] . '" class="" title="">' . $subforum['name'] . '</a></li>';
		}
		$s_subforums_list = (string) implode(', ', $s_subforums_list);
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
				'S_IS_CAT'			=> false,
				'S_NO_CAT'			=> $catless && !$last_catless,
				'S_IS_LINK'			=> ($row['forum_type'] == FORUM_LINK) ? true : false,
				'S_UNREAD_FORUM'	=> $forum_unread,
				'S_AUTH_READ'		=> $auth->acl_get('f_read', $row['forum_id']),
				'S_LOCKED_FORUM'	=> ($row['forum_status'] == ITEM_LOCKED) ? true : false,
				'S_LIST_SUBFORUMS'	=> ($row['display_subforum_list']) ? true : false,
				'S_SUBFORUMS'		=> (sizeof($subforums_list)) ? true : false,
				'S_FEED_ENABLED'	=> ($config['feed_forum'] && !phpbb_optionget(FORUM_OPTION_FEED_EXCLUDE, $row['forum_options']) && $row['forum_type'] == FORUM_POST) ? true : false,

				'FORUM_ID'				=> $row['forum_id'],
				'FORUM_NAME'			=> $row['forum_name'],
				'FORUM_DESC'			=> generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
				'TOPICS'				=> $row['forum_topics'],
				$l_post_click_count		=> $post_click_count,
				'FORUM_FOLDER_IMG'		=> $user->img($folder_image, $folder_alt),
				'FORUM_FOLDER_IMG_SRC'	=> $user->img($folder_image, $folder_alt, false, '', 'src'),
				'FORUM_FOLDER_IMG_ALT'	=> isset($user->lang[$folder_alt]) ? $user->lang[$folder_alt] : '',
				'FORUM_IMAGE'			=> ($row['forum_image']) ? '<img src="' . $phpbb_root_path . $row['forum_image'] . '" alt="' . $user->lang[$folder_alt] . '" />' : '',
				'FORUM_IMAGE_SRC'		=> ($row['forum_image']) ? $phpbb_root_path . $row['forum_image'] : '',
				'LAST_POST_SUBJECT'		=> censor_text($last_post_subject),
				'LAST_POST_TIME'		=> $last_post_time,
				'LAST_POSTER'			=> get_username_string('username', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'LAST_POSTER_COLOUR'	=> get_username_string('colour', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'LAST_POSTER_FULL'		=> get_username_string('full', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'MODERATORS'			=> $moderators_list,
				'SUBFORUMS'				=> $s_subforums_list,

				'L_SUBFORUM_STR'		=> $l_subforums,
				'L_MODERATOR_STR'		=> $l_moderator,

				'U_UNAPPROVED_TOPICS'	=> ($row['forum_id_unapproved_topics']) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=unapproved_topics&amp;f=' . $row['forum_id_unapproved_topics']) : '',
				'U_VIEWFORUM'		=> $u_viewforum,
				'U_LAST_POSTER'		=> get_username_string('profile', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				// www.phpBB-SEO.com SEO TOOLKIT BEGIN -> no dupe
		'LAST_POST_LINK'	=> $last_post_link,
		// www.phpBB-SEO.com SEO TOOLKIT END -> no dupe
		'U_LAST_POST'		=> $last_post_url)
		);

		// Assign subforums loop for style authors
		foreach ($subforums_list as $subforum)
		{
			$template->assign_block_vars('nav_forumrow.subforum', array(
					'U_SUBFORUM'	=> $subforum['link'],
					'SUBFORUM_NAME'	=> $subforum['name'],
					'S_UNREAD'		=> $subforum['unread'])
			);
		}

		$last_catless = $catless;
	}

	$template->assign_vars(array(
			'U_MARK_FORUMS'		=> ($user->data['is_registered'] || $config['load_anon_lastread']) ? append_sid("{$phpbb_root_path}viewforum.$phpEx", 'hash=' . generate_link_hash('global') . '&amp;f=' . $root_data['forum_id'] . '&amp;mark=forums') : '',
			'S_HAS_SUBFORUM'	=> ($visible_forums) ? true : false,
			'L_SUBFORUM'		=> ($visible_forums == 1) ? $user->lang['SUBFORUM'] : $user->lang['SUBFORUMS'],
			'LAST_POST_IMG'		=> $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
			'UNAPPROVED_IMG'	=> $user->img('icon_topic_unapproved', 'TOPICS_UNAPPROVED'),
	));

	return array($active_forum_ary, array());
}

?>
