<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id: group_promotions.php 2932 2007-03-10 05:48:55Z chris $
 */

$page->add_breadcrumb_item("User Email Log", "index.php?".SID."&amp;module=tools/maillogs");

if($mybb->input['action'] == "view")
{
	$query = $db->simple_select("maillogs", "*", "mid='".intval($mybb->input['mid'])."'");
	$log = $db->fetch_array($query);

	if(!$log['mid'])
	{
		exit;
	}

	$log['toemail'] = htmlspecialchars_uni($log['toemail']);
	$log['fromemail'] = htmlspecialchars_uni($log['fromemail']);
	$log['subject'] = htmlspecialchars_uni($log['subject']);
	$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);
	$log['message'] = nl2br(htmlspecialchars_uni($log['message']));

	?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head profile="http://gmpg.org/xfn/1">
	<title><?php echo "User Email Log Viewer"; ?></title>
	<link rel="stylesheet" href="styles/<?php echo $page->style; ?>/main.css" type="text/css" />
	<link rel="stylesheet" href="styles/<?php echo $page->style; ?>/popup.css" type="text/css" />
</head>
<body id="popup">
	<div id="popup_container">
	<div class="popup_title"><a href="#" onclick="window.close();" class="close_link">Close Window</a><?php echo "User Email Log Viewer"; ?></div>

	<div id="content">
	<?php
	$table = new Table();

	$table->construct_cell("To:");
	$table->construct_cell("<a href=\"mailto:{$log['toemail']}\">{$log['toemail']}</a>");
	$table->construct_row();

	$table->construct_cell("From:");
	$table->construct_cell("<a href=\"mailto:{$log['fromemail']}\">{$log['fromemail']}</a>");
	$table->construct_row();

	$table->construct_cell("IP Address:");
	$table->construct_cell($log['ipaddress']);
	$table->construct_row();

	$table->construct_cell("Subject:");
	$table->construct_cell($log['subject']);
	$table->construct_row();

	$table->construct_cell("Date:");
	$table->construct_cell($log['dateline']);
	$table->construct_row();

	$table->construct_cell($log['message'], array("colspan" => 2));
	$table->construct_row();

	$table->output("Email");

	?>
	</div>
</div>
</body>
</html>
	<?php
}

if(!$mybb->input['action'])
{
	$per_page = 20;

	if($mybb->input['page'] && $mybb->input['page'] > 1)
	{
		$mybb->input['page'] = intval($mybb->input['page']);
		$start = ($mybb->input['page']*$per_page)-$per_page;
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}

	$additional_criteria = array();

	// Filter form was submitted - play around with the values
	if($mybb->request_method == "post")
	{
		if($mybb->input['from_type'] == "user")
		{
			$mybb->input['fromname'] = $mybb->input['from_value'];
		}
		else if($mybb->input['from_type'] == "email")
		{
			$mybb->input['fromemail'] = $mybb->input['from_value'];
		}

		if($mybb->input['to_type'] == "user")
		{
			$mybb->input['toname'] = $mybb->input['to_value'];
		}
		else if($mybb->input['to_type'] == "email")
		{
			$mybb->input['toemail'] = $mybb->input['to_value'];
		}
	}

	// Begin criteria filtering
	if($mybb->input['subject'])
	{
		$additional_sql_criteria .= " AND l.subject LIKE '%".$db->escape_string($mybb->input['subject'])."%'";
		$additional_criteria[] = "subject=".htmlspecialchars_uni($mybb->input['subject']);
	}

	if($mybb->input['fromuid'])
	{
		$query = $db->simple_select("users", "uid, username", "uid='".intval($mybb->input['fromuid'])."'");
		$user = $db->fetch_array($query);
		$from_filter = $user['username'];
		$additional_sql_criteria .= " AND l.fromuid='".intval($mybb->input['fromuid'])."'";
		$additional_criteria[] = "fromuid=".intval($mybb->input['fromuid']);
	}
	else if($mybb->input['fromname'])
	{
		$query = $db->simple_select("users", "uid, username", "LOWER(username)='".my_strtolower($mybb->input['fromname'])."'");
		$user = $db->fetch_array($query);
		$from_filter = $user['username'];

		if(!$user['uid'])
		{
			flash_message('The username you entered does not exist.', 'error');
			admin_redirect("index.php?".SID."&module=tools/maillogs");
		}
		$additional_sql_criteria .= "AND l.fromuid='{$user['uid']}'";
		$additional_criteria = "fromuid={$user['uid']}";
	}

	if($mybb->input['fromemail'])
	{
		$additional_sql_criteria .= " AND l.fromemail LIKE '%".$db->escape_string($mybb->input['fromemail'])."%'";
		$additional_criteria[] = "fromemail=".urlencode($mybb->input['fromemail']);
		$from_filter = $mybb->input['fromemail'];
	}

	if($mybb->input['touid'])
	{
		$query = $db->simple_select("users", "uid, username", "uid='".intval($mybb->input['touid'])."'");
		$user = $db->fetch_array($query);
		$to_filter = $user['username'];
		$additional_sql_criteria .= " AND l.touid='".intval($mybb->input['touid'])."'";
		$additional_criteria[] = "touid=".intval($mybb->input['touid']);
	}
	else if($mybb->input['toname'])
	{
		$query = $db->simple_select("users", "uid, username", "LOWER(username)='".my_strtolower($mybb->input['toname'])."'");
		$user = $db->fetch_array($query);
		$to_filter = $user['username'];

		if(!$user['uid'])
		{
			flash_message('The username you entered does not exist.', 'error');
			admin_redirect("index.php?".SID."&module=tools/maillogs");
		}
		$additional_sql_criteria .= "AND l.touid='{$user['uid']}'";
		$additional_criteria = "touid={$user['uid']}";
	}

	if($mybb->input['toemail'])
	{
		$additional_sql_criteria .= " AND l.toemail LIKE '%".$db->escape_string($mybb->input['toemail'])."%'";
		$additional_criteria[] = "toemail=".urlencode($mybb->input['toemail']);
		$to_filter = $mybb->input['toemail'];
	}

	$additional_criteria = implode("&amp;", $additional_criteria);	

	$page->output_header("User Email Log");
	
	$sub_tabs['maillogs'] = array(
		'title' => "User Email Log",
		'description' => "All emails sent from one member to another member as well as emails sent by the 'Send Thread to a Friend' feature are logged and shown below. Here you can identify potential abusers of this functionality."
	);
	$sub_tabs['prune_maillogs'] = array(
		'title' => "Prune User Email Log",
		'link' => "index.php?".SID."&amp;module=tools/maillogs&amp;action=prune"
	);

	$page->output_nav_tabs($sub_tabs, 'maillogs');

	$table = new Table;
	$table->construct_header("Subject", array("colspan" => 2));
	$table->construct_header("From", array("class" => "align_center", "width" => "20%"));
	$table->construct_header("To", array("class" => "align_center", "width" => "20%"));
	$table->construct_header("Date Sent", array("class" => "align_center", "width" => "20%"));

	$query = $db->query("
		SELECT l.*, r.username AS to_username, f.username AS from_username, t.subject AS thread_subject
		FROM ".TABLE_PREFIX."maillogs l
		LEFT JOIN ".TABLE_PREFIX."users r ON (r.uid=l.touid)
		LEFT JOIN ".TABLE_PREFIX."users f ON (f.uid=l.fromuid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		WHERE 1=1 {$additional_sql_criteria}
		ORDER BY dateline DESC
		LIMIT {$start}, {$per_page}
	");
	while($log = $db->fetch_array($query))
	{
		$log['subject'] = htmlspecialchars_uni($log['subject']);
		$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);
		if($log['tid'] > 0)
		{
			if($log['thread_subject'])
			{
				$log['thread_subject'] = htmlspecialchars($log['thread_subject']);
				$thread_link = "<a href=\"../".get_thread_link($log['tid'])."\">".$log['thread_subject']."</a>";
			}
			else
			{
				$thread_link = "Deleted";
			}
			$table->construct_cell("<img src=\"styles/{$page->style}/images/icons/maillogs_thread.gif\" title=\"Sent using the Send Thread to Friend feature\" />", array("width" => 1));
			$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?".SID."&amp;module=tools/maillogs&action=view&mid={$log['mid']}', 'log_entry', 450, 450);\">{$log['subject']}</a><br /><small>Thread: {$thread_link}</small>");
			$find_from = "<div class=\"float_right\"><a href=\"index.php?".SID."&amp;module=tools/maillogs&amp;fromuid={$log['fromuid']}\"><img src=\"styles/{$page->style}/images/icons/find.gif\" title=\"Find all emails sent by this user\" alt=\"Find\" /></a></div>";
			if(!$log['from_username'])
			{
				$table->construct_cell("{$find_from}<div>Deleted User</div>");
			}
			else
			{
				$table->construct_cell("{$find_from}<div><a href=\"../".get_profile_link($log['fromuid'])."\">{$log['from_username']}</a></div>");
			}
			$log['toemail'] = htmlspecialchars_uni($log['toemail']);
			$table->construct_cell($log['toemail']);
			$table->construct_cell($log['dateline'], array("class" => "align_center"));
		}
		else
		{
			$table->construct_cell("<img src=\"styles/{$page->style}/images/icons/maillogs_user.gif\" title=\"Email sent to user\" />", array("width" => 1));
			$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?".SID."&amp;module=tools/maillogs&action=view&mid={$log['mid']}', 'log_entry', 450, 450);\">{$log['subject']}</a>");
			$find_from = "<div class=\"float_right\"><a href=\"index.php?".SID."&amp;module=tools/maillogs&amp;fromuid={$log['fromuid']}\"><img src=\"styles/{$page->style}/images/icons/find.gif\" title=\"Find all emails sent by this user\" alt=\"Find\" /></a></div>";
			if(!$log['from_username'])
			{
				$table->construct_cell("{$find_from}<div>Deleted User</div>");
			}
			else
			{
				$table->construct_cell("{$find_from}<div><a href=\"../".get_profile_link($log['fromuid'])."\">{$log['from_username']}</a></div>");
			}
			$find_to = "<div class=\"float_right\"><a href=\"index.php?".SID."&amp;module=tools/maillogs&amp;fromuid={$log['fromuid']}\"><img src=\"styles/{$page->style}/images/icons/find.gif\" title=\"Find all emails received by this user\" alt=\"Find\" /></a></div>";
			if(!$log['to_username'])
			{
				$table->construct_cell("{$find_to}<div>Deleted User</div>");
			}
			else
			{
				$table->construct_cell("{$find_to}<div><a href=\"../".get_profile_link($log['touid'])."\">{$log['to_username']}</a></div>");
			}
			$table->construct_cell($log['dateline'], array("class" => "align_center"));
		}
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no log entries with the selected criteria.", array("colspan" => "5"));
		$table->construct_row();
	}
	
	$table->output("User Email Log");
	
	$query = $db->simple_select("maillogs l", "COUNT(l.mid) as logs", "1=1 {$additional_sql_criteria}");
	$total_rows = $db->fetch_field($query, "logs");

	echo "<br />".draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?".SID."&amp;module=tools/maillogs&amp;page={page}{$additional_criteria}");
	
	$form = new Form("index.php?".SID."&amp;module=tools/maillogs", "post");
	$form_container = new FormContainer("Filter User Email Log");
	$user_email = array(
		"user" => "Username is",
		"email" => "Email Address contains"
	);
	$form_container->output_row("Subject contains", "", $form->generate_text_box('subject', $mybb->input['subject'], array('id' => 'subject')), 'subject');	
	if($from_username)
	{
		$from_type = "user";
	}
	else if($mybb->input['fromemail'])
	{
		$from_type = "email";
	}
	$form_container->output_row("From", "", $form->generate_select_box('from_type', $user_email, $from_type)." ".$form->generate_text_box('from_value', $from_filter, array('id' => 'from_value')), 'from_value');
	if($to_username)
	{
		$to_type = "user";
	}
	else if($mybb->input['toemail'])
	{
		$to_type = "email";
	}
	$form_container->output_row("To", "", $form->generate_select_box('to_type', $user_email, $to_type)." ".$form->generate_text_box('to_value', $to_filter, array('id' => 'to_value')), 'to_value');
	$form_container->end();
	$buttons[] = $form->generate_submit_button("Filter User Email Log");
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}