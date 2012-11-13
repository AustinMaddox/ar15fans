<?php
/**
*
* @package 
* @version $Id: functions_attached_images.php,v0.0.1 2012/1/5 17:00:00 austin881 Exp $
* @copyright (c) 2012 austin881
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

$user->add_lang('mods/attached_images_lang');

function attached_recent_images($forum_ids, $max_limit_arg, $orientation_arg, $num_chars, $max_width, $resize_after)
{
	global $config, $db, $auth, $user, $template, $phpbb_root_path, $phpEx;
	
	$ext_array = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
	$forum_ids_to_show = explode(',', $forum_ids);
	$max_limit = (!empty($max_limit_arg)) ? $max_limit_arg : 1;
	
	// Properly resize the avatar of the poster
	$avatar_width = $avatar_height = '';
	$avatar_max_dimensions = 50; // Here you can change the max-width you would like the avatars

	// Don't display attachments if the forum and attachment are not authorized
	$auth_read_forum = $auth->acl_getf('f_read', 'f_download', true);
	$forums_auth_ary = array();
	foreach($auth_read_forum as $key => $authed_attachments)
	{
		if($authed_attachments['f_read'] != 0)
		{
			$forums_auth_ary[] = $key;
        }
	}
    $authed_attachments = array_intersect(array_keys($auth->acl_getf('f_read', true)), array_keys($auth->acl_getf('f_download', true)));
    unset($auth_read_forum);
    
	// Grab attachments that meet criteria and proper authentication
	if(sizeof($authed_attachments))
	{
		$sql = 'SELECT a.post_msg_id, a.attach_id, a.physical_filename, a.poster_id, a.filetime, a.thumbnail, u.user_id, u.username, u.user_colour, u.user_type, u.user_avatar, u.user_avatar_type, u.user_avatar_width, u.user_avatar_height, t.topic_id, t.topic_title, t.forum_id, t.topic_last_post_id, t.topic_last_post_time, t.topic_replies_real, t.topic_views, f.forum_id, f.forum_name
			FROM ' . ATTACHMENTS_TABLE . ' a
				INNER JOIN ' . TOPICS_TABLE . ' t ON (a.topic_id = t.topic_id)
					INNER JOIN ' . USERS_TABLE . ' u ON (a.poster_id = u.user_id)
						INNER JOIN ' . FORUMS_TABLE . ' f ON (t.forum_id = f.forum_id)
							WHERE a.topic_id = t.topic_id
								AND ' . $db->sql_in_set('extension', $ext_array) . '
								AND ' . $db->sql_in_set('t.forum_id', $authed_attachments) . '
								AND ' . $db->sql_in_set('t.forum_id', $forum_ids_to_show) . '
								AND t.forum_id <> 0
							GROUP BY post_msg_id
							ORDER BY filetime DESC, post_msg_id ASC';
	
		$result = $db->sql_query_limit($sql, $max_limit);
	
		while ($row = $db->sql_fetchrow($result))
		{	
			// BEGAN - Properly resize the Avatar of poster
			if ( $row['user_avatar_width'] >= $row['user_avatar_height'] )
			{
				$avatar_width = ( $row['user_avatar_width'] > $avatar_max_dimensions ) ? $avatar_max_dimensions : $row['user_avatar_width'] ;
				$avatar_height = ( $avatar_width == $avatar_max_dimensions ) ? round($avatar_max_dimensions / $row['user_avatar_width'] * $row['user_avatar_height']) : $row['user_avatar_height'] ;
			}
			else 
			{
				$avatar_height = ( $row['user_avatar_height'] > $avatar_max_dimensions ) ? $avatar_max_dimensions : $row['user_avatar_height'] ;
				$avatar_width = ( $avatar_height == $avatar_max_dimensions ) ? round($avatar_max_dimensions / $row['user_avatar_height'] * $row['user_avatar_width']) : $row['user_avatar_width'] ;
			}
			// ENDED - Properly resize the Avatar of poster
			
			// Obtain the mess of data
			$forum_id = $row['forum_id'];
			$forum_name = $row['forum_name'];
			
			$topic_id = $row['topic_id'];
			$topic_title = $row['topic_title'];
			
			$attach_id = $row['attach_id'];
			$attachment_date = $user->format_date($row['filetime'], "|M d 'y|");
			$attachment_time = $user->format_date($row['filetime'], "g:ia");
			$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id);

			$poster_name = get_username_string('username', $row['user_id'], $row['username'], $row['user_colour']);
			$poster_name_full = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
			$poster_avatar = get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $avatar_width, $avatar_height, $user->lang['ATTACHED_BY'] . ' ' . $poster_name);
			
			$thumbnail = $row['thumbnail'];

			// Trim the topic titles
			if ($num_chars != 0 and utf8_strlen($topic_title) > $num_chars)
			{
				$topic_title = utf8_substr($topic_title, 0, $num_chars) . '...';
			}

			if($row['thumbnail'] == 1)
			{
				$row['physical_filename'] = 'thumb_' . $row['physical_filename'];
				$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id . '&amp;t=1');
			}
			else
			{
				$row['physical_filename'] = $row['physical_filename'];
				$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id);
			}

			// Resize image
			if ($ext_array = array('jpeg', 'jpg', 'gif', 'png', 'bmp') || ($row['physical_filename'] = $row['physical_filename']))
			{
				$which_resize = @getimagesize($config['upload_path'] . '/' . $row['physical_filename']);
				if ($which_resize[0] > $resize_after)
				{
					$ratio 	= $max_width/$which_resize[0];
					$h_vign = round($which_resize[1]*$ratio);
					$l_vign = $max_width;
					$window_width = $l_vign+10;
					$window_height = $h_vign+10;
				}
				else 
				{
					$l_vign = $which_resize[0];
					$h_vign = $which_resize[1];
				}
			}

			// Assign index specific vars
			$template->assign_block_vars('attached_recent_images', array(
				'FORUM_NAME'			=> $forum_name,
				'TOPIC_TITLE'			=> $topic_title,
				
				'ATTACHED_IMG_WIDTH'	=> $l_vign,
				'ATTACHED_IMG_HEIGHT'	=> $h_vign,
				
				'POSTER_AVATAR'			=> $poster_avatar,
				'POSTER_NAME'			=> $poster_name,
				'POSTER_NAME_FULL'		=> $poster_name_full,
				
				'ATTACHMENT_VIEWS'		=> $row['topic_views'],
				'ATTACHMENT_REPLIES'	=> $row['topic_replies_real'],
				'ATTACHMENT_DATE'		=> $attachment_date,
				'ATTACHMENT_TIME'		=> $attachment_time,

				'U_ATTACHED_IMG'			=> $attachment_url,
				'U_FORUM'          			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),				
				'U_ATTACHMENT_POST'      	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['post_msg_id'] .'#p'.$row['post_msg_id']),
				'U_TOPIC_LAST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['topic_last_post_id'] .'#p'.$row['topic_last_post_id']),
				'U_POSTER' 					=> append_sid($phpbb_root_path . 'memberlist.' . $phpEx, array('mode' => 'viewprofile', 'u' => $row['user_id'])),
				'U_VIEW_TOPIC'				=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $forum_id . '&amp;t=' . $topic_id),
			));

			// If no attachments to display, we already parsed them
			$attachments[$row['post_msg_id']][] = $row;
			if (!empty($attachments[$row['post_msg_id']]))
			{
				foreach ($attachments[$row['post_msg_id']] as $attachment)
				{
					$template->assign_vars(array(
						'RECENT_ATTACHMENTS_EXIST'	=> (!empty($attachments[$row['post_msg_id']])) ? true : false,
					));
				}
			}
		}

		// Assign specific vars
		$template->assign_vars(array(
			'COLSPAN'		=> $max_limit,
			'VERTICAL'		=> ($orientation_arg == 'vertical') ? true : false,
		));

	$db->sql_freeresult($result);
	}

}

function attached_random_images($forum_ids, $max_limit_arg, $orientation_arg, $num_chars, $max_width, $resize_after)
{
	global $config, $db, $auth, $user, $template, $phpbb_root_path, $phpEx;
	
	$ext_array = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
	$forum_ids_to_show = explode(',', $forum_ids);
	$max_limit = (!empty($max_limit_arg)) ? $max_limit_arg : 1;
	
	// Properly resize the avatar of the poster
	$avatar_width = $avatar_height = '';
	$avatar_max_dimensions = 50; // Here you can change the max-width you would like the avatars

	// Don't display attachments if the forum and attachment are not authorized
	$auth_read_forum = $auth->acl_getf('f_read', 'f_download', true);
	$forums_auth_ary = array();
	foreach($auth_read_forum as $key => $authed_attachments)
	{
		if($authed_attachments['f_read'] != 0)
		{
			$forums_auth_ary[] = $key;
        }
	}
    $authed_attachments = array_intersect(array_keys($auth->acl_getf('f_read', true)), array_keys($auth->acl_getf('f_download', true)));
    unset($auth_read_forum);
    
	// Grab attachments that meet criteria and proper authentication
	if(sizeof($authed_attachments))
	{
		$sql = 'SELECT a.post_msg_id, a.attach_id, a.physical_filename, a.poster_id, a.filetime, a.thumbnail, u.user_id, u.username, u.user_colour, u.user_type, u.user_avatar, u.user_avatar_type, u.user_avatar_width, u.user_avatar_height, t.topic_id, t.topic_title, t.forum_id, t.topic_last_post_id, t.topic_last_post_time, t.topic_replies_real, t.topic_views, f.forum_id, f.forum_name
			FROM ' . ATTACHMENTS_TABLE . ' a
				INNER JOIN ' . TOPICS_TABLE . ' t ON (a.topic_id = t.topic_id)
					INNER JOIN ' . USERS_TABLE . ' u ON (a.poster_id = u.user_id)
						INNER JOIN ' . FORUMS_TABLE . ' f ON (t.forum_id = f.forum_id)
							WHERE a.topic_id = t.topic_id
								AND ' . $db->sql_in_set('extension', $ext_array) . '
								AND ' . $db->sql_in_set('t.forum_id', $authed_attachments) . '
								AND ' . $db->sql_in_set('t.forum_id', $forum_ids_to_show) . '
								AND t.forum_id <> 0
							GROUP BY post_msg_id
							ORDER BY RAND()';
	
		$result = $db->sql_query_limit($sql, $max_limit);
	
		while ($row = $db->sql_fetchrow($result))
		{	
			// BEGAN - Properly resize the Avatar of poster
			if ( $row['user_avatar_width'] >= $row['user_avatar_height'] )
			{
				$avatar_width = ( $row['user_avatar_width'] > $avatar_max_dimensions ) ? $avatar_max_dimensions : $row['user_avatar_width'] ;
				$avatar_height = ( $avatar_width == $avatar_max_dimensions ) ? round($avatar_max_dimensions / $row['user_avatar_width'] * $row['user_avatar_height']) : $row['user_avatar_height'] ;
			}
			else 
			{
				$avatar_height = ( $row['user_avatar_height'] > $avatar_max_dimensions ) ? $avatar_max_dimensions : $row['user_avatar_height'] ;
				$avatar_width = ( $avatar_height == $avatar_max_dimensions ) ? round($avatar_max_dimensions / $row['user_avatar_height'] * $row['user_avatar_width']) : $row['user_avatar_width'] ;
			}
			// ENDED - Properly resize the Avatar of poster
			
			// Obtain the mess of data
			$forum_id = $row['forum_id'];
			$forum_name = $row['forum_name'];
			
			$topic_id = $row['topic_id'];
			$topic_title = $row['topic_title'];
			
			$attach_id = $row['attach_id'];
			$attachment_date = $user->format_date($row['filetime'], "|M d 'y|");
			$attachment_time = $user->format_date($row['filetime'], "g:ia");
			$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id);

			$poster_name = get_username_string('username', $row['user_id'], $row['username'], $row['user_colour']);
			$poster_name_full = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
			$poster_avatar = get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $avatar_width, $avatar_height, $user->lang['ATTACHED_BY'] . ' ' . $poster_name);
			
			$thumbnail = $row['thumbnail'];

			// Trim the topic titles
			if ($num_chars != 0 and utf8_strlen($topic_title) > $num_chars)
			{
				$topic_title = utf8_substr($topic_title, 0, $num_chars) . '...';
			}

			if($row['thumbnail'] == 1)
			{
				$row['physical_filename'] = 'thumb_' . $row['physical_filename'];
				$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id . '&amp;t=1');
			}
			else
			{
				$row['physical_filename'] = $row['physical_filename'];
				$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id);
			}

			// Resize image
			if ($ext_array = array('jpeg', 'jpg', 'gif', 'png', 'bmp') || ($row['physical_filename'] = $row['physical_filename']))
			{
				$which_resize = @getimagesize($config['upload_path'] . '/' . $row['physical_filename']);
				if ($which_resize[0] > $resize_after)
				{
					$ratio 	= $max_width/$which_resize[0];
					$h_vign = round($which_resize[1]*$ratio);
					$l_vign = $max_width;
					$window_width = $l_vign+10;
					$window_height = $h_vign+10;
				}
				else 
				{
					$l_vign = $which_resize[0];
					$h_vign = $which_resize[1];
				}
			}

			// Assign index specific vars
			$template->assign_block_vars('attached_random_images', array(
				'FORUM_NAME'			=> $forum_name,
				'TOPIC_TITLE'			=> $topic_title,
				
				'ATTACHED_IMG_WIDTH'	=> $l_vign,
				'ATTACHED_IMG_HEIGHT'	=> $h_vign,
				
				'POSTER_AVATAR'			=> $poster_avatar,
				'POSTER_NAME'			=> $poster_name,
				'POSTER_NAME_FULL'		=> $poster_name_full,
				
				'ATTACHMENT_VIEWS'		=> $row['topic_views'],
				'ATTACHMENT_REPLIES'	=> $row['topic_replies_real'],
				'ATTACHMENT_DATE'		=> $attachment_date,
				'ATTACHMENT_TIME'		=> $attachment_time,

				'U_ATTACHED_IMG'			=> $attachment_url,
				'U_FORUM'          			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),				
				'U_ATTACHMENT_POST'      	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['post_msg_id'] .'#p'.$row['post_msg_id']),
				'U_TOPIC_LAST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['topic_last_post_id'] .'#p'.$row['topic_last_post_id']),
				'U_POSTER' 					=> append_sid($phpbb_root_path . 'memberlist.' . $phpEx, array('mode' => 'viewprofile', 'u' => $row['user_id'])),
				'U_VIEW_TOPIC'				=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $forum_id . '&amp;t=' . $topic_id),
			));

			// If no attachments to display, we already parsed them
			$attachments[$row['post_msg_id']][] = $row;
			if (!empty($attachments[$row['post_msg_id']]))
			{
				foreach ($attachments[$row['post_msg_id']] as $attachment)
				{
					$template->assign_vars(array(
						'RANDOM_ATTACHMENTS_EXIST'	=> (!empty($attachments[$row['post_msg_id']])) ? true : false,
					));
				}
			}
		}

		// Assign specific vars
		$template->assign_vars(array(
			'COLSPAN'		=> $max_limit,
			'VERTICAL'		=> ($orientation_arg == 'vertical') ? true : false,
			'FIDS'			=> $forum_ids_to_show,
		));

	$db->sql_freeresult($result);
	}

}

?>