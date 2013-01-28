<?php
//
//	file: language/en/mods/info_acp_apiv.php
//	author: abdev
//	begin: 08/27/2012
//	version: 0.0.3 - 09/01/2012
//	licence: http://opensource.org/licenses/gpl-license.php GNU Public License
//

// ignore
if ( !defined('IN_PHPBB') )
{
	exit;
}

// init lang ary, if it doesn't !
if ( empty($lang) || !is_array($lang) )
{
	$lang = array();
}

// administration
$lang = array_merge($lang, array(
	'APIV_MAX_DIMENSIONS' => 'Maximum dimension for the displaying of the avatar',
	'APIV_MAX_DIMENSIONS_EXPLAIN' => 'The displayed avatar will not exceed the specified dimension.',
	'APIV_FORUMS_LAST_POSTER_SHOW' => 'Display the avatar of the latest post author on index and in the subforums',
	'APIV_TOPICS_FIRST_POSTER_SHOW' => 'Display the avatar of the first post author in the forums',
	'APIV_TOPICS_LAST_POSTER_SHOW' => 'Display the avatar of the latest post author in the forums',
));

// umil
$lang = array_merge($lang, array(
	'APIV' => 'Avatar of Poster on Index and Viewforum',

	'INSTALL_APIV' => 'Install “Avatar of Poster on Index and Viewforum”',
	'INSTALL_APIV_CONFIRM' => 'Are you ready to install “Avatar of Poster on Index and Viewforum” ?',
	'UPDATE_APIV' => 'Update “Avatar of Poster on Index and Viewforum”',
	'UPDATE_APIV_CONFIRM' => 'Are you ready to update “Avatar of Poster on Index and Viewforum” ?',
	'UNINSTALL_APIV' => 'Uninstall “Avatar of Poster on Index and Viewforum”',
	'UNINSTALL_APIV_CONFIRM' => 'Are you ready to uninstall “Avatar of Poster on Index and Viewforum” ? All settings and data saved by this MOD will be removed !',
));

// functions
$lang = array_merge($lang, array(
	'APIV_AVATARS_SYNCHRONIZED' => 'The information concerning avatars have been synchronized.',

	'APIV_130_UPDATED' => 'Your “Avatar of Poster on Index and Viewforum” version has now been updated.<br /><br />Do not forget to delete this file from your server.',
	'APIV_130_GREATER' => 'You are already using a greater or equal version to this one.<br /><br />Do not forget to delete this file from your server.',
));
