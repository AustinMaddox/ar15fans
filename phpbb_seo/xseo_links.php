<?php
/** 
* 
* @version $Id: xseo_links.php dcz Exp $
* @copyright (c) 2008 dcz - www.phpbb-seo.com
* @license http://opensource.org/licenses/lgpl-3.0.html GNU Lesser General Public License v2
*
*/
/**
* @ignore
*/
if (!defined('IN_PHPBB')) {
	exit;
}
/**
* xseo_links ($message)
* Do the actual translation from one matching URL to a BBCoded plus topic title one
* $message : raw message with bbcodes
**/
function xseo_links ($message) {
	global $phpEx, $db, $phpbb_seo, $phpbb_root_path;
	static $server_urls;
	if (empty($phpbb_seo->seo_opt['url_rewrite'])) {
		return $message;
	}
	// Why not going as fast as possible ;-)
	$phpbb_seo->seo_url = array_merge(
		array(
			'xforum' => array(),
			'xtopic' => array(),
			'xuser' => array(),
			'xpost' => array(),
			'xorig' => array(	
				// 'http://www.example.com.com/phpBB'
			),
			'xcache' => array(),
		), 
		$phpbb_seo->seo_url
	);
	// The urls to take as possible source
	if (!isset($server_urls)) {
		$server_urls = array(trim(generate_board_url(), '/'));
		foreach ($phpbb_seo->seo_url['xorig'] as $xorig) {
			$server_urls[] = preg_quote($xorig, '`');
		}
	}
	static $magic_url_match;
	static $magic_url_replace;
	static $url_patterns;
	if (empty($magic_url_match)) {
		$magic_url_match = $magic_url_replace = $url_patterns = array();
		foreach ($server_urls as $server_url ) {
			$url_patterns[] = $server_url . "/[a-z0-9_/-]*(\.$phpEx|\.html|/)[\w\#$%&~/.\-;:=,?@+]*";
		}
		// Matches both with and without http://
		foreach ($url_patterns as $url_pattern) {
			// bbcoded url that may require an update
			// [url=valid_url].*[/url]
			$magic_url_match[] = '`\[url=(' . $url_pattern . ')\](.*)\[/url\]`Uie';
			$magic_url_replace[] = "xseo_links_callback('\$0', '\$1', '', '\$3')";
			// [url]valid_url[/url]
			$magic_url_match[] = '`(\[url\])\s*(' . $url_pattern . ')\s*\[/url\]`Uie';
			$magic_url_replace[] = "xseo_links_callback('\$0', '\$2')";
			// inline valid_url
			$magic_url_match[] = '`(^|[\n\t\r \(\>\.]+)(' . $url_pattern . ')`ie';
			$magic_url_replace[] = "xseo_links_callback('\$0', '\$2', '\$1')";
		}
	}
	// Tickle preg_replace ;-)
	return preg_replace($magic_url_match, $magic_url_replace, $message);
}
/**
* xseo_links_callback($full_mask, $url, $filebit, $whitespace = '', $bbcode_title = '')
* Do the actual translation from one matching URL to a BBCoded plus topic title one
*
**/
function xseo_links_callback($full_mask, $url, $whitespace = '', $bbcode_title = '') {
	global $db, $phpbb_seo, $phpEx, $phpbb_root_path, $config;
	static $server_url;
	static $server_urls;
	// phpBB2 Cat : associative array cat_id => forum_id needs to be set here
	static $cattoforum = array();
	// Why not going as fast as possible ;-)
	if (isset($phpbb_seo->seo_url['xcache'][$full_mask])) {
		return $phpbb_seo->seo_url['xcache'][$full_mask];
	}
	// The replacement domain
	if (!isset($server_url)) {
		$server_url = trim(generate_board_url(), '/');
		$server_urls = $phpbb_seo->seo_url['xorig'];
		$server_urls[] = $server_url;

	}
	$modified = false;
	$keep_vars = false;
	$title = '';
	$qs = '';
	$anchor = '';
	$get_vars = array();
	$forum_id = $topic_id = $post_id = $user_id = $start = 0;
	// Build url, make sure it is properly formated
	static $url_find = array('`^http\://`i', '`&(amp;)?amp;`i');
	static $url_replace = array('', '&');
	$url = 'http://' . trim(preg_replace($url_find, $url_replace, trim($url)));
	if (preg_match('`#([a-z0-9_-]+)$`i', $url, $match)) {
		$anchor = '#' . $match[1];
		$url = preg_replace('`' . $anchor . '$`i', '', $url);
	}
	@list($url, $qs) = explode('?', $url, 2);
	if ($qs) {
		parse_str($qs, $get_vars);
	}
	$url = str_replace($server_urls, '', $url);
	// Url rewritten links
	if (strpos($url, ".$phpEx") === false && (preg_match('`(-v?[ftc]{1}|' . $phpbb_seo->seo_static['topic'] . '|' . $phpbb_seo->seo_static['forum'] . '|' . $phpbb_seo->seo_static['post'] . ')([0-9]+)(-([0-9]+)|/' . $phpbb_seo->seo_static['pagination'] . '([0-9]+))?(\.html|/)$`i', $url, $matches)
	|| preg_match('`([a-z0-9_-]+)(/' . $phpbb_seo->seo_static['pagination'] . '([0-9]+))?(\.html|/)?$`i', $url, $matches)) ) {
		switch ($matches[1]) {
			case '-vf':
			case '-f':
				$forum_id = (int) $matches[2];
				$start = !empty($matches[4]) ? (int) $matches[4] : (!empty($matches[5]) ? (int) $matches[5] : 0);
				break;
			case $phpbb_seo->seo_static['topic']:
			case '-vt':
			case '-t':
				$topic_id = (int) $matches[2];
				$start = !empty($matches[4]) ? (int) $matches[4] : (!empty($matches[5]) ? (int) $matches[5] : 0);
				break;
			case $phpbb_seo->seo_static['post']:
				$post_id = (int) $matches[2];
				break;
			case '-vc':
			case '-c':
				// Cat
				$forum_id = !empty($cattoforum[$matches[2]]) ? (int) $cattoforum[$matches[2]] : 0;
				break;
			default :
				// Forum without ids
				$phpbb_seo->get_forum_id($forum_id, $matches[1]);
				if ($forum_id) {
					$start = !empty($matches[3]) ? (int) $matches[3] : 0;
				}
				break;
		}
	} else {
		$file = str_replace(".$phpEx", '', basename($url));
		$forum_id = isset($get_vars['f']) && $file == 'viewforum' ? max(0, (int) $get_vars['f']) : 0;
		$topic_id = isset($get_vars['t']) && $file == 'viewtopic' ? max(0, (int) $get_vars['t']) : 0;
		$post_id = isset($get_vars['p']) && $file == 'viewtopic' ? max(0, (int) $get_vars['p']) : 0;
		$user_id = isset($get_vars['u']) && ($file == 'memberlist' || $file == 'profile') ? max(0, (int) $get_vars['u']) : 0;
		$start = isset($get_vars['start']) ? max(0, (int) $get_vars['start']) : 0;
	}
	unset($get_vars['f'], $get_vars['t'], $get_vars['p'], $get_vars['u'], $get_vars['start'], $get_vars['sid']);
	if ($forum_id && !$topic_id && !$post_id && !$user_id) { // Forum url
		$keep_vars = true;
		if (empty($phpbb_seo->seo_url['xforum'][$forum_id])) {
			$sql = "SELECT forum_id, forum_name, forum_topics
				FROM " . FORUMS_TABLE . "
				WHERE forum_id = $forum_id";
			$result = $db->sql_query($sql);
			if ($row = $db->sql_fetchrow($result)) {
				$phpbb_seo->set_url($row['forum_name'], $forum_id, $phpbb_seo->seo_static['forum']);
				$url = $phpbb_seo->seo_url['xforum'][$forum_id]['url'] = $phpbb_root_path . "viewforum.$phpEx?f=" . $row['forum_id'];
				$title = $phpbb_seo->seo_url['xforum'][$forum_id]['title'] = $row['forum_name'];
				$topics_count = $phpbb_seo->seo_url['xforum'][$forum_id]['topics_count'] = $row['forum_topics'];
				$modified = true;
			}
		} else {
			$url = $phpbb_seo->seo_url['xforum'][$forum_id]['url'];
			$title = $phpbb_seo->seo_url['xforum'][$forum_id]['title'];
			$topics_count = $phpbb_seo->seo_url['xforum'][$forum_id]['topics_count'];
			$modified = true;
		}
		if ($modified && $start) {
			if ($start >= $topics_count) {
				$start = floor(($topics_count - 1) / $config['topics_per_page']) * $config['topics_per_page'];
			} else {
				$start = $phpbb_seo->seo_chk_start( $start, $config['topics_per_page'] );
			}
			$url .= "&amp;start=$start";
		}
	} elseif ($post_id) { // Post
		$keep_vars = true;
		if (empty($phpbb_seo->seo_url['xpost'][$post_id])) {
			$sql = "SELECT p.post_id, p.post_subject, t.topic_title
				FROM " . POSTS_TABLE . " p, " . TOPICS_TABLE . " t
			 	WHERE p.post_id = $post_id
			 	ANd t.topic_id = p.topic_id";
			$result = $db->sql_query($sql);
			if ( $row = $db->sql_fetchrow($result) ) {
				$url = $phpbb_seo->seo_url['xpost'][$post_id]['url'] = $phpbb_root_path . "viewtopic.$phpEx?p=" . $row['post_id'];
				$title = $phpbb_seo->seo_url['xpost'][$post_id]['title'] = !empty($row['post_subject']) ?  $row['post_subject'] :  $row['topic_title'];
				$modified = true;
			}
		} else {
			$url = $phpbb_seo->seo_url['xpost'][$post_id]['url'];
			$title = $phpbb_seo->seo_url['xpost'][$post_id]['title'];
			$modified = true;
		}
		$anchor = $anchor ? $anchor  : "#p$post_id";
	} elseif ($topic_id) { // Topic
		// we do this to keep the proper rewriting for complex cases (watch= etc ...)
		$keep_vars = true;
		if (empty($phpbb_seo->seo_url['xtopic'][$topic_id])) {
			$sql = "SELECT t.topic_title, t.topic_id, t.forum_id, t.topic_type, t.topic_replies " . (!empty($phpbb_seo->seo_opt['sql_rewrite']) ? ', t.topic_url ' : ' ') . ", f.forum_name
				FROM " . TOPICS_TABLE . " t, " . FORUMS_TABLE . " f
				WHERE t.topic_id = $topic_id
				AND f.forum_id = t.forum_id";
			$result = $db->sql_query($sql);
			if ($row = $db->sql_fetchrow($result)) {
				$row['topic_title'] = censor_text($row['topic_title']);
				$phpbb_seo->set_url($row['forum_name'], $row['forum_id'], $phpbb_seo->seo_static['forum']);
				$phpbb_seo->prepare_iurl($row, 'topic', $row['topic_type'] == POST_GLOBAL ? $phpbb_seo->seo_static['global_announce'] : $phpbb_seo->seo_url['forum'][$row['forum_id']]);
				$url = $phpbb_seo->seo_url['xtopic'][$topic_id]['url'] = $phpbb_root_path . "viewtopic.$phpEx?f=" . $row['forum_id'] . '&amp;t=' . $topic_id;
				$title = $phpbb_seo->seo_url['xtopic'][$topic_id]['title'] = $row['topic_title'];
				$topic_replies = $phpbb_seo->seo_url['xuser'][$user_id]['topic_replies'] = $row['topic_replies'];
				$modified = true;
			}
		} else {
			$url = $phpbb_seo->seo_url['xtopic'][$topic_id]['url'];
			$title = $phpbb_seo->seo_url['xtopic'][$topic_id]['title'];
			$topic_replies = $phpbb_seo->seo_url['xuser'][$user_id]['topic_replies'];
			$modified = true;
		}
		if ($modified && $start) {
			$total_posts = $topic_replies + 1;
			if ($start >= $total_posts) {
				$start = floor(($total_posts - 1) / $config['posts_per_page']) * $config['posts_per_page'];
			} else {
				$start = $phpbb_seo->seo_chk_start( $start, $config['posts_per_page'] );
			}
			$url .= "&amp;start=$start";
		}
	} elseif ($user_id) { // user
		if (empty($phpbb_seo->seo_url['xuser'][$user_id])) {
			$sql = "SELECT username
				 FROM " . USERS_TABLE . "
				 WHERE user_id = $user_id";
			$result = $db->sql_query($sql);
			if ( $row = $db->sql_fetchrow($result) ) {
				$phpbb_seo->set_user_url( $row['username'], $user_id );
				$url = $phpbb_seo->seo_url['xuser'][$user_id]['url'] = $phpbb_root_path . "memberlist.$phpEx?mode=viewprofile&amp;u=$user_id";
				$title = $phpbb_seo->seo_url['xuser'][$user_id]['title'] = $row['username'];
				$modified = true;
			}
		} else {
			$url = $phpbb_seo->seo_url['xuser'][$user_id]['url'];
			$title = $phpbb_seo->seo_url['xuser'][$user_id]['title'];
			$modified = true;
		}
	} else {
		foreach ($phpbb_seo->seo_url['xorig'] as $_orig_url) {
			if (strpos($full_mask, $_orig_url) !== false) {
				$full_mask = str_replace($_orig_url, $server_url, $full_mask);
				break;
			}
		}
	}
	// return the original code if not modified
	if (!$modified) {
		return ($phpbb_seo->seo_url['xcache'][$full_mask] = $full_mask);
	}
	$happend = '';
	if($keep_vars && !empty($get_vars)) {
		$params = array();
		foreach($get_vars as $key => $value) {
			$happend .= '&amp;' . $key . '=' . $value;
		}
	}
	if (!empty($bbcode_title)) {
		if (preg_match('`\[img[^\[]*\][^\[]*\[/img\]`', $bbcode_title)) {
			$title = $bbcode_title;
		} else {
			$title = ltrim(htmlspecialchars_decode(str_replace($server_url, '', trim($bbcode_title))), '/');
		}
	}
	$url = append_sid( $url . $happend, false, true, 0 );
	if (strpos($url, $server_url) === false) {
		$url = str_replace($phpbb_root_path, "$server_url/", $url);
	}
	return ($phpbb_seo->seo_url['xcache'][$full_mask] = $whitespace . '[url=' . $url . $anchor . ']' . $title . '[/url]');
}
?>
