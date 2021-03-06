<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


//
// Generate the config cache PHP script
//
function generate_config_cache()
{
	global $db;

	// Get the forum config from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'config', true) or error('Unable to fetch forum config', __FILE__, __LINE__, $db->error());
	while ($cur_config_item = $db->fetch_row($result))
		$output[$cur_config_item[0]] = $cur_config_item[1];

	// Output config as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_config.php', 'wb');
	if (!$fh)
		error('Unable to write configuration cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'PUN_CONFIG_LOADED\', 1);'."\n\n".'$pun_config = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);

	if (function_exists('apc_delete_file'))
		@apc_delete_file(FORUM_CACHE_DIR.'cache_config.php');
}


//
// Generate the bans cache PHP script
//
function generate_bans_cache()
{
	global $db;

	// Get the ban list from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'bans', true) or error('Unable to fetch ban list', __FILE__, __LINE__, $db->error());

	$output = array();
	while ($cur_ban = $db->fetch_assoc($result))
		$output[] = $cur_ban;

	// Output ban list as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_bans.php', 'wb');
	if (!$fh)
		error('Unable to write bans cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'PUN_BANS_LOADED\', 1);'."\n\n".'$pun_bans = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);

	if (function_exists('apc_delete_file'))
		@apc_delete_file(FORUM_CACHE_DIR.'cache_bans.php');
}


//
// Generate the ranks cache PHP script
//
function generate_ranks_cache()
{
	global $db;

	// Get the rank list from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'ranks ORDER BY min_posts', true) or error('Unable to fetch rank list', __FILE__, __LINE__, $db->error());

	$output = array();
	while ($cur_rank = $db->fetch_assoc($result))
		$output[] = $cur_rank;

	// Output ranks list as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_ranks.php', 'wb');
	if (!$fh)
		error('Unable to write ranks cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'PUN_RANKS_LOADED\', 1);'."\n\n".'$pun_ranks = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);

	if (function_exists('apc_delete_file'))
		@apc_delete_file(FORUM_CACHE_DIR.'cache_ranks.php');
}


//
// Generate quick jump cache PHP scripts
//
function generate_quickjump_cache($group_id = false)
{
	global $db, $lang_common, $pun_user;

	// If a group_id was supplied, we generate the quick jump cache for that group only
	if ($group_id !== false)
	{
		// Is this group even allowed to read forums?
		$result = $db->query('SELECT g_read_board FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch user group read permission', __FILE__, __LINE__, $db->error());
		$read_board = $db->result($result);
		
		$groups[$group_id] = $read_board;
	}
	else
	{
		// A group_id was not supplied, so we generate the quick jump cache for all groups
		$result = $db->query('SELECT g_id, g_read_board FROM '.$db->prefix.'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
		$num_groups = $db->num_rows($result);

		while ($row = $db->fetch_row($result))
			$groups[$row[0]] = $row[1];
	}

	// Loop through the groups in $groups and output the cache for each of them
	foreach ($groups as $group_id => $read_board)
	{
		// Output quick jump as PHP code
		$fh = @fopen(FORUM_CACHE_DIR.'cache_quickjump_'.$group_id.'.php', 'wb');
		if (!$fh)
			error('Unable to write quick jump cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

		$output = '<?php'."\n\n".'if (!defined(\'PUN\')) exit;'."\n".'define(\'PUN_QJ_LOADED\', 1);'."\n".'$forum_id = isset($forum_id) ? $forum_id : 0;'."\n\n".'?>';
		
		if ($read_board == '1')
		{
			$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$group_id.') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result))
			{
				$output .= "\t\t\t\t".'<form id="qjump" method="get" action="viewforum.php">'."\n\t\t\t\t\t".'<div><label><span><?php echo $lang_common[\'Jump to\'] ?>'.'<br /></span>'."\n\t\t\t\t\t".'<select name="id" onchange="window.location=(\'viewforum.php?id=\'+this.options[this.selectedIndex].value)">'."\n";

				$cur_category = 0;
				while ($cur_forum = $db->fetch_assoc($result))
				{
					if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
					{
						if ($cur_category)
							$output .= "\t\t\t\t\t\t".'</optgroup>'."\n";

						$output .= "\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
						$cur_category = $cur_forum['cid'];
					}

					$redirect_tag = ($cur_forum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
					$output .= "\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'"<?php echo ($forum_id == '.$cur_forum['fid'].') ? \' selected="selected"\' : \'\' ?>>'.pun_htmlspecialchars($cur_forum['forum_name']).$redirect_tag.'</option>'."\n";
				}

				$output .= "\t\t\t\t\t\t".'</optgroup>'."\n\t\t\t\t\t".'</select>'."\n\t\t\t\t\t".'<input type="submit" value="<?php echo $lang_common[\'Go\'] ?>" accesskey="g" />'."\n\t\t\t\t\t".'</label></div>'."\n\t\t\t\t".'</form>'."\n";
			}
		}

		fwrite($fh, $output);

		fclose($fh);

		if (function_exists('apc_delete_file'))
			@apc_delete_file(FORUM_CACHE_DIR.'cache_quickjump_'.$group_id.'.php');
	}
}

define('FORUM_CACHE_FUNCTIONS_LOADED', true);
