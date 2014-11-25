<?php
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("usercp_start", "sessions_usercp");
$plugins->add_hook("global_start", "sessions_manage");
$plugins->add_hook("member_logout_end", "sessions_logout");

function sessionmanager_info()
{
	return array(
		"name"			=> "Session Manager",
		"description"	=> "Adds a session manager to the User Control Panel",
		"website"		=> "http://jonesboard.de/",
		"author"		=> "Jones",
		"authorsite"	=> "http://jonesboard.de/",
		"version"		=> "1.0",
		"compatibility" => "18*",
		"codename"		=> "sessionmanager"
	);
}

function sessionmanager_install()
{
	global $db;

	$templateset = array(
		"prefix"	=> "sessions",
		"title"		=> "MyBB Session Manager"
	);
	$db->insert_query("templategroups", $templateset);

	$template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->sessions}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead" colspan="5"><strong>{$lang->sessions}</strong></td>
			</tr>
			<tr>
				<td class="tcat">{$lang->location}</td>
				<td class="tcat">{$lang->browser}</td>
				<td class="tcat">{$lang->page}</td>
				<td class="tcat" colspan="2">{$lang->lastseen}</td>
			</tr>
          {$current_session}
          {$sessions}
      </table>
	</td>
</tr>
</table>
{$footer}
</body>
</html>';
	$templatearray = array(
		"title" => "sessions_page",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template = '	<td class="{$trow}"><a href="usercp.php?action=sessionmanager&revoke={$ses[\'sid\']}">{$lang->revoke}</a></td>';
	$templatearray = array(
		"title" => "sessions_revoke",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template = '<img src="{$mybb->settings[\'bburl\']}/images/buddy_online.png" alt="[{$lang->activesession}]" title="{$lang->activesession}" />';
	$templatearray = array(
		"title" => "sessions_statusicon_on",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template = '<img src="{$mybb->settings[\'bburl\']}/images/buddy_offline.png" />';
	$templatearray = array(
		"title" => "sessions_statusicon_off",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template = '<tr>
	<td class="{$trow}">{$active}{$ses[\'loc\']}</td>
	<td class="{$trow}">{$ses[\'browser\']}</td>
	<td class="{$trow}">{$ses[\'page\']}</td>
	<td class="{$trow}" {$colspan}>{$ses[\'time\']}</td>
	{$revoke}
</td>';
	$templatearray = array(
		"title" => "sessions_row",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$col = $db->build_create_table_collation();
	$db->query("CREATE TABLE `".TABLE_PREFIX."sessionmanager` (
				`bid` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`sid` varchar(32) NOT NULL,
				`uid` int(10) unsigned NOT NULL DEFAULT '0',
				`ip` varbinary(16) NOT NULL,
				`time` int(10) unsigned NOT NULL DEFAULT '0',
				`location` varchar(150) NOT NULL,
				`useragent` varchar(200) NOT NULL,
				`revoked` tinyint(1) NOT NULL DEFAULT '0',
			PRIMARY KEY (`bid`) ) ENGINE=MyISAM {$col}");
}

function sessionmanager_is_installed()
{
	global $db;
	return $db->table_exists("sessionmanager");
}

function sessionmanager_uninstall()
{
	global $db;

	$db->delete_query("templategroups", "prefix='sessions'");
	$db->delete_query("templates", "title='sessions_page'");
	$db->delete_query("templates", "title='sessions_revoke'");
	$db->delete_query("templates", "title='sessions_row'");

	$db->drop_table("sessionmanager");
}


function sessionmanager_activate()
{
	global $db;

	require_once MYBB_ROOT."inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('{$lang->ucp_nav_change_pass}</a></div>')."#i", "{\$lang->ucp_nav_change_pass}</a></div>
		<div><a href=\"usercp.php?action=sessionmanager\" class=\"usercp_nav_item usercp_nav_password\">{\$lang->sessions}</a></div>");
}

function sessionmanager_deactivate()
{
	global $db;

	require_once MYBB_ROOT."inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('<div><a href="usercp.php?action=sessionmanager" class="usercp_nav_item usercp_nav_password">{$lang->sessions}</a></div>')."#i", '', 0);
}

function sessions_usercp()
{
	global $db, $mybb, $headerinclude, $header, $usercpnav, $theme, $footer, $templates, $lang, $session;

    if($mybb->input['action'] != "sessionmanager")
	    return;

	if(isset($mybb->input['revoke']))
	{
		// We're revoking a sid
		$sid = $db->escape_string($mybb->get_input("revoke"));

		// Don't close your own session stupid guy
		if($sid != $session->sid)
		{
			// Close the session - but only if it's our own!
			$db->update_query("sessionmanager", array("revoked" => 1), "sid='{$sid}' AND uid='{$mybb->user['uid']}'");
			redirect("usercp.php?action=sessionmanager", $lang->sessionclosed);
		}
	}

	require_once MYBB_ROOT."inc/functions_online.php";
	$lang->load("online");

	// We want to display the current session on top - and don't allow revoking it
	$revoke = ""; $colspan='colspan="2"';
	$active = eval($templates->render("sessions_statusicon_on"));
	$query = $db->simple_select("sessionmanager", "*", "sid='{$session->sid}'");
	$ses = $db->fetch_array($query);
	// Prepare output
	$trow = alt_trow(1);
	if(function_exists('geoip_record_by_name'))
	{
		$ip_record = @geoip_record_by_name(my_inet_ntop($db->unescape_binary($ses['ip'])));
		if($ip_record)
		{
			$ipaddress_location = "<br />".htmlspecialchars_uni($ip_record['country_name']);
			if($ip_record['city'])
			{
				$ipaddress_location .= $lang->comma.htmlspecialchars_uni($ip_record['city']);
			}
		}
		$ses['loc'] = $ipaddress_location." (".my_inet_ntop($db->unescape_binary($ses['ip'])).")";
	}
	else
	{
		$ses['loc'] = my_inet_ntop($db->unescape_binary($ses['ip']));
	}
	$ses['browser'] = getBrowser($ses['useragent']);
	$ses['time'] = my_date("relative", $ses['time']);
	$data = fetch_wol_activity($ses['location']);
	$ses['page'] = build_friendly_wol_location($data);
	$current_session = eval($templates->render("sessions_row"));

	// And now the other sessions...
	$colspan="";
	$active = eval($templates->render("sessions_statusicon_off"));
	$sessions = "";
	$query = $db->simple_select("sessionmanager", "*", "uid={$session->uid} AND sid!='{$session->sid}' AND revoked=0", array("order_by" => "time DESC"));
	while($ses = $db->fetch_array($query))
	{
		$trow = alt_trow();
		if(function_exists('geoip_record_by_name'))
		{
			$ip_record = @geoip_record_by_name(my_inet_ntop($db->unescape_binary($ses['ip'])));
			if($ip_record)
			{
				$ipaddress_location = "<br />".htmlspecialchars_uni($ip_record['country_name']);
				if($ip_record['city'])
				{
					$ipaddress_location .= $lang->comma.htmlspecialchars_uni($ip_record['city']);
				}
			}
			$ses['loc'] = $ipaddress_location." (".my_inet_ntop($db->unescape_binary($ses['ip'])).")";
		}
		else
		{
			$ses['loc'] = my_inet_ntop($db->unescape_binary($ses['ip']));
		}
		$ses['browser'] = getBrowser($ses['useragent']);
		$ses['time'] = my_date("relative", $ses['time']);
		$data = fetch_wol_activity($ses['location']);
		$ses['page'] = build_friendly_wol_location($data);
		$revoke = eval($templates->render("sessions_revoke"));
		$sessions .= eval($templates->render("sessions_row"));
	}

	$page = eval($templates->render("sessions_page"));
	output_page($page);
}

function sessions_manage()
{
	global $session, $db, $mybb, $lang;

	// As this is run on every page we can update the binary_fields array here
	$mybb->binary_fields['sessionmanager']['ip'] = true;

	if(THIS_SCRIPT == "usercp.php")
    	$lang->load("sessionmanager");

	// No time for guests or spiders
	if($session->uid == 0)
	    return;

	// Our update/insert array. we need it everytime so build it first
	$dbarray = array(
		"sid"		=> $session->sid, // No need to escape, is escaped in inc/class_session.php
		"uid"		=> $session->uid, // As above
		"ip"		=> $db->escape_binary($session->packedip),
		"time"		=> TIME_NOW,
		"location"	=> $db->escape_string(get_current_location()),
		"useragent"	=> $db->escape_string(my_substr($session->useragent, 0, 200)),
	);

	$found = false;
	// First check the current session id and update our session copy
	$query = $db->simple_select("sessionmanager", "*", "sid='{$dbarray['sid']}' AND uid={$session->uid}");
	if($db->num_rows($query) == 1)
	{
		// Found a session! Updating...
		$db->update_query("sessionmanager", $dbarray, "sid='{$dbarray['sid']}' AND uid={$session->uid}");
		$found = true;
	}
	elseif(isset($mybb->cookies['bid']))
	{
		// Try to get the session via browser id
		$bid = (int)$mybb->cookies['bid'];
		$query = $db->simple_select("sessionmanager", "*", "bid={$bid} AND uid={$session->uid}");
		if($db->num_rows($query) == 1)
		{
			// Found a session! Updating...
			$db->update_query("sessionmanager", $dbarray, "bid={$bid} AND uid={$session->uid}");
			$found = true;
		}
	}

	if($found === false)
	{
		// No luck? New session!
		$db->insert_query("sessionmanager", $dbarray);
	}

	// After all that hard word we can finally check whether we need to close this session
	$query = $db->simple_select("sessionmanager", "bid, revoked", "sid='{$dbarray['sid']}'");
	$ar = $db->fetch_array($query);

	// No cookie or invalid one?
    if(!isset($mybb->cookies['bid']) || $mybb->cookies['bid'] != $ar['bid'])
	{
		// Delete session with this browser id - it isn't used anymore
		if(!empty($mybb->cookies['bid']))
			$db->delete_query("sessionmanager", "bid=".(int)$mybb->cookies['bid']);
		// Setting our browser id to make sure that we can identify ourselves later again
		my_setcookie("bid", $ar['bid'], "", true);
	}

	if($ar['revoked'])
	{
		// We revoked this session - time to do some work
		my_unsetcookie("mybbuser");
		my_unsetcookie("sid");
		my_unsetcookie("bid");

		$time = TIME_NOW;
		// Run this after the shutdown query from session system
		$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastvisit='{$time}', lastactive='{$time}' WHERE uid='{$dbarray['uid']}'");
		$db->delete_query("sessions", "sid='{$session->sid}'");
		$db->delete_query("sessionmanager", "sid='{$session->sid}'");

		run_shutdown();

		// Force redirect
		header("Location: {$mybb->settings['bburl']}/index.php");
	}
}

function sessions_logout()
{
	global $db, $session;

	// As we have updated our sessions on global_start we can be sure that this session id is present. No need to check ip/useragent
	if($session->uid)
	{
		$db->delete_query("sessionmanager", "sid='{$session->sid}'");
		my_unsetcookie("bid");
	}
}

// Wasn't a priority, will be added in the next version
function getBrowser($useragent)
{
	require_once MYBB_ROOT."inc/plugins/sessionmanager/UserAgentParser.php";

	$info = parse_user_agent($useragent);

	return htmlspecialchars_uni($info['browser'])." ".htmlspecialchars_uni($info['version'])." on ".htmlspecialchars_uni($info['platform']);
}