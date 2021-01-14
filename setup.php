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
include_once($config['base_path'] . '/plugins/extenddb/ssh2.php');
include_once($config['base_path'] . '/plugins/extenddb/telnet.php');

function plugin_extenddb_install () {
	api_plugin_register_hook('extenddb', 'config_settings', 'extenddb_config_settings', 'setup.php');
	api_plugin_register_hook('extenddb', 'config_form', 'extenddb_config_form', 'setup.php');
	api_plugin_register_hook('extenddb', 'api_device_new', 'extenddb_api_device_new', 'setup.php');
	api_plugin_register_hook('extenddb', 'utilities_action', 'extenddb_utilities_action', 'setup.php');
	api_plugin_register_hook('extenddb', 'utilities_list', 'extenddb_utilities_list', 'setup.php');

// Device action
    api_plugin_register_hook('extenddb', 'device_action_array', 'extenddb_device_action_array', 'setup.php');
    api_plugin_register_hook('extenddb', 'device_action_execute', 'extenddb_device_action_execute', 'setup.php');
    api_plugin_register_hook('extenddb', 'device_action_prepare', 'extenddb_device_action_prepare', 'setup.php');

}

function plugin_extenddb_uninstall () {
	// Do any extra Uninstall stuff here

	// Remove items from the settings table
	db_execute('ALTER TABLE host DROP COLUMN serial_no, DROP COLUMN type, DROP COLUMN isPhone');
}

function plugin_extenddb_check_config () {
	// Here we will check to ensure everything is configured
	extenddb_check_upgrade();

	return true;
}

function plugin_extenddb_upgrade () {
	// Here we will upgrade to the newest version
	extenddb_check_upgrade();
	return false;
}

function extenddb_check_upgrade() {
	global $config;

	$version = plugin_extenddb_version ();
	$current = $version['version'];
	$old     = db_fetch_cell('SELECT version
		FROM plugin_config
		WHERE directory="extenddb"');

	if ($current != $old) {

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='extenddb'");
		db_execute("UPDATE plugin_config SET 
			version='" . $version['version'] . "', 
			name='"    . $version['longname'] . "', 
			author='"  . $version['author'] . "', 
			webpage='" . $version['homepage'] . "' 
			WHERE directory='" . $version['name'] . "' ");

		if( $old < '1.1' ) {
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'serial_no', 'type' => 'char(50)', 'NULL' => true, 'default' => ''));
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'type', 'type' => 'char(50)', 'NULL' => true, 'default' => ''));
		}
		if( $old < '1.1.2' ) {
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'isPhone', 'type' => 'char(2)', 'NULL' => true, 'default' => ''));
		}

		if( $old < '1.2.3' ) {
// Device action
   	 		api_plugin_register_hook('extenddb', 'device_action_array', 'extenddb_device_action_array', 'setup.php');
    		api_plugin_register_hook('extenddb', 'device_action_execute', 'extenddb_device_action_execute', 'setup.php');
    		api_plugin_register_hook('extenddb', 'device_action_prepare', 'extenddb_device_action_prepare', 'setup.php');
		}
		if( $old < '1.3.2' ) {
			$data = array();
			$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'auto_increment'=>'');
			$data['columns'][] = array('name' => 'snmp_SysObjectId', 'type' => 'varchar(64)', 'NULL' => false );
			$data['columns'][] = array('name' => 'oid_model', 'type' => 'varchar(64)', 'NULL' => false );
			$data['columns'][] = array('name' => 'oid_sn', 'type' => 'varchar(64)', 'NULL' => false );
			$data['columns'][] = array('name' => 'model', 'type' => 'varchar(64)', 'NULL' => false );
 			$data['primary'] = 'id';
			$data['keys'][] = array('name' => 'snmp_SysObjectId', 'columns' => 'snmp_SysObjectId');
			$data['keys'][] = array('name' => 'oid_model', 'columns' => 'oid_model');
			$data['keys'][] = array('name' => 'model', 'columns' => 'model');
			$data['type'] = 'InnoDB';
			api_plugin_db_table_create('extenddb', 'plugin_extenddb_model', $data);
		}
	}
	fill_model_db(); // place where new device are added
}

function plugin_extenddb_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/extenddb/INFO', true);
	return $info['info'];
}

function extenddb_config_form () {
	global $fields_host_edit;
	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'notes') {
			$fields_host_edit3['serial_no'] = array(
				'method' => 'textbox',
				'friendly_name' => 'Serial No',
				'description' => 'Enter the serial number.',
				'max_length' => 50,
				'value' => '|arg1:serial_no|',
				'default' => '',
			);
			$fields_host_edit3['type'] = array(
				'friendly_name' => 'Type',
				'description' => 'This is the type of equipement.',
				'method' => 'textbox',
				'max_length' => 50,
				'value' => '|arg1:type|',
				'default' => '',
			);
			$fields_host_edit3['isPhone'] = array(
				'friendly_name' => 'isPhone',
				'description' => 'Is it a phone ?',
				'method' => 'checkbox',
				'value' => '|arg1:isPhone|',
				'default' => '',
			);
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function extenddb_utilities_list () {
	global $colors, $config;
	html_header(array("extenddb Plugin"), 4);
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_complete'>Complete Serial Number and Type.</a>
		</td>
		<td class="textArea">
			Complete Serial Number anb Type of all non filed device.
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_rebuild'>Recheck All Cisco Device.</a>
		</td>
		<td class="textArea">
			Build Serial Number anb Type of All Cisco Device.
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='<?php print $config['url_path'] . 'plugins/extenddb/'?>extenddb_type.php?action=display_type_db'>Edit the ExtendDB table.</a>
		</td>
		<td class="textArea">
			Change, add or remove a type entry on the ExtendDB table.
		</td>
	<?php
	form_end_row();
	form_alternate_row();
		print "<td class='nowrap' style='vertical-align:top;'> <a class='hyperLink' href='utilities.php?action=extenddb_count'>ExtendDB type count</a></td>\n";
		print "<td>Count the number of each device type.</td>\n";
	form_end_row();
	form_alternate_row();
		print "<td class='nowrap' style='vertical-align:top;'> <a class='hyperLink' href='utilities.php?action=extenddb_export_type_SN'>ExtendDB Export Type and SN</a></td>\n";
		print "<td>Export in CSV format, the type and SN of all active device.</td>\n";
	form_end_row();

}

function extenddb_utilities_action ($action) {
	global $item_rows;
	
	if ( $action == 'extenddb_complete' || $action == 'extenddb_rebuild' ){
		if ($action == 'extenddb_complete') {
	// get device list,  where serial number is empty, or type
			$dbquery = db_fetch_assoc("SELECT * FROM host 
			WHERE (serial_no is NULL OR type IS NULL OR serial_no = '' OR type = '')
			AND status = '3' AND disabled != 'on'
			AND snmp_sysDescr LIKE '%cisco%'
			ORDER BY id");
		// Upgrade the extenddb value
			if( $dbquery > 0 ) {
				foreach ($dbquery as $host) {
					update_sn_type( $host );
				}
			}
		} else if ($action == 'extenddb_rebuild') {
	// get device list,  where serial number is empty, or type
			$dbquery = db_fetch_assoc("SELECT  * FROM host 
			WHERE status = '3' AND disabled != 'on'
			AND snmp_sysDescr LIKE '%cisco%'
			ORDER BY id");
		// Upgrade the extenddb value
			if( $dbquery > 0 ) {
				foreach ($dbquery as $host) {
					update_sn_type( $host );
				}
			}
		}
		top_header();
		utilities();
		bottom_footer();
	} else if ($action == 'extenddb_export_type_SN') {
		data_export();
	} elseif ($action == 'extenddb_count') {
		top_header();

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
			'filter' => array(
				'filter' => FILTER_DEFAULT,
				'pageset' => true,
				'default' => ''
			),
			'sort_column' => array(
				'filter' => FILTER_CALLBACK,
				'default' => 'occurence',
				'options' => array('options' => 'sanitize_search_string')
			),
			'sort_direction' => array(
				'filter' => FILTER_CALLBACK,
				'default' => 'ASC',
				'options' => array('options' => 'sanitize_search_string')
			)
		);

		validate_store_request_vars($filters, 'sess_extenddbcount');
		/* ================= input validation ================= */

		if (get_request_var('rows') == '-1') {
			$rows = read_config_option('num_rows_table');
		} else {
			$rows = get_request_var('rows');
		}

		$refresh['seconds'] = '300';
		$refresh['page']    = 'utilities.php?action=extenddb_count&header=false';
		$refresh['logout']  = 'false';

		set_page_refresh($refresh);

		?>
		<script type="text/javascript">

		function applyFilter() {
			strURL  = 'utilities.php?action=extenddb_count';
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = urlPath+'utilities.php?action=extenddb_count&clear=1&header=false';
			loadPageNoHeader(strURL);
		}
		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#count_type').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		<?php
		html_start_box(__('Extenddb Device Type'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
			<td>
			<form id='count_type' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
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
				<input type='hidden' name='action' value='extenddb_count'>
			</form>
			</td>
		</tr>
		<?php
		html_end_box();

	// sql query: SELECT type,COUNT(1) as occurence FROM host where type LIKE "C9200" GROUP BY type ORDER BY occurence
		$sql_where = '';

	/* filter by search string */
		if (get_request_var('filter') != '') {
			$sql_where .= ' WHERE type LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
		}

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT(type)) FROM host". $sql_where);
		
		$sql_where .= ' GROUP BY type ';

		$extenddb_count_sql = "SELECT type,COUNT(1) as occurence FROM host 
			$sql_where 
			ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
			LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$extenddb_count = db_fetch_assoc($extenddb_count_sql);

	/* generate page list */
		$nav = html_nav_bar('utilities.php?action=extenddb_count&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Entries'), 'page', 'main');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(
		'type' => array(__('Device Type'), 'ASC'),
		'occurence' => array(__('Number of Occurence'), 'ASC'));

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=extenddb_count');

		if (cacti_sizeof($extenddb_count)) {
			foreach ($extenddb_count as $item) {
				if( empty($item['type']) ) $item['type'] = 'empty';
				form_alternate_row('line' . $item['type'], false);
				form_selectable_cell(filter_value($item['type'], get_request_var('filter'), 'utilities.php?action=extenddb_display&sort_column=description&model=' . $item['type']), $item['type']);
				form_selectable_cell(filter_value($item['occurence'], get_request_var('filter')), $item['occurence']);
				form_end_row();
			}
		}

		html_end_box();
		if (cacti_sizeof($extenddb_count)) {
			print $nav;
		}

		?>
		<script type='text/javascript'>
			$('.tooltip').tooltip({
				track: true,
				show: 250,
				hide: 250,
				position: { collision: "flipfit" },
				content: function() { return $(this).attr('title'); }
			});
		</script>
	<?php
	} elseif ($action == 'extenddb_display') {
		top_header();
// Show list of a specific type
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
			),
			'filter' => array(
				'filter' => FILTER_DEFAULT,
				'pageset' => true,
				'default' => ''
			),
			'sort_column' => array(
				'filter' => FILTER_CALLBACK,
				'default' => 'hostname',
				'options' => array('options' => 'sanitize_search_string')
			),
			'sort_direction' => array(
				'filter' => FILTER_CALLBACK,
				'default' => 'ASC',
				'options' => array('options' => 'sanitize_search_string')
			)
		);
		validate_store_request_vars($filters, 'sess_extenddbdisp');
		/* ================= input validation ================= */

		if (get_request_var('rows') == '-1') {
			$rows = read_config_option('num_rows_table');
		} else {
			$rows = get_request_var('rows');
		}
		if( get_request_var('model') == 'empty' ) set_request_var('model', '');

		$model = get_request_var('model');
		$refresh['seconds'] = '300';
		$refresh['page']    = 'utilities.php?action=extenddb_display&header=false&model='.$model;
		$refresh['logout']  = 'false';

		set_page_refresh($refresh);

		?>
		<script type="text/javascript">
		function applyFilter() {
			strURL  = 'utilities.php?action=extenddb_display';
			strURL += '&model=' +$('#model').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = urlPath+'utilities.php?action=extenddb_count&clear=1&header=false';
			loadPageNoHeader(strURL);
		}
		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#display_type').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		<?php
		html_start_box(__('Extenddb Device Type'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
		<id='model' value=<?php print (get_request_var('model'))?> >
			<td>
			<form id='display_type' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
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
				<input type='hidden' name='action' value='extenddb_display'>
			</form>
			</td>
		</tr>
		<?php
		html_end_box();

		$sql_where = '';

	/* filter by search string */
		$sql_where .= ' WHERE type LIKE ' . db_qstr('' . get_request_var('model') . '');

		$total_rows = db_fetch_cell("SELECT COUNT(*) FROM host ".$sql_where);
		
		$extenddb_display_sql = "SELECT id, hostname, description, serial_no  FROM host
			$sql_where
			ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
			LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$extenddb_display = db_fetch_assoc($extenddb_display_sql);
//SELECT id, hostname, description, serial_no FROM host WHERE type LIKE 'C9200L-24P-4X' ORDER BY description ASC LIMIT 50,50

	/* generate page list */
		$nav = html_nav_bar('utilities.php?action=extenddb_display&filter=' . get_request_var('filter').'&model='.get_request_var('model'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Entries'), 'page', 'main');

		print $nav;

		$display_text = array(
		'hostname' => array(__('Device Hostname'), 'ASC'),
		'description' => array(__('Device Description'), 'ASC'),
		'serial_no' => array(__('Device SerialNumber'), ''));

		html_start_box('', '100%', '', '3', 'center', '');

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=extenddb_display');


		if (cacti_sizeof($extenddb_display)) {
			foreach ($extenddb_display as $item) {
				form_alternate_row('line' . $item['hostname'], false);
				form_selectable_cell(filter_value($item['hostname'], get_request_var('filter'), 'host.php?action=edit&id=' . $item['id']), $item['id']);
				form_selectable_cell(filter_value($item['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $item['id']), $item['id']);
				form_selectable_cell(filter_value($item['serial_no'], get_request_var('filter')), $item['serial_no']);
				form_end_row();
			}
		}

		html_end_box();
		if (cacti_sizeof($extenddb_display)) {
			print $nav;
		}

		?>
		<script type='text/javascript'>
			$('.tooltip').tooltip({
				track: true,
				show: 250,
				hide: 250,
				position: { collision: "flipfit" },
				content: function() { return $(this).attr('title'); }
			});
		</script>
	<?php
	} 
	return $action;
}

function extenddb_api_device_new($hostrecord_array) {
extdb_log('Enter Extenddb' );
$snmpsysobjid = ".1.3.6.1.2.1.1.2.0"; // ObjectID
$snmpsysdescr = ".1.3.6.1.2.1.1.1.0"; // system description

	$host = db_fetch_row("SELECT * FROM host WHERE id=".$hostrecord_array['id']);

	// don't do it for disabled
	if ($host['disabled'] == 'on' ) {
extdb_log('Exit Extenddb Disabled');
		return $hostrecord_array;
	}
	
	if( empty($host['snmp_sysObjectID']) ) {
		// parse device to find snmp_sysObjectID: OID: .1.3.6.1.4.1.9.1.2134
		// on DB iso.3.6.1.4.1.9.1.2134'
		$host_data = cacti_snmp_get( $host['hostname'], $host['snmp_community'], $snmpsysobjid, 
		$host['snmp_version'], $host['snmp_username'], $host['snmp_password'], 
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
		$host['snmp_context'] );
		
		$regex = '~.[0-9].*\.([0-9].*)~';
		preg_match( $regex, $host_data, $result ); // extract the OID of the switch number from the snmp query
		$host['snmp_sysObjectID'] = 'iso.3.6.1.4.1.9.1.'.$result[1];
		$hostrecord_array['snmp_sysObjectID'] = $host['snmp_sysObjectID'];
		extdb_log('host_data:'.$host['snmp_sysObjectID']);
	
		$host['snmp_sysDescr'] = cacti_snmp_get( $host['hostname'], $host['snmp_community'], $snmpsysdescr, 
		$host['snmp_version'], $host['snmp_username'], $host['snmp_password'], 
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
		$host['snmp_context'] );
		$hostrecord_array['snmp_sysDescr'] = $host['snmp_sysDescr'];
		extdb_log('host_id:'.$host['snmp_sysDescr']);
	}
	
	// do it for Cisco type
	if( mb_stripos( $host['snmp_sysDescr'], 'cisco') === false ) {
extdb_log('Exit Extenddb' );
		return $host;
	}
	
	if (!isset_request_var('serial_no')) {
		$host['serial_no'] = form_input_validate(get_filter_request_var('serial_no'), 'serial_no', '', true, 3);
	} else {
		$host_extend_record['serial_no'] = get_SN( $host, $host['snmp_sysObjectID'] );
		$host['serial_no'] = form_input_validate($host_extend_record['serial_no'], 'serial_no', '', true, 3);
	}
	
	if (!isset_request_var('type'))
		$host['type'] = form_input_validate(get_filter_request_var('type'), 'type', '', true, 3);
	else
		$host['type'] = get_type( $host );

	if (isset_request_var('isPhone'))
		$host['isPhone'] = form_input_validate(get_filter_request_var('isPhone'), 'isPhone', '', true, 3);
	else
		$host['isPhone'] = form_input_validate('off', 'isPhone', '', true, 3);

	sql_save($host, 'host');

extdb_log('Exit Extenddb' );
	return $hostrecord_array;
}

function extenddb_config_settings () {
	global $tabs, $settings;
	$tabs["misc"] = "Misc";

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$tabs['misc'] = 'Misc';
	$temp = array(
		"extenddb_general_header" => array(
			"friendly_name" => "extenddb",
			"method" => "spacer",
			),
		'extenddb_log_debug' => array(
			'friendly_name' => 'Debug Log',
			'description' => 'Enable logging of debug messages during extenddb action',
			'method' => 'checkbox',
			'default' => 'off'
			),
	);
	
	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;
}

function extenddb_check_dependencies() {
	global $plugins, $config;

	return true;
}

function get_type( $hostrecord_array ) {
//snmp_SysObjectId, oid_model, oid_sn, model
	$sqlquery = "SELECT * FROM plugin_extenddb_model WHERE snmp_SysObjectId='".$hostrecord_array['snmp_sysObjectID']."'";

	$result = db_fetch_row($sqlquery);
	if( empty($result) ) {
		// use default value
		$oid_model = ".1.3.6.1.2.1.47.1.1.1.1.13.1001";
	} else {
		$oid_model =  $result['oid_model'];
	}
	$data_model = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $oid_model, 
	$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
	$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
	$hostrecord_array['snmp_context'] );

	if( empty($data_model) ) {
		$text = "Can t find model No for : " . $hostrecord_array['description'].'(oid:'.$hostrecord_array['snmp_sysObjectID'].') at: '. $oid_model;
		cacti_log( $text, false, "EXTENDDB" );
	}

	return $data_model;
}

function get_SN( $hostrecord_array, $SysObjId ){
	$snmp_stackinfo = "1.3.6.1.4.1.9.9.500.1.2.1.1.1"; // will return an array of switch number, the id last number of the OID
	$snmp_vssinfo = "1.3.6.1.4.1.9.9.388.1.2.2.1.1"; // will return an array of switch number in a vss
	
//snmp_SysObjectId, oid_model, oid_sn, model
	$sqlquery = "SELECT * FROM plugin_extenddb_model WHERE snmp_SysObjectId='".$SysObjId."'";

	$result = db_fetch_row($sqlquery);
	if( empty($result) ) {
		// use default value
		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.1001";
	} else {
		$snmpserialno =  $result['oid_sn'];
	}
	
	// check if we have a stack, and how many device
	$stacknumber = cacti_snmp_walk( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmp_stackinfo, 
	$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
	$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
	$hostrecord_array['snmp_context'] ); // count() if 0 mean no stack possibility, or can't read (4500x)
	
	$serialno = '';
	if( count($stacknumber) == 0) { // VSS ? or no answer to CISCO-STACKWISE-MIB
		// check if we have a vss
		$vssnumber = cacti_snmp_walk( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmp_vssinfo, 
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
		$hostrecord_array['snmp_context'] ); // count() if 0 mean no stack possibility, or can't read (4500x)
	
		if( count($vssnumber) == 0) { // no vss either
			$serialno = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno, 
			$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
			$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
			$hostrecord_array['snmp_context'] );
			if( empty($serialno) ) {
				$text = "Can t find serial No for : " . $hostrecord_array['description'] .'(oid:'.$hostrecord_array['snmp_sysObjectID'].') at: '. $snmpserialno;
				cacti_log( $text, false, "EXTENDDB" );
			}
		} else {
			foreach( $vssnumber as $stackitem ) {
				$regex = '~(.[0-9.]+)\.[0-9]+~';
				preg_match( $regex, $snmpserialno, $result ); // extract base of the OID from the DB (left part)
				$stacksnmpswnum = $result[1];
		
				$regex = '~.[0-9].*\.([0-9].*)~';
				preg_match( $regex, $stackitem['oid'], $result ); // extract the OID of the switch number from the snmp query
				$stacksnmpno = $stacksnmpswnum.'.'.$result[1];
	
				$serialno .= ' ' . cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $stacksnmpno, 
				$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
				$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
				$hostrecord_array['snmp_context'] );
			}			
			$serialno = trim($serialno);
		}
	} else {
		foreach( $stacknumber as $stackitem ) {
			$regex = '~(.[0-9.]+)\.[0-9]+~';
			preg_match( $regex, $snmpserialno, $result ); // extract base of the OID from the DB (left part)
			$stacksnmpswnum = $result[1];
	
			$regex = '~.[0-9].*\.([0-9].*)~';
			preg_match( $regex, $stackitem['oid'], $result ); // extract the OID of the switch number from the snmp query
			$stacksnmpno = $stacksnmpswnum.'.'.$result[1];

			$serialno .= ' ' . cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $stacksnmpno, 
			$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
			$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
			$hostrecord_array['snmp_context'] );
		}
		$serialno = trim($serialno);
	}
	return $serialno;
}

function extenddb_device_action_array($device_action_array) {
    $device_action_array['fill_extenddb'] = __('Scan for type and Serial');
        return $device_action_array;
}

function extenddb_device_action_execute($action) {
   global $config;
   if ($action != 'fill_extenddb' ) {
           return $action;
   }

   $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false) {
		if ($action == 'fill_extenddb' ) {
			foreach( $selected_items as $hostid ) {
				if ($action == 'fill_extenddb') {
					$dbquery = db_fetch_assoc("SELECT * FROM host WHERE id=".$hostid);
extdb_log("Fill ExtendDB value: ".$hostid." - ".print_r($dbquery[0])." - ".$dbquery[0]['description']."\n");
					update_sn_type( $dbquery[0], true );
				}
			}
		}
    }

	return $action;
}

function extenddb_device_action_prepare($save) {
    global $host_list;

    $action = $save['drp_action'];

    if ($action != 'fill_extenddb' ) {
		return $save;
    }

    if ($action == 'fill_extenddb' ) {
		$action_description = 'Scan for type and Serial';
			print "<tr>
                    <td colspan='2' class='even'>
                            <p>" . __('Click \'Continue\' to %s on these Device(s)', $action_description) . "</p>
                            <p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
                    </td>
            </tr>";
    }
	return $save;
}

function update_sn_type( $hostrecord_array, $force=false ) {
	if( $hostrecord_array['status']!= '3' and !$force) {
	extdb_log('Host not up: '.$hostrecord_array['description']);
	// host down do nothing
		return;
	}
	
	extdb_log('host: ' . $hostrecord_array['description'] );
		$host_extend_record['serial_no'] = get_SN( $hostrecord_array, $hostrecord_array['snmp_sysObjectID'] );
		if( $host_extend_record['serial_no'] == 'U' ) {
			extdb_log('can t SNMP read SN on ' . $hostrecord_array['description'] );
			return;
		}
		
		$hostrecord_array['serial_no'] = form_input_validate($host_extend_record['serial_no'], 'serial_no', '', true, 3);
		$hostrecord_array['type'] = get_type( $hostrecord_array );
		if( $hostrecord_array['type'] == 'U' ) {
			extdb_log('can t SNMP read type of ' . $hostrecord_array['description'] );
			return;
		}
	extdb_log('SN: ' . $hostrecord_array['serial_no'] );
	extdb_log('type: ' . $hostrecord_array['type'] );

	sql_save($hostrecord_array, 'host');
}

function extdb_log( $text ){
    	$dolog = read_config_option('extenddb_log_debug');
	if( $dolog ) cacti_log( $text, false, "EXTENDDB" );
}

function fill_model_db(){
/* insert values in plugin_ciscotools_modele */
	db_execute("INSERT INTO plugin_extenddb_model "
	."(snmp_SysObjectId, oid_model, oid_sn, model) VALUES "
	."('iso.3.6.1.4.1.9.1.837', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'CISCO881'),"
	."('iso.3.6.1.4.1.9.1.857', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'CISCO891-K9'),"
	."('iso.3.6.1.4.1.9.1.959', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'IE-3000-8TC'),"
	."('iso.3.6.1.4.1.9.1.279', '.1.3.6.1.2.1.47.1.1.1.1.2.2', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'VG200'),"
	."('iso.3.6.1.4.1.9.1.324', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'WS-C2950-24'),"
	."('iso.3.6.1.4.1.9.1.516', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3750G-12S'),"
	."('iso.3.6.1.4.1.9.1.540', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'WS-C2940-8TT-S'),"
	."('iso.3.6.1.4.1.9.1.558', '.1.3.6.1.2.1.47.1.1.1.1.2.2', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'VG224'),"
	."('iso.3.6.1.4.1.9.1.563', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560-24PS'),"
	."('iso.3.6.1.4.1.9.1.569', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'CISCO877-K9         Chassis'),"
	."('iso.3.6.1.4.1.9.1.571', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'CISCO871-K9         Chassis'),"
	."('iso.3.6.1.4.1.9.1.577', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', '2821'),"
	."('iso.3.6.1.4.1.9.1.578', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', '2851'),"
	."('iso.3.6.1.4.1.9.1.614', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560G-24PS'),"
	."('iso.3.6.1.4.1.9.1.633', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560-24TS-E'),"
	."('iso.3.6.1.4.1.9.1.694', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C2960-24TC-L'),"
	."('iso.3.6.1.4.1.9.1.797', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560-8PC'),"	
	."('iso.3.6.1.4.1.9.1.1020', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560V2-24TS'),"
	."('iso.3.6.1.4.1.9.1.1021', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560V2-24PS'),"
	."('iso.3.6.1.4.1.9.1.1041', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'CISCO3945-CHASSIS'),"
	."('iso.3.6.1.4.1.9.1.1069', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'AIR-CT5508'),"
	."('iso.3.6.1.4.1.9.1.1084', '.1.3.6.1.2.1.47.1.1.1.1.13.10', '.1.3.6.1.2.1.47.1.1.1.1.11.10', 'N5K-C5548UP'),"
	."('iso.3.6.1.4.1.9.1.1208', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C2960X-24PS-L'),"
	."('iso.3.6.1.4.1.9.1.1315', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C2960CPD-8PT-L'),"
	."('iso.3.6.1.4.1.9.1.1317', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560CG-8PC-S'),"
	."('iso.3.6.1.4.1.9.1.1378', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'C819G-U-K9'),"
	."('iso.3.6.1.4.1.9.1.1384', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'C819HG-U-K9'),"
	."('iso.3.6.1.4.1.9.1.1747', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'VG204XM'),"
	."('iso.3.6.1.4.1.9.1.1470', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'IE-2000-4TC-G-B'),"
	."('iso.3.6.1.4.1.9.1.1471', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'IE-2000-4T-G-B'),"
	."('iso.3.6.1.4.1.9.1.1473', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'IE-2000-8TC-G-B'),"
	."('iso.3.6.1.4.1.9.1.1497', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'C819G-4G-G-K9'),"
	."('iso.3.6.1.4.1.9.1.1730', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'IE-2000-16PTC-G-E'),"
	."('iso.3.6.1.4.1.9.1.1732', '.1.3.6.1.2.1.47.1.1.1.1.13.1000', '.1.3.6.1.2.1.47.1.1.1.1.11.1000', 'WS-C-4500X-32'),"
	."('iso.3.6.1.4.1.9.1.1745', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'WS-C3850-24XS-S'),"
	."('iso.3.6.1.4.1.9.1.1858', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'C891F-K9'),"
	."('iso.3.6.1.4.1.9.1.2059', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'cisco819G-4G'),"
	."('iso.3.6.1.4.1.9.1.2134', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560CX-12PC-S'),"
	."('iso.3.6.1.4.1.9.1.2277', '.1.3.6.1.2.1.47.1.1.1.1.13.1001', '.1.3.6.1.2.1.47.1.1.1.1.11.1001', 'WS-C3560CX-12PD-S'),"
	."('iso.3.6.1.4.1.9.1.2593', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'C9500-16X'),"
	."('iso.3.6.1.4.1.9.1.2661', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'IR1101-K9'),"
	."('iso.3.6.1.4.1.9.1.2694', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'C9200L-24P-4G-E'),"
	."('iso.3.6.1.4.1.9.1.2694', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'C9200L-24P-4X'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1062', '.1.3.6.1.2.1.47.1.1.1.1.13.10', '.1.3.6.1.2.1.47.1.1.1.1.11.10', 'UCS-FI-6248UP'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1410', '.1.3.6.1.2.1.47.1.1.1.1.13.10', '.1.3.6.1.2.1.47.1.1.1.1.11.10', '5672UP'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1491', '.1.3.6.1.2.1.47.1.1.1.1.13.10', '.1.3.6.1.2.1.47.1.1.1.1.11.10', 'DS-C9148S-K9'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1519', '.1.3.6.1.2.1.47.1.1.1.1.13.10', '.1.3.6.1.2.1.47.1.1.1.1.11.10', 'Nexus1000VSG'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.840', '.1.3.6.1.2.1.47.1.1.1.1.13.10', '.1.3.6.1.2.1.47.1.1.1.1.11.10', 'Virtual Supervisor Module'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.2560', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'IR807-LTE-GA-K9'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.2684', '.1.3.6.1.2.1.47.1.1.1.1.13.1', '.1.3.6.1.2.1.47.1.1.1.1.11.1', 'IE-3200-8P2S')"
	." ON DUPLICATE KEY UPDATE snmp_SysObjectId=VALUES(snmp_SysObjectId), oid_model=VALUES(oid_model), oid_sn=VALUES(oid_sn), model=VALUES(model)"
	);
}

function data_export () {
		// export CSV device list
		$dbquery = db_fetch_assoc("SELECT description, hostname, type, serial_no FROM host 
		WHERE status = '3' AND disabled != 'on'
		AND snmp_sysDescr LIKE '%cisco%'
		ORDER BY id");
		
		$stdout = fopen('php://output', 'w');

		header('Content-type: application/excel');
		header('Content-Disposition: attachment; filename=cacti-devices-type-sn.csv');
	
		$header = array_keys($dbquery[0]);
		fputcsv($stdout, $header);

		foreach($dbquery as $h) {
			fputcsv($stdout, $h );
		}
		fclose($stdout);
}

?>
