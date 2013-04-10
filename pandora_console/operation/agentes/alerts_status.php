<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2009 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

global $config;

// Login check
check_login ();

if (is_ajax()) {
	require_once("include/functions_reporting.php");
	
	$get_alert_fired = get_parameter("get_alert_fired", 0);
	
	if ($get_alert_fired) {
		// Calculate alerts fired 
		$data_reporting = reporting_get_group_stats();
		echo $data_reporting['monitor_alerts_fired'];
	}
	
	return;
}

require_once ("include/functions_agents.php");
require_once ('operation/agentes/alerts_status.functions.php');
require_once ('include/functions_users.php');

$isFunctionPolicies = enterprise_include_once ('include/functions_policies.php');

$filter = get_parameter ("filter", "all_enabled");
$filter_standby = get_parameter ("filter_standby", "all");
$offset_simple = (int) get_parameter_get ("offset_simple", 0);
$id_group = (int) get_parameter ("ag_group", 0); //0 is the All group (selects all groups)
$free_search = get_parameter("free_search", '');

$sec2 = get_parameter_get ('sec2');
$sec2 = safe_url_extraclean ($sec2);

$sec = get_parameter_get ('sec');
$sec = safe_url_extraclean ($sec);

$flag_alert = (bool) get_parameter ('force_execution', 0);
$alert_validate = (bool) get_parameter ('alert_validate', 0);
$tab = get_parameter_get ("tab", null);

$url = 'index.php?sec='.$sec.'&sec2='.$sec2.'&refr='.$config["refr"].'&filter='.$filter.'&filter_standby='.$filter_standby.'&ag_group='.$id_group;

if ($flag_alert == 1 && check_acl($config['id_user'], $id_group, "AW")) {
	forceExecution($id_group);
}

$idAgent = get_parameter_get('id_agente', 0);

// Show alerts for specific agent
if ($idAgent != 0) {
	$url = $url . '&id_agente=' . $idAgent;
	
	$id_group = agents_get_agent_group ($idAgent);
	
	$is_extra = enterprise_hook('policies_is_agent_extra_policy',
		array($id_agente));
	
	if($is_extra === ENTERPRISE_NOT_HOOK) {
		$is_extra = false;
	}
	
	if (!check_acl ($config["id_user"], $id_group, "AR") && !$is_extra) {
		db_pandora_audit("ACL Violation","Trying to access alert view");
		require ("general/noaccess.php");
		exit;
	}
	
	$agents = array($idAgent);
	$idGroup = false;
	
	$print_agent = false;
} 
else {
	if (!check_acl ($config["id_user"], 0, "AR")) {
		db_pandora_audit("ACL Violation","Trying to access alert view");
		require ("general/noaccess.php");
		return;
	}
	
	$agents = array_keys(agents_get_group_agents(array_keys(users_get_groups($config["id_user"], 'AR', false))));
	
	$idGroup = $id_group;
	
	$print_agent = true;
	
	ui_print_page_header (__('Alert detail'), "images/op_alerts.png", false, "alert_validation");
}

if ($alert_validate) {
	if (check_acl ($config["id_user"], $id_group, "AW") == 0) {
		echo '<h3 class="error">'.__('Insufficient permissions to validate alerts').'</h3>';
	}
	else {
		validateAlert();
	}
}

if ($free_search != '') {
	switch ($config["dbtype"]) {
		case "mysql":
			$whereAlertSimple = 'AND (' .
				'id_alert_template IN (SELECT id FROM talert_templates WHERE name LIKE "%' . $free_search . '%") OR ' .
				'id_alert_template IN (SELECT id FROM talert_templates WHERE id_alert_action IN (SELECT id FROM talert_actions WHERE name LIKE "%' . $free_search . '%")) OR ' .
				'talert_template_modules.id IN (SELECT id_alert_template_module FROM talert_template_module_actions WHERE id_alert_action IN (SELECT id FROM talert_actions WHERE name LIKE "%' . $free_search . '%")) OR ' .
				'id_agent_module IN (SELECT id_agente_modulo FROM tagente_modulo WHERE nombre LIKE "%' . $free_search . '%") OR ' .
				'id_agent_module IN (SELECT id_agente_modulo FROM tagente_modulo WHERE id_agente IN (SELECT id_agente FROM tagente WHERE nombre LIKE "%' . $free_search . '%"))' .
				')';
			
			break;
		case "postgresql":
		case "oracle":
			$whereAlertSimple = 'AND (' .
				'id_alert_template IN (SELECT id FROM talert_templates WHERE name LIKE \'%' . $free_search . '%\') OR ' .
				'id_alert_template IN (SELECT id FROM talert_templates WHERE id_alert_action IN (SELECT id FROM talert_actions WHERE name LIKE \'%' . $free_search . '%\')) OR ' .
				'talert_template_modules.id IN (SELECT id_alert_template_module FROM talert_template_module_actions WHERE id_alert_action IN (SELECT id FROM talert_actions WHERE name LIKE \'%' . $free_search . '%\')) OR ' .
				'id_agent_module IN (SELECT id_agente_modulo FROM tagente_modulo WHERE nombre LIKE \'%' . $free_search . '%\') OR ' .
				'id_agent_module IN (SELECT id_agente_modulo FROM tagente_modulo WHERE id_agente IN (SELECT id_agente FROM tagente WHERE nombre LIKE \'%' . $free_search . '%\'))' .
				')';
			
			break;
	}
}
else {
	$whereAlertSimple = '';
}

$sortField = get_parameter('sort_field');
$sort = get_parameter('sort', 'none');
$selected = 'border: 1px solid black;';
$selectAgentUp = '';
$selectAgentDown = '';
$selectModuleUp = '';
$selectModuleDown = '';
$selectTemplateUp = '';
$selectTemplateDown = '';
switch ($sortField) {
	case 'agent':
		switch ($sort) {
			case 'up':
				$selectAgentUp = $selected;
				$order = array('field' => 'agent_name', 'order' => 'ASC');
				break;
			case 'down':
				$selectAgentDown = $selected;
				$order = array('field' => 'agent_name', 'order' => 'DESC');
				break;
		}
		break;
	case 'module':
		switch ($sort) {
			case 'up':
				$selectModuleUp = $selected;
				$order = array('field' => 'agent_module_name',
					'order' => 'ASC');
				break;
			case 'down':
				$selectModuleDown = $selected;
				$order = array('field' => 'agent_module_name',
					'order' => 'DESC');
				break;
		}
		break;
	case 'template':
		switch ($sort) {
			case 'up':
				$selectTemplateUp = $selected;
				$order = array('field' => 'template_name',
					'order' => 'ASC');
				break;
			case 'down':
				$selectTemplateDown = $selected;
				$order = array('field' => 'template_name',
					'order' => 'DESC');
				break;
		}
		break;
	default:
		if ($print_agent) {
			$selectDisabledUp = '';
			$selectDisabledDown = '';
			$selectAgentUp = '';
			$selectAgentDown = '';
			$selectModuleUp = $selected;
			$selectModuleDown = '';
			$selectTemplateUp = '';
			$selectTemplateDown = '';
			$order = array('field' => 'agent_module_name',
				'order' => 'ASC');
		}
		else {
			$selectDisabledUp = '';
			$selectDisabledDown = '';
			$selectAgentUp = '';
			$selectAgentDown = '';
			$selectModuleUp = $selected;
			$selectModuleDown = '';
			$selectTemplateUp = '';
			$selectTemplateDown = '';
			$order = array('field' => 'agent_module_name',
				'order' => 'ASC');
		}
		break;
}


//Add checks for user ACL
$groups = users_get_groups($config["id_user"]);
$id_groups = array_keys($groups);

if (empty($id_groups)) {
	$whereAlertSimple .= ' AND (1 = 0) ';
}
else {
	$whereAlertSimple .= ' AND id_agent_module IN (
		SELECT tam.id_agente_modulo
		FROM tagente_modulo AS tam
		WHERE tam.id_agente IN (SELECT ta.id_agente
			FROM tagente AS ta
			WHERE ta.id_grupo IN (' . implode(',', $id_groups) . '))) ';
}


$alerts = array();
$options_simple = array('offset' => $offset_simple, 'limit' => $config['block_size'], 'order' => $order);

$filter_alert = array();
if($filter_standby == 'standby_on') {
	$filter_alert['disabled'] = $filter;
	$filter_alert['standby'] = '1';
}
else if($filter_standby == 'standby_off') {
	$filter_alert['disabled'] = $filter;
	$filter_alert['standby'] = '0';
}
else {
	$filter_alert['disabled'] = $filter;
}

$alerts['alerts_simple'] = agents_get_alerts_simple ($agents,
	$filter_alert, $options_simple, $whereAlertSimple, false, false, $idGroup);

$countAlertsSimple = agents_get_alerts_simple ($agents, $filter_alert,
	false, $whereAlertSimple, false, false, $idGroup, true);

if ($tab != null) {
	$url = $url.'&tab='.$tab;
}

// Filter form
if ($print_agent) {
	ui_toggle(printFormFilterAlert($id_group, $filter, $free_search, $url, $filter_standby, true),__('Alert control filter'), __('Toggle filter(s)'));
}

$table->width = '100%';
$table->class = "databox";

$table->size = array ();
$table->head = array ();
$table->align = array ();

if ($isFunctionPolicies !== ENTERPRISE_NOT_HOOK) {
	if ($print_agent) {
		$table->head[0] = "<span title='" . __('Policy') . "'>" . __('P.') . "</span>";
		$table->head[1] = "<span title='" . __('Standby') . "'>" . __('S.') . "</span>";
		$table->head[2] = "<span title='" . __('Force execution') . "'>" . __('F.') . "</span>";
		$table->head[3] = __('Agent') . ' ' .
			'<a href="' . $url . '&sort_field=agent&sort=up">' . html_print_image("images/sort_up.png", true, array("style" => $selectAgentUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=agent&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectAgentDown)) . '</a>';
		$table->head[4] = __('Module') . ' ' .
			'<a href="' . $url . '&sort_field=module&sort=up">' . html_print_image("images/sort_up.png", true, array("style" =>$selectModuleUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=module&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectModuleDown)) . '</a>';
		$table->head[5] = __('Template') . ' ' .
			'<a href="' . $url . '&sort_field=template&sort=up">' . html_print_image("images/sort_up.png", true, array("style" =>$selectTemplateUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=template&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectTemplateDown)) . '</a>';
		$table->head[6] = __('Action');
		$table->head[7] = __('Last fired');
		$table->head[8] = __('Status');
		if (check_acl ($config["id_user"], $id_group, "LW") || check_acl ($config["id_user"], $id_group, "LM")) {
			$table->head[9] = __('Validate');
		}
		$table->align[8] = 'center';
		$table->align[9] = 'center';
	}
	else {
		
		$table->head[0] = "<span title='" . __('Policy') . "'>" . __('P.') . "</span>";
		$table->head[1] = "<span title='" . __('Standby') . "'>" . __('S.') . "</span>";
		$table->head[2] = "<span title='" . __('Force execution') . "'>" . __('F.') . "</span>";
		$table->head[3] = __('Module') . ' ' .
			'<a href="' . $url . '&sort_field=module&sort=up">' . html_print_image("images/sort_up.png", true, array("style" => $selectModuleUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=module&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectModuleDown)) . '</a>';
		$table->head[4] = __('Template') . ' ' .
			'<a href="' . $url . '&sort_field=template&sort=up">' . html_print_image("images/sort_up.png", true, array("style" => $selectTemplateUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=template&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectTemplateDown)) . '</a>';
		$table->head[5] = __('Action');
		$table->head[6] = __('Last fired');
		$table->head[7] = __('Status');
		if (check_acl ($config["id_user"], $id_group, "LW") || check_acl ($config["id_user"], $id_group, "LM")) {
			$table->head[8] = __('Validate');
		}
		$table->align[7] = 'center';
		$table->align[8] = 'center';
	}
}
else
{
	if ($print_agent) {
		
		$table->head[0] = "<span title='" . __('Standby') . "'>" . __('S.') . "</span>";
		$table->head[1] = "<span title='" . __('Force execution') . "'>" . __('F.') . "</span>";
		$table->head[2] = __('Agent') . ' ' .
			'<a href="' . $url . '&sort_field=agent&sort=up">'. html_print_image("images/sort_up.png", true, array("style" => $selectAgentUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=agent&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectAgentDown)) . '</a>';
		$table->head[3] = __('Module') . ' ' .
			'<a href="' . $url . '&sort_field=module&sort=up">' . html_print_image("images/sort_up.png", true, array("style" => $selectModuleUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=module&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectModuleDown)) . '</a>';
		$table->head[4] = __('Template') . ' ' .
			'<a href="' . $url . '&sort_field=template&sort=up">' . html_print_image("images/sort_up.png", true, array("style" => $selectTemplateUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=template&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectTemplateDown)) . '</a>';
		$table->head[5] = __('Action');
		$table->head[6] = __('Last fired');
		$table->head[7] = __('Status');
		if (check_acl ($config["id_user"], $id_group, "LW") || check_acl ($config["id_user"], $id_group, "LM")) {
			$table->head[8] = __('Validate');
		}
		$table->align[7] = 'center';
		$table->align[8] = 'center';
	}
	else {
		$table->head[0] = "<span title='" . __('Standby') . "'>" . __('S.') . "</span>";
		$table->head[1] = "<span title='" . __('Force execution') . "'>" . __('F.') . "</span>";
		$table->head[2] = __('Module') . ' ' .
			'<a href="' . $url . '&sort_field=module&sort=up">' . html_print_image("images/sort_up.png", true, array("style" => $selectModuleUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=module&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectModuleDown)) . '</a>';
		$table->head[3] = __('Template') . ' ' .
			'<a href="' . $url . '&sort_field=template&sort=up">' . html_print_image("images/sort_up.png", true, array("style" => $selectTemplateUp)) . '</a>' .
			'<a href="' . $url . '&sort_field=template&sort=down">' . html_print_image("images/sort_down.png", true, array("style" => $selectTemplateDown)) . '</a>';
		$table->head[4] = __('Action');
		$table->head[5] = __('Last fired');
		$table->head[6] = __('Status');
		if (check_acl ($config["id_user"], $id_group, "LW") || check_acl ($config["id_user"], $id_group, "LM")) {
			$table->head[7] = __('Validate');
		}
		$table->align[6] = 'center';
		$table->align[7] = 'center';
	}
}

$table->data = array ();

$rowPair = true;
$iterator = 0;
foreach ($alerts['alerts_simple'] as $alert) {
	if ($rowPair)
		$table->rowclass[$iterator] = 'rowPair';
	else
		$table->rowclass[$iterator] = 'rowOdd';
	$rowPair = !$rowPair;
	
	array_push ($table->data, ui_format_alert_row ($alert, $print_agent, $url, 'font-size: 7pt;'));
}

echo '<form method="post" action="'.$url.'">';

if (!empty ($table->data)) {
	ui_pagination ($countAlertsSimple, $url,  $offset_simple, 0, false, 'offset_simple');
	html_print_table ($table);
}
else {
	echo '<div class="nf">'.__('No alerts found').'</div>';
}

if (check_acl ($config["id_user"], $id_group, "AW") || check_acl ($config["id_user"], $id_group, "AM")) {
	if (count($alerts['alerts_simple']) > 0) {
		echo '<div class="action-buttons" style="width: '.$table->width.';">';
		html_print_submit_button (__('Validate'), 'alert_validate', false, 'class="sub upd"', false);
		echo '</div>';
	}
}

echo '</form>';

ui_require_css_file('cluetip');
ui_require_jquery_file('cluetip');
?>

<script type="text/javascript">
$(document).ready (function () {
	$("a.template_details").cluetip ({
		arrows: true,
		attribute: 'href',
		cluetipClass: 'default'
	}).click (function () {
		return false;
	});
});
</script>
