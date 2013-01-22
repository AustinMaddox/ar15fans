<?php
/**
*
* @package
* @version $Id: functions_attached_images.php,v0.0.1 2012/01/05 17:00:00 austin881 Exp $
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

function attached_images($type, $forum_ids, $max_num_results, $orientation, $num_chars, $max_width_img = false, $max_width_avatar = false)
{
	global $config, $db, $auth, $user, $template, $phpbb_root_path, $phpEx;

	$array_ext = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
	$max_num_results = (!empty($max_num_results)) ? $max_num_results : 1;
	$order = ($type == 'recent') ? 'filetime DESC, post_msg_id ASC' : 'RAND()';
	$max_width_img = (!empty($max_width_img)) ? $max_width_img : 200;
	$max_width_avatar = (!empty($max_width_avatar)) ? $max_width_avatar : 50;
	$num_results = 0;

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
		$sql = 'SELECT a.post_msg_id, a.attach_id, a.attach_comment, a.physical_filename, a.poster_id, a.filetime, a.thumbnail,
				u.user_id, u.username, u.user_colour, u.user_avatar, u.user_avatar_type, u.user_avatar_width, u.user_avatar_height,
				t.topic_id, t.topic_title, t.forum_id, t.topic_last_post_id, t.topic_replies_real, t.topic_views,
				f.forum_id, f.forum_name
			FROM ' . ATTACHMENTS_TABLE . ' a
				INNER JOIN ' . TOPICS_TABLE . ' t ON (a.topic_id = t.topic_id)
					INNER JOIN ' . USERS_TABLE . ' u ON (a.poster_id = u.user_id)
						INNER JOIN ' . FORUMS_TABLE . ' f ON (t.forum_id = f.forum_id)
							WHERE a.topic_id = t.topic_id
								AND ' . $db->sql_in_set('extension', $array_ext) . '
								AND ' . $db->sql_in_set('t.forum_id', $authed_attachments) . '
								AND ' . $db->sql_in_set('t.forum_id', $forum_ids) . '
								AND t.forum_id <> 0
								AND t.topic_approved = 1
							GROUP BY post_msg_id
							ORDER BY ' . $order;
		
		$result = $db->sql_query_limit($sql, $max_num_results, 0, 60);
		
		while ($row = $db->sql_fetchrow($result))
		{
			$num_results++;
			
			// Resize the avatar of the poster
			$dimensions_avatar = resize_image($row['user_avatar_width'], $row['user_avatar_height'], $max_width_avatar);

			// Obtain the mess of data
			$forum_id = $row['forum_id'];
			$forum_name = $row['forum_name'];

			$topic_id = $row['topic_id'];
			$topic_title = $row['topic_title'];

			$attach_id = $row['attach_id'];
			$attach_comment = $row['attach_comment'];
			$attachment_date = $user->format_date($row['filetime'], "|M d 'y|");
			$attachment_time = $user->format_date($row['filetime'], "g:ia");
			
			if ($row['thumbnail'] == 1)
			{
				$filename = 'thumb_' . $row['physical_filename'];
				$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id . '&amp;t=1');
			}
			else
			{
				$filename = $row['physical_filename'];
				$attachment_url = append_sid($phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id);
			}

			$poster_name = get_username_string('username', $row['user_id'], $row['username'], $row['user_colour']);
			$poster_name_full = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
			$poster_avatar = get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $dimensions_avatar['width'], $dimensions_avatar['height'], $user->lang['ATTACHED_BY'] . ' ' . $poster_name);
			
			// Trim the topic titles
			if ($num_chars != 0 && utf8_strlen($topic_title) > $num_chars)
			{
				$topic_title = utf8_substr($topic_title, 0, $num_chars) . '...';
			}

			// Resize the attachment image
			$imagesize = @getimagesize($config['upload_path'] . '/' . $filename);
			$dimensions_img = resize_image($imagesize[0], $imagesize[1], $max_width_img);
			
			// Assign index specific vars
			$template->assign_block_vars('attached_images', array(
				'FORUM_NAME'			=> $forum_name,
				'TOPIC_TITLE'			=> $topic_title,

				'ATTACHED_IMG_WIDTH'	=> $dimensions_img['width'],
				'ATTACHED_IMG_HEIGHT'	=> $dimensions_img['height'],

				'POSTER_AVATAR'			=> $poster_avatar,
				'POSTER_NAME'			=> $poster_name,
				'POSTER_NAME_FULL'		=> $poster_name_full,

				'ATTACHMENT_VIEWS'		=> $row['topic_views'],
				'ATTACHMENT_REPLIES'	=> $row['topic_replies_real'],
				'ATTACHMENT_DATE'		=> $attachment_date,
				'ATTACHMENT_TIME'		=> $attachment_time,
				'ATTACHMENT_COMMENT'	=> $attach_comment,

				'U_ATTACHED_IMG'			=> $attachment_url,
				'U_FORUM'          			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
				'U_ATTACHMENT_POST'      	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['post_msg_id'] .'#p'.$row['post_msg_id']),
				'U_TOPIC_LAST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['topic_last_post_id'] .'#p'.$row['topic_last_post_id']),
				'U_POSTER' 					=> append_sid($phpbb_root_path . 'memberlist.' . $phpEx, array('mode' => 'viewprofile', 'u' => $row['user_id'])),
				'U_VIEW_TOPIC'				=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $forum_id . '&amp;t=' . $topic_id),
			));
		}
		
		// Assign specific vars
		$template->assign_vars(array(
			'NUM_RESULTS'			=> $num_results,
			'COLSPAN'				=> $max_num_results,
			'VERTICAL'				=> ($orientation == 'vertical') ? true : false,
			'ATTACHED_IMAGE_TITLE'	=> sprintf($user->lang['ATTACHED_IMAGE_TITLE'], $type),
		));
		
		$db->sql_freeresult($result);
	}
}

function resize_image($actual_w, $actual_h, $max_dimension) {
	if ($actual_w >= $actual_h)
	{
		$w = ($actual_w > $max_dimension) ? $max_dimension : $actual_w;
		$h = ($w == $max_dimension) ? round($max_dimension / $actual_w * $actual_h) : $actual_h;
	}
	else
	{
		$h = ($actual_h > $max_dimension) ? $max_dimension : $actual_h;
		$w = ($h == $max_dimension) ? round($max_dimension / $actual_h * $actual_w) : $actual_w;
	}
	$dimensions = array(
			'width' => $w,
			'height' => $h
	);
	return $dimensions;
}

?>