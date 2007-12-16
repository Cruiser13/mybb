<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->admin_logs, "index.php?".SID."&amp;module=tools/adminlog");

$sub_tabs['admin_logs'] = array(
	'title' => $lang->admin_logs,
	'link' => "index.php?".SID."&amp;module=tools/adminlog",
	'description' => $lang->admin_logs_desc
);
$sub_tabs['prune_admin_logs'] = array(
	'title' => $lang->prune_admin_logs,
	'link' => "index.php?".SID."&amp;module=tools/adminlog&amp;action=prune",
	'description' => $lang->prune_admin_logs_desc
);

if($mybb->input['action'] == 'prune')
{
	if($config['log_pruning']['admin_logs'])
	{
		flash_message($lang->error_logs_automatically_pruned, 'error');
		admin_redirect("index.php?".SID."&module=tools/adminlog");
	}
	if(!is_super_admin($mybb->user['uid']))
	{
		flash_message($lang->cannot_perform_action_super_admin_general, 'error');
		admin_redirect("index.php?".SID."&module=tools/adminlog");
	}
	if($mybb->request_method == 'post')
	{
		$where = 'dateline < '.(time()-(intval($mybb->input['older_than'])*86400));
		
		// Searching for entries by a particular user
		if($mybb->input['uid'])
		{
			$where .= " AND uid='".intval($mybb->input['uid'])."'";
		}
		
		// Searching for entries in a specific module
		if($mybb->input['filter_module'])
		{
			$where .= " AND module='".$db->escape_string($mybb->input['filter_module'])."'";
		}
		
		$query = $db->delete_query("adminlog", $where);
		$num_deleted = $db->affected_rows();
		
		// Log admin action
		log_admin_action($mybb->input['older_than'], $mybb->input['uid'], $mybb->input['filter_module'], $num_deleted);

		flash_message($lang->success_pruned_admin_logs, 'success');
		admin_redirect("index.php?".SID."&module=tools/adminlog");
	}
	$page->add_breadcrumb_item($lang->prune_admin_logs, "index.php?".SID."&amp;module=tools/adminlog&amp;action=prune");
	$page->output_header($lang->prune_admin_logs);
	$page->output_nav_tabs($sub_tabs, 'prune_admin_logs');
	
	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';
	
	$user_options[''] = $lang->all_administrators;
	$user_options['0'] = '----------';
	
	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$user_options[$user['uid']] = $user['username'];
	}

	$module_options = array();
	$module_options[''] = $lang->all_modules;
	$module_options['0'] = '----------';
	$query = $db->query("
		SELECT DISTINCT l.module
		FROM ".TABLE_PREFIX."adminlog l
		ORDER BY l.module ASC
	");
	while($module = $db->fetch_array($query))
	{
		$module_options[$module['module']] = str_replace(' ', ' -> ', ucwords(str_replace('/', ' ', $module['module'])));
	}
	
	$sort_by = array(
		'dateline' => $lang->date,
		'username' => $lang->username,
		'forum' => $lang->forum_name,
		'thread' => $lang->thread_subject
	);
	
	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?".SID."&amp;module=tools/adminlog&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_administrator_logs);
	$form_container->output_row($lang->module, "", $form->generate_select_box('filter_module', $module_options, $mybb->input['filter_module'], array('id' => 'filter_module')), 'filter_module');	
	$form_container->output_row($lang->administrator, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');
	if(!$mybb->input['older_than'])
	{
		$mybb->input['older_than'] = '30';
	}
	$form_container->output_row($lang->date_range, "", 'Older than '.$form->generate_text_box('older_than', $mybb->input['older_than'], array('id' => 'older_than', 'style' => 'width: 30px')).' days', 'older_than');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->prune_administrator_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}
if(!$mybb->input['action'])
{
	$page->output_header($lang->admin_logs);
	$page->output_nav_tabs($sub_tabs, 'admin_logs');
	
	$perpage = intval($mybb->input['perpage']);
	if(!$perpage)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = '';

	// Searching for entries by a particular user
	if($mybb->input['uid'])
	{
		$where .= " AND l.uid='".intval($mybb->input['uid'])."'";
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->input['order'];
	if($order != 'asc')
	{
		$order = 'desc';
	}

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE 1=1 {$where}
	");
	$rescount = $db->fetch_field($query, "count");
	
	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$pagecnt = intval($mybb->input['page']);
	}

	$postcount = intval($rescount);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$pagecnt = $pages;
	}

	if($pagecnt > $pages)
	{
		$pagecnt = 1;
	}

	if($pagecnt)
	{
		$start = ($pagecnt-1) * $perpage;
	}
	else
	{
		$start = 0;
		$pagecnt = 1;
	}
	
	$table = new Table;
	$table->construct_header($lang->username, array('width' => '10%'));
	$table->construct_header($lang->date, array('class' => 'align_center', 'width' => '15%'));
	$table->construct_header($lang->information, array('class' => 'align_center', 'width' => '65%'));
	$table->construct_header($lang->ipaddress, array('class' => 'align_center', 'width' => '10%'));

	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE 1=1 {$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['dateline'] = date("jS M Y, G:i", $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		
		// Get detailed information from meta
		$data = unserialize($logitem['data']);
		$information = '';
		
		$module = explode('/', $logitem['module']);
		$action = $module[1];
		$module = preg_replace('#[^a-z0-9-_]#i', '', $module[0]);
		$page_file = MYBB_ADMIN_DIR."/modules/{$module}/module_meta.php";
		if(file_exists($page_file))
		{
			require_once $page_file;
			$function_name = $module.'_format_admin_log_data';
			if(function_exists($function_name))
			{
				$information = call_user_func($function_name, $action, $data);
			}
		}
		
		$table->construct_cell($logitem['profilelink']);
		$table->construct_cell($logitem['dateline'], array('class' => 'align_center'));
		$table->construct_cell($information);
		$table->construct_cell($logitem['ipaddress'], array('class' => 'align_center'));
		$table->construct_row();
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_adminlogs, array('colspan' => '4'));
		$table->construct_row();
	}
	
	$table->output($lang->admin_logs);
	
	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?".SID."&amp;module=tools/adminlog&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;fid={$mybb->input['fid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}
	
	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';
	
	$user_options[''] = $lang->all_administrators;
	$user_options['0'] = '----------';
	
	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$user_options[$user['uid']] = $user['username'];
	}

	$module_options = array();
	$module_options[''] = $lang->all_modules;
	$module_options['0'] = '----------';
	$query = $db->query("
		SELECT DISTINCT l.module
		FROM ".TABLE_PREFIX."adminlog l
		ORDER BY l.module ASC
	");
	while($module = $db->fetch_array($query))
	{
		$module_options[$module['module']] = str_replace(' ', ' -> ', ucwords(str_replace('/', ' ', $module['module'])));
	}
	
	$sort_by = array(
		'dateline' => $lang->date,
		'username' => $lang->username,
		'forum' => $lang->forum_name,
		'thread' => $lang->thread_subject
	);
	
	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?".SID."&amp;module=tools/adminlog", "post");
	$form_container = new FormContainer($lang->filter_administrator_logs);
	$form_container->output_row($lang->module, "", $form->generate_select_box('filter_module', $module_options, $mybb->input['filter_module'], array('id' => 'filter_module')), 'filter_module');	
	$form_container->output_row($lang->administrator, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');	
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');	
	$form_container->output_row($lang->results_per_page, "", $form->generate_text_box('perpage', $perpage, array('id' => 'perpage')), 'perpage');	

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_administrator_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}
?>