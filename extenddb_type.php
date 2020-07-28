<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/
include(dirname(__FILE__).'/../../include/global.php');

$extenddb_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate')
);

set_default_action('display_type_db');

switch (get_request_var('action')) {
	case 'display_type_db':
		top_header();
		display_type_db();
		bottom_footer();
		break;
		
	case 'edit_type':
		top_header();
		edit_type_db();
		bottom_footer();
		break;
					
	case 'actions':
		extenddb_form_actions();
		break;

	case 'save':
		extenddb_type_form_save();
		break;
}

function display_type_db() {
    global $config, $item_rows, $extenddb_actions;
// snmp_SysObjectId, oid_model, oid_sn, model
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'model' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		)
	);

	validate_store_request_vars($filters, 'sess_extenddb_display_db');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}
		$refresh['seconds'] = '300';
		$refresh['page']    = 'extenddb_type.php?action=display_type_db&header=false';
		$refresh['logout']  = 'false';

		set_page_refresh($refresh);

	?>
	<script type="text/javascript">

	function applyFilter() {
		strURL  = 'extenddb_type.php?action=display_type_db';
		strURL += '&rows=' + $('#rows').val();
		strURL += '&header=false';
		if ($('#model') ) {
			strURL += '&model=' + $('#model').val();
		}
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'extenddb_type.php?action=display_type_db&clear=1&header=false';
		loadPageNoHeader(strURL);
	}
	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_display_db').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
		</script>
		<?php
		html_start_box(__('ExtendDB Device Type'), '100%', '', '3', 'center', 'extenddb_type.php?action=edit_type');
		?>
		<tr class='even noprint'>
			<td>
			<form id='form_display_db' action='extenddb_type.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Model');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='model' size='25' value='<?php print html_escape_request_var('model');?>'>
						</td>
						<td>
							<?php print __('Rows');?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							</span>
						</td>
					</tr>
				</table>
				<input type='hidden' name='action' value='form_display_db'>
			</form>
			</td>
		</tr>
	<?php
	html_end_box();

/* filter by search string */
	$sql_where = '';

	if (get_request_var('model') != '') {
		$sql_where = ' WHERE model LIKE ' . db_qstr('%' . get_request_var('model') . '%');
	}
    // how many OID we have
    $sql_total_row = "SELECT count(distinct(snmp_SysObjectId))
 		FROM plugin_extenddb_model".
 		$sql_where;

    $total_rows = db_fetch_cell( $sql_total_row);
	/* if the number of rows is -1, set it to the default */
	if (get_request_var("rows") == "-1") {
		$per_row = read_config_option('num_rows_table'); //num_rows_device');
	}else{
		$per_row = get_request_var('rows');
	}
	$page = ($per_row*(get_request_var('page')-1));
	$sql_limit = $page . ',' . $per_row;

    $sql_query = "SELECT *
 	    FROM plugin_extenddb_model
 	    $sql_where
 	    ORDER BY snmp_SysObjectId 
 	    LIMIT " . $sql_limit;
	
    $result = db_fetch_assoc($sql_query);

/* generate page list */
	$nav = html_nav_bar('extenddb_type.php?action=display_type_db&model=' . get_request_var('model'), MAX_DISPLAY_PAGES, get_request_var('page'), $per_row, $total_rows);

	form_start('extenddb_type.php');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');
	
	html_header_checkbox(array(__('Model'), __('SysObjectId'), __('SNMP OID model'), __('SNMP OID Serial Number')) );

	if (!empty($result)) {
		// $page contain the start value, $row the number to display
		foreach($result as $item) {

			$model = filter_value($item['model'], get_request_var('model'));
			form_alternate_row('line' . $item['id'], false);

				print '<td><a href="' . html_escape('extenddb_type.php?action=edit_type&id=' . 
				$item['id']) . '">' . $item['model'] . '</a>'.'</td>';

				form_selectable_cell($item['snmp_SysObjectId'], $item['snmp_SysObjectId']);
				form_selectable_cell($item['oid_model'], $item['oid_model']);
				form_selectable_cell($item['oid_sn'], $item['oid_sn']);
				
				form_checkbox_cell($item['model'], $item['id']);
			form_end_row();
		}
	}

	html_end_box(false);
	if (!empty($result)) {
		print $nav;
	}

	form_hidden_box('action_receivers', '1', '');

	draw_actions_dropdown($extenddb_actions);

	form_end();
}

function extenddb_form_actions() {
	global $extenddb_actions;
	if (isset_request_var('selected_items')) {
		if (isset_request_var('action_receivers')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					db_execute('DELETE FROM plugin_extenddb_model WHERE id IN (' . implode(',' ,$selected_items) . ')');
					header('Location: extenddb_type.php?header=false');
				} elseif (get_nfilter_request_var('drp_action') == '2') { // duplicate
					if( count($selected_items) > 1 ){
						display_custom_error_message( 'Only one Model Type can be duplicated at time' );
						header('Location: extenddb_type.php?header=false');
					}
					else {
						$selected_item = implode(',' ,$selected_items);
						$sql_query = "SELECT * from plugin_extenddb_model WHERE id='".$selected_item."'";
						$item = db_fetch_row_prepared($sql_query);
						extdb_log('query:' .$sql_query);
						$edit_type_db['model'] = $item['model'];
						$edit_type_db['snmp_SysObjectId'] = $item['snmp_SysObjectId'];
						$edit_type_db['oid_model'] = $item['oid_model'];
						$edit_type_db['oid_sn'] = $item['oid_sn'];
						
						edit_type_db($edit_type_db);
					}
				}
				exit;

			}
		}
	} else {
		if (isset_request_var('action_receivers')) {
			$selected_items = array();
			$list = '';
			foreach($_POST as $key => $value) {
				if (strstr($key, 'chk_')) {
					/* grep type's id */
					$id = substr($key, 4);
					/* ================= input validation ================= */
					input_validate_input_number($id);
					/* ==================================================== */
					$list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT model FROM plugin_extenddb_model WHERE id = ?', array($id))) . '</li>';
					$selected_items[] = $id;
				}
			}

			top_header();

			form_start('extenddb_type.php');

			html_start_box($extenddb_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

			if (cacti_sizeof($selected_items)) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					$msg = __n('Click \'Continue\' to delete the following Model Type', 'Click \'Continue\' to delete following Model Type', cacti_sizeof($selected_items));
				} elseif (get_nfilter_request_var('drp_action') == '2') { // duplicate
					$msg = __n('Click \'Continue\' to duplicate the following Model Type', 'Click \'Continue\' to duplicate following Model Type', cacti_sizeof($selected_items));
				}

				print "<tr>
					<td class='textArea'>
						<p>$msg</p>
						<div class='itemlist'><ul>$list</ul></div>
					</td>
				</tr>";

				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'><input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('%s Model Type', $extenddb_actions[get_nfilter_request_var('drp_action')]) . "'>";
			} else {
				raise_message(40);
				header('Location: extenddb_type.php?header=false');
				exit;
			}

			print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='action_receivers' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($selected_items) ? serialize($selected_items) : '') . "'>
				<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
				$save_html
				</td>
			</tr>\n";

			html_end_box();

			form_end();

			bottom_footer();
		}
	}
}

function edit_type_db($extdb_type=null) {
// snmp_SysObjectId, oid_model, oid_sn, model
	global $config;

	$fields_extdb_edit = array(
		'model' => array(
			'method' => 'textbox',
			'friendly_name' => __('Model'),
			'description' => __('Exact model réference (has to match th SNMP OID model answer).'),
			'value' => '|arg1:model|',
			'max_length' => '64',
			'size' => 64
			),
		'snmp_SysObjectId' => array(
			'method' => 'textbox',
			'friendly_name' => __('SysObjectId'),
			'description' => __('Cisco SysObjectID.'),
			'value' => '|arg1:snmp_SysObjectId|',
			'max_length' => '64',
			'size' => 64
			),
		'oid_model' => array(
			'method' => 'textbox',
			'friendly_name' => __('SNMP OID model'),
			'description' => __('SNMP OID to get the model type.'),
			'value' => '|arg1:oid_model|',
			'max_length' => '64',
			'size' => 64
			),
		'oid_sn' => array(
			'method' => 'textbox',
			'friendly_name' => __('SNMP OID Serial Number'),
			'description' => __('SNMP OID to get the serial number.'),
			'value' => '|arg1:oid_sn|',
			'max_length' => '64',
			'size' => 64
		),
		'id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
		)
	);
	
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$id	= (isset_request_var('id') ? get_request_var('id') : '0');

	if ($id) {
		$extdb_type = db_fetch_row_prepared('SELECT * FROM plugin_extenddb_model WHERE id = ?', array($id));
		$header_label = __esc('ExtendDB Device type [edit: %s - %s]', $extdb_type['model'], $extdb_type['snmp_SysObjectId']);
	} else {
		$header_label = __('ExtendDB Device type [new]');
	}

	form_start('extenddb_type.php');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_extdb_edit, (isset($extdb_type) ? $extdb_type : array()))
		)
	);

	html_end_box(true, true);

	form_save_button('extenddb_type.php', 'return');
}

function extenddb_type_form_save() {
	$save['id']					= get_request_var('id');
	$save['model']				= form_input_validate(trim(get_nfilter_request_var('model')), 'model', '', false, 3);
	$save['snmp_SysObjectId']	= form_input_validate(trim(get_nfilter_request_var('snmp_SysObjectId')), 'snmp_SysObjectId', '', false, 3);
	$save['oid_model']			= form_input_validate(trim(get_nfilter_request_var('oid_model')), 'oid_model', '', false, 3);
	$save['oid_sn']				= form_input_validate(get_nfilter_request_var('oid_sn'), 'oid_sn', '', false, 3);


	$extenddb_model_id = 0;
	if (!is_error_message()) {
		$extenddb_model_id = sql_save($save, 'plugin_extenddb_model');
		raise_message( ($extenddb_model_id)? 1 : 2 );
	}
//	header('Location: extenddb_type.php?action=edit_type&header=false&id=' . (empty($extenddb_model_id) ? get_nfilter_request_var('id') : $extenddb_model_id) );
	header('Location: extenddb_type.php?header=false' );

}

?>