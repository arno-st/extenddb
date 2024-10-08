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
include_once($config['base_path'] . '/lib/ping.php');

require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_automation.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/sort.php');
require_once($config['base_path'] . '/lib/template.php');

function plugin_extenddb_install() {
	global $config;

	api_plugin_register_hook('extenddb', 'config_settings', 'extenddb_config_settings', 'setup.php');
	api_plugin_register_hook('extenddb', 'config_form', 'extenddb_config_form', 'setup.php');
	api_plugin_register_hook('extenddb', 'api_device_new', 'extenddb_api_device_new', 'setup.php');
// utilities action
	api_plugin_register_hook('extenddb', 'utilities_action', 'extenddb_utilities_action', 'setup.php');
	api_plugin_register_hook('extenddb', 'utilities_list', 'extenddb_utilities_list', 'setup.php');

// Device action
    api_plugin_register_hook('extenddb', 'device_action_array', 'extenddb_device_action_array', 'setup.php');
    api_plugin_register_hook('extenddb', 'device_action_execute', 'extenddb_device_action_execute', 'setup.php');
    api_plugin_register_hook('extenddb', 'device_action_prepare', 'extenddb_device_action_prepare', 'setup.php');
	api_plugin_register_hook('extenddb', 'device_remove', 'extenddb_api_device_remove', 'setup.php');

// add new filter for device
	api_plugin_register_hook('extenddb', 'device_filters', 'extenddb_device_filters', 'setup.php');
	api_plugin_register_hook('extenddb', 'device_sql_where', 'extenddb_device_sql_where', 'setup.php');
	api_plugin_register_hook('extenddb', 'device_table_bottom', 'extenddb_device_table_bottom', 'setup.php');
	
// host edit form, used to fill the model and serial number, or allow to enter one (same format of the extenddb model and serial no)
	api_plugin_register_hook('extenddb', 'host_edit_bottom', 'extenddb_host_edit_bottom', 'setup.php');

	extenddb_setup_table(); // setup the table if new install
	fill_model_db(); // place where new device are added
	
}

function plugin_extenddb_uninstall () {
	// Do any extra Uninstall stuff here

	// Remove items from the settings table
	db_execute('ALTER TABLE host DROP COLUMN isPhone');
	db_execute('ALTER TABLE host DROP COLUMN isWifi');
	db_execute('DROP TABLE plugin_extenddb_host_model');
	db_execute('DROP TABLE plugin_extenddb_host_serial_no');
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
		if( $old < '1.3.4' ) {
		}
		if( $old < '1.3.5' ) {
			// change the name of the switch type to model, to be more coherent between plugin
			db_execute('ALTER TABLE host CHANGE type model CHAR(50)');
		}
		if( $old < '1.3.6' ) {
			api_plugin_register_hook('extenddb', 'device_filters', 'extenddb_device_filters', 'setup.php');
			api_plugin_register_hook('extenddb', 'device_sql_where', 'extenddb_device_sql_where', 'setup.php');
			api_plugin_register_hook('extenddb', 'device_table_bottom', 'extenddb_device_table_bottom', 'setup.php');
		}
		if( $old < '1.4.0' ) {
			$data = array();
			$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'auto_increment'=>'');
			$data['columns'][] = array('name' => 'host_id', 'type' => 'mediumint(8)', 'NULL' => false );
			$data['columns'][] = array('name' => 'serial_no', 'type' => 'char(50)', 'NULL' => true );
 			$data['primary'] = "id`,`host_id";
			$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
			$data['keys'][] = array('name' => 'serial_no', 'columns' => 'serial_no');
			$data['type'] = 'InnoDB';
			api_plugin_db_table_create('extenddb', 'plugin_extenddb_host_serial_no', $data);

			$data = array();
			$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'auto_increment'=>'');
			$data['columns'][] = array('name' => 'host_id', 'type' => 'mediumint(8)', 'NULL' => false );
			$data['columns'][] = array('name' => 'model', 'type' => 'char(50)', 'NULL' => true );
 			$data['primary'] = "id`,`host_id";
			$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
			$data['keys'][] = array('name' => 'model', 'columns' => 'model');
			$data['type'] = 'InnoDB';
			api_plugin_db_table_create('extenddb', 'plugin_extenddb_host_model', $data);
			
			// upgrade from previous 1.4.0, so transfert data to new table
			$sql_query = "SELECT id, model, serial_no FROM host WHERE ((serial_no != '' AND serial_no IS NOT NULL) OR (model IS NOT NULL AND model != '')) AND snmp_version > 0";
			$dbquery = db_fetch_assoc( $sql_query );
			// be sure than the query return something
			if( count($dbquery) > 0 ) {
				foreach( $dbquery as $hostid ){
					$explode_model = explode('|', $hostid['model']);
					foreach( $explode_model as $key => $model ) {
						db_execute_prepared('INSERT INTO plugin_extenddb_host_model (id, host_id, model) VALUES (?, ?, ?)', array($key,$hostid['id'], $model) );
					}
		
					$explode_serial = explode('|', $hostid['serial_no']);
					foreach( $explode_serial as $key => $serial ) {
						db_execute_prepared('INSERT INTO plugin_extenddb_host_serial_no (id, host_id, serial_no) VALUES (?, ?, ?)', array($key, $hostid['id'], $serial) );
					}
				}
				db_remove_column('host', 'model');
				db_remove_column('host', 'serial_no');
			}
			api_plugin_register_hook('extenddb', 'device_remove', 'extenddb_api_device_remove', 'setup.php');
		}
		if( $old < '1.4.1' ) {
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'isWifi', 'type' => 'char(2)', 'NULL' => true, 'default' => ''));
		}
		if( $old < '1.4.2' ) {
			api_plugin_register_hook('extenddb', 'host_edit_bottom', 'extenddb_host_edit_bottom', 'setup.php');
		}

	}
}

function plugin_extenddb_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/extenddb/INFO', true);
	return $info['info'];
}

function extenddb_setup_table() {
	global $config;
	include_once($config["library_path"] . "/database.php");

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'NULL' => false);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'mediumint(8)', 'NULL' => false );
	$data['columns'][] = array('name' => 'serial_no', 'type' => 'char(50)', 'NULL' => true );
 	$data['primary'] = "id`,`host_id";
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'serial_no', 'columns' => 'serial_no');
	$data['type'] = 'InnoDB';
	api_plugin_db_table_create('extenddb', 'plugin_extenddb_host_serial_no', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'NULL' => false);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'mediumint(8)', 'NULL' => false );
	$data['columns'][] = array('name' => 'model', 'type' => 'char(50)', 'NULL' => true );
 	$data['primary'] = "id`,`host_id";
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'model', 'columns' => 'model');
	$data['type'] = 'InnoDB';
	api_plugin_db_table_create('extenddb', 'plugin_extenddb_host_model', $data);

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

	api_plugin_db_add_column ('extenddb', 'host', array('name' => 'isWifi', 'type' => 'char(2)', 'NULL' => true, 'default' => ''));
	api_plugin_db_add_column ('extenddb', 'host', array('name' => 'isPhone', 'type' => 'char(2)', 'NULL' => true, 'default' => ''));
		
}

function extenddb_api_device_remove( $host_id ){
	global $asset_status;
extdb_log('extenddb_api_device_remove Start: '.print_r($host_id, true) );

	foreach( $host_id as $host ) {
extdb_log('extenddb_api_device_remove remove from serial_no table: '.print_r($host, true) );
		db_execute_prepared('DELETE FROM plugin_extenddb_host_serial_no where host_id=?', array($host) );
extdb_log('extenddb_api_device_remove remove from model table: '.print_r($host_id, true) );
		db_execute_prepared('DELETE FROM plugin_extenddb_host_model where host_id=?', array($host) );
	}
	return $host_id;
}

function extenddb_config_form () {
	
	// 'value' => db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($host_id)),

	global $fields_host_edit;
	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'external_id') {
			$fields_host_edit3['extenddb_header'] = array(
				'friendly_name' => __('Extend DB'),
				'method' => 'spacer',
				'collapsible' => 'true'
			);
			$fields_host_edit3['serial_no'] = array(
				'method' => 'drop_sql',
				'friendly_name' => 'Serial No',
				'description' => 'Enter the serial number.',
				'max_length' => 50,
				'value' => '|arg1:serial_no|',
				'sql' => 'SELECT id, serial_no AS name FROM plugin_extenddb_host_serial_no WHERE host_id="|arg1:id|"',
				'default' => '',
			);
            $fields_host_edit3['model'] = array(
                'friendly_name' => 'Model',
                'description' => 'This is the model of equipement.',
                'method' => 'drop_sql',
                'max_length' => 50,
                'sql' => 'SELECT id, model AS name FROM plugin_extenddb_host_model WHERE host_id="|arg1:id|"',
                'value' => '|arg1:model|',
				'default' => '',
            );
			$fields_host_edit3['isPhone'] = array(
				'friendly_name' => 'isPhone',
				'description' => 'Is it a phone ?',
				'method' => 'checkbox',
				'value' => '|arg1:isPhone|',
				'default' => '',
			);
			$fields_host_edit3['isWifi'] = array(
				'friendly_name' => 'isWifi',
				'description' => 'Is it a WiFi AP ?',
				'method' => 'checkbox',
				'value' => '|arg1:isWifi|',
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
			<a href='utilities.php?action=extenddb_complete'>Complete Serial Number and Model</a>
		</td>
		<td class="textArea">
			Complete Serial Number anb Model of all non filed device
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_rebuild'>Recheck All Cisco Device</a>
		</td>
		<td class="textArea">
			Build Serial Number anb Model of All Cisco Device
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='<?php print $config['url_path'] . 'plugins/extenddb/'?>extenddb_type.php?action=display_model_db'>Edit the ExtendDB table</a>
		</td>
		<td class="textArea">
			Change, add or remove a model entry on the ExtendDB table
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_purge_wifi'>Remove inexistant WiFi devices</a>
		</td>
		<td class="textArea">
			Purge the DB with WiFi devices that are not connected anymore
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_purge'>Remove inexistant devices</a>
		</td>
		<td class="textArea">
			Purge the DB with  devices that are not connected anymore
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_count'>ExtendDB model count</a>
		</td>
		<td class="textArea">
			Count the number of each device model
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_export_model_SN'>ExtendDB Export Model and SN</a>
		</td>
		<td class="textArea">
			Export in CSV format, the model and SN of all active device
		</td>
	<?php
	form_end_row();
}

function extenddb_utilities_action ($action) {
	global $config, $item_rows;
	
cacti_log( 'Extenddb utilities start: '.$action, false, "EXTENDDB" ); // Log somme information on cactilog
	
	if ($action == 'extenddb_complete') {
	// get device list,  where serial number is empty, or model
		$dbquery = db_fetch_assoc("SELECT * FROM host 
		LEFT JOIN plugin_extenddb_host_serial_no AS pehs ON pehs.host_id=host.id
		LEFT JOIN plugin_extenddb_host_model AS pehm ON pehm.host_id=host.id
		WHERE (pehs.serial_no IS NULL OR pehs.serial_no = '' OR pehm.model IS NULL OR pehm.model ='')
           AND host.status = '3' AND host.disabled != 'on'
		AND host.snmp_sysDescr LIKE '%cisco%'
		AND host.snmp_version>0
		ORDER BY host.id");
	// Upgrade the extenddb value
		if( $dbquery > 0 ) {
			foreach ($dbquery as $host) {
				update_sn_model( $host );
			}
		}
		top_header();
		utilities();
		bottom_footer();
	} elseif ($action == 'extenddb_rebuild') {
	// get device list
			$dbquery = db_fetch_assoc("SELECT  * FROM host 
			WHERE status = '3' AND disabled != 'on'
			AND snmp_sysDescr LIKE '%cisco%'
			AND snmp_version>0
			ORDER BY id");
		// Upgrade the extenddb value
			if( $dbquery > 0 ) {
				foreach ($dbquery as $host) {
					update_sn_model( $host );
				}
			}
		top_header();
		utilities();
		bottom_footer();
	} elseif ($action=='extenddb_purge_wifi' ) {
		// purge the device that are not pingable
			// get device list
			$dbquery = "SELECT * FROM host WHERE disabled='on'
				AND availability > 0
				AND isWifi = 'on'
				ORDER BY id";
		$extenddb_purge_wifi = db_fetch_assoc($dbquery);
extdb_log('extenddb_utilities_action nb device: '. count($extenddb_purge_wifi) );
		if( $extenddb_purge_wifi > 0 ) {
			foreach ($extenddb_purge_wifi as $host) {
				// create new ping socket for host pinging
				$ping = new Net_Ping;

				$ping->host = $host;
				$ping->port = $host['ping_port'];
		
				// perform the appropriate ping check of the host, 100ms TimeOut, and just 2 retries
				$ping_results = $ping->ping(AVAIL_PING, $host['ping_method'], 100, 1);
	
				if ($ping_results === true) {
extdb_log('extenddb_utilities_action dont purge wifi device: '. $host['id']. ' description: '. $host['description'] );
				} else {
extdb_log('extenddb_utilities_action purge wifi device: '. $host['id']. ' description: '. $host['description'].' reps: '.$ping->ping_response );
					  api_device_remove( $host['id'] );
				}
			}
extdb_log('extenddb_utilities_action end purge: ' );
		}
		top_header();
		utilities();
		bottom_footer();
	} elseif ($action=='extenddb_purge' ) {
		// purge the disabled device that are not pingable
			// get device list
			$dbquery = "SELECT * FROM host WHERE disabled='on'
				AND availability > 0
				AND isWifi != 'on'
				ORDER BY id";
		$extenddb_purge = db_fetch_assoc($dbquery);
extdb_log('extenddb_utilities_action nb device: '. count($extenddb_purge) );
		if( $extenddb_purge > 0 ) {
			foreach ($extenddb_purge as $host) {
				// create new ping socket for host pinging
				$ping = new Net_Ping;

				$ping->host = $host;
				$ping->port = $host['ping_port'];
		
				// perform the appropriate ping check of the host, 10ms TimeOut, and just 2 retries
				$ping_results = $ping->ping(AVAIL_PING, $host['ping_method'], 10, 1);
	
				if ($ping_results === true) {
extdb_log('extenddb_utilities_action dont purge device: '. $host['id']. ' description: '. $host['description'] );
				} else {
extdb_log('extenddb_utilities_action purge device: '. $host['id']. ' description: '. $host['description'].' reps: '.$ping->ping_response );
					  api_device_remove( $host['id'] );
				}
			}
extdb_log('extenddb_utilities_action end purge: ' );
		}
		top_header();
		utilities();
		bottom_footer();
	} else if ($action == 'extenddb_export_model_SN') {
		data_export();
	} elseif ($action == 'extenddb_count') { //**************Display the list of model and number of each one of it
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

			$('#count_model').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		<?php
		html_start_box(__('Extenddb Device model'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
			<td>
			<form id='count_model' action='utilities.php'>
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

		$sql_where = " WHERE true ";

	/* filter by search string */
		if (get_request_var('filter') != '') {
			$sql_where .= ' AND pehm.model LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
		}

		// retrieve all model count uniquness
		$total_rows = db_fetch_cell("SELECT count(distinct(pehm.model)) FROM plugin_extenddb_host_model AS pehm". $sql_where);

extdb_log('total_rows :' .print_r($total_rows, true) );

		// count each of it
		$extenddb_count = db_fetch_assoc("SELECT count(pehm.model) AS occurence, pehm.model AS model FROM plugin_extenddb_host_model AS pehm ". $sql_where
		." GROUP BY model");

extdb_log('extenddb_count :' .print_r($extenddb_count, true) );

// sort it
extdb_log('model_count order before:' .print_r($extenddb_count, true) );
		$model = array_column( $extenddb_count, 'model' );
		$occurence = array_column( $extenddb_count, 'occurence' );

		if( get_request_var('sort_column') == 'occurence') {
			if( get_request_var('sort_direction') == 'ASC' )
				array_multisort( $occurence, SORT_ASC, $extenddb_count );
			else 
				array_multisort( $occurence, SORT_DESC, $extenddb_count );
		} else {
			if( get_request_var('sort_direction') == 'ASC' )
				array_multisort( $model, SORT_ASC, $extenddb_count );
			else 
				array_multisort( $model, SORT_DESC, $extenddb_count );
		}
extdb_log('model_count order after: ' .print_r($extenddb_count, true) );

// define the limit, based on the size of the display
		$extenddb_count = array_slice($extenddb_count, $rows*(get_request_var('page')-1), $rows,  $preserve_keys = true);
		
	/* generate page list */
		$nav = html_nav_bar('utilities.php?action=extenddb_count&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Entries'), 'page', 'main');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(
		'model' => array(__('Device model'), 'ASC'),
		'occurence' => array(__('Number of Occurence'), 'ASC'));

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=extenddb_count');

		if (cacti_sizeof($extenddb_count)) {
			foreach ($extenddb_count as $item) {
				if( empty($item['model']) ) $item['model'] = 'empty';
				form_alternate_row('line' . $item['model'], false);
				form_selectable_cell(filter_value($item['model'], get_request_var('filter'), 'utilities.php?action=extenddb_display&sort_column=description&model=' . $item['model']), $item['model'].'&page=1');
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
// Show list of a specific model
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

			$('#model').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		<?php
		html_start_box(__('Extenddb Device model'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
		<id='model' value=<?php print (get_request_var('model'))?> >
			<td>
			<form id='model' action='utilities.php'>
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

		$sql_where = "";
			
	/* filter by search string */
		if( get_request_var('model') == 'empty' || get_request_var('model') == '' ) {
			$sql_where .= ' LEFT JOIN plugin_extenddb_host_model AS pehm ON pehm.host_id=host.id
			WHERE ( pehm.host_id IS NULL) ';
		} else {
			$sql_where .= " INNER JOIN plugin_extenddb_host_model as pehm ON pehm.host_id=host.id 
			INNER JOIN plugin_extenddb_host_serial_no as pehs ON pehs.host_id=host.id 
			WHERE pehm.model LIKE ". db_qstr('%' . get_request_var('model') . '%');
		}
		$sql_where .= " AND host.disabled != 'on' ";
		
		$extenddb_display_sqlquery = "SELECT COUNT(DISTINCT(pehs.serial_no)) FROM host ".$sql_where;
		$total_rows = db_fetch_cell($extenddb_display_sqlquery);
extdb_log('type count query: '.$extenddb_display_sqlquery);

		// group by serial number to have only one entry of each
		$sql_where .= " GROUP BY pehs.serial_no ";

		$extenddb_display_sql = "SELECT host.id as id, host.hostname as hostname, host.description as description, pehs.serial_no as serial_no FROM host
			$sql_where
			ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
			LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$extenddb_display = db_fetch_assoc($extenddb_display_sql);
extdb_log('type display query: '.$extenddb_display_sql);
//SELECT id, hostname, description, serial_no FROM host WHERE model LIKE 'C9200L-24P-4X' ORDER BY description ASC LIMIT 50,50

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
cacti_log( 'Extenddb utilities end: '. $action, false, "EXTENDDB" ); // Log somme information on cactilog

	return $action;
}

function extenddb_api_device_new($hostrecord_array) {
$snmpsysobjid = ".1.3.6.1.2.1.1.2.0"; // ObjectID
$snmpsysdescr = ".1.3.6.1.2.1.1.1.0"; // system description

extdb_log('extenddb_api_device_new: '.print_r($hostrecord_array['description'], true) );

// check valid call
	if( !array_key_exists('disabled', $hostrecord_array ) ) {
			extdb_log('extenddb_api_device_new Not valid call: '. $hostrecord_array['description'] .' '. $hostrecord_array['hostname']);
		return $hostrecord_array;
	}

	// get valid host from DB
	$host = db_fetch_row("SELECT * FROM host WHERE hostname='".$hostrecord_array['hostname']."' OR description='".$hostrecord_array['description']."'");
	if( empty($host) ){
			extdb_log('extenddb_api_device_new Unknown hostname in Extenddb:'. $hostrecord_array['description'] .' '. $hostrecord_array['hostname'] );
		return $hostrecord_array;
	}
	
	// don't do it for Phone and Wifi
	if ($host['isPhone'] == 'On' ) {
			extdb_log('extenddb_api_device_new Exit Extenddb skip for phone');
		return $hostrecord_array;
	}

	// don't do it for disabled and no snmp
	if ($host['disabled'] == 'on' || $host['snmp_version'] == 0 ) {
extdb_log('extenddb_api_device_new Exit Extenddb Disabled or no snmp');
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
extdb_log('extenddb_api_device_new host_data: '. $hostrecord_array['description'] .' '. $hostrecord_array['hostname'].' '.$host['snmp_sysObjectID']);
		$host['snmp_sysObjectID'] = 'iso.3.6.1.4.1.9.1.'.$result[1];
		$hostrecord_array['snmp_sysObjectID'] = $host['snmp_sysObjectID'];
extdb_log('host_data: '.$host['snmp_sysObjectID']);
	
		$host['snmp_sysDescr'] = cacti_snmp_get( $host['hostname'], $host['snmp_community'], $snmpsysdescr, 
		$host['snmp_version'], $host['snmp_username'], $host['snmp_password'], 
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
		$host['snmp_context'] );
		$hostrecord_array['snmp_sysDescr'] = $host['snmp_sysDescr'];
extdb_log('host_id: '.$host['snmp_sysDescr']);
	}
	
	// do it for Cisco model
	if( mb_stripos( $host['snmp_sysDescr'], 'cisco') === false ) {
extdb_log('extenddb_api_device_new Exit Extenddb not cisco' );
		return $hostrecord_array;
	}
/*
	if (isset_request_var('isPhone')) {
		$hostrecord_array['isPhone'] = form_input_validate(get_nfilter_request_var('isPhone'), 'isPhone', '', true, 3);
	} else {
		$hostrecord_array['isPhone'] = form_input_validate('off', 'isPhone', '', true, 3);
	}
*/

//****** check the serial 
	$serial_nos = get_SN( $host, $host['snmp_sysObjectID'] );
	if( empty($serial_nos) ) {
		extdb_log('extenddb_api_device_new can t SNMP read SN on ' . $host['description'] );
		return $hostrecord_array;
	}
	if ( array_key_exists('id', $hostrecord_array)) {
		foreach(explode( '|', $serial_nos) as $key => $serial_no ) {
			$mysql_insert = "INSERT INTO plugin_extenddb_host_serial_no (id, host_id, serial_no) VALUES('".$key."', '".$hostrecord_array['id']."', '".$serial_no."')
			ON DUPLICATE KEY UPDATE serial_no='".$serial_no."'";
			db_execute($mysql_insert);
extdb_log('extenddb_api_device_new End Extenddb serial_no: '.print_r($mysql_insert, true) );
		}
	} else {
		cacti_log( 'Cant find Serial for device: '.$host['description'], false, "EXTENDDB" );
	}

//****** check the model
	$models = get_model( $host );
	if( empty($models) ) {
		extdb_log('extenddb_api_device_new can t SNMP read model on ' . $host['description'] );
		return $hostrecord_array;
	}
	if (array_key_exists('id', $hostrecord_array)) {
		foreach(explode( '|', $models) as $key => $model ) {
			$mysql_insert = "INSERT INTO plugin_extenddb_host_model (id, host_id, model) VALUES('".$key."', '".$hostrecord_array['id']."', '".$model."') 
			ON DUPLICATE KEY UPDATE model='".$model."'";
			db_execute($mysql_insert);
	extdb_log('extenddb_api_device_new End Extenddb model: '.print_r($mysql_insert, true) );
		}
	} else {
		cacti_log( 'Cant find Model for device: '.$host['description'], false, "EXTENDDB" );
	}

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

/*
Query the device to have the model of the device, based on the SysObjectID find by cacti, and a table inside extenddb
*/
function get_model( $hostrecord_array, $force=false ) {
	$snmp_stackinfo = ".1.3.6.1.4.1.9.9.500.1.2.1.1.1"; // will return an array of switch number, the id last number of the OID
	$snmp_vssinfo = ".1.3.6.1.4.1.9.9.388.1.2.2.1.1"; // will return an array of switch number in a vss

//snmp_SysObjectId, oid_model, oid_sn, model
	$sqlquery = "SELECT * FROM plugin_extenddb_model WHERE snmp_SysObjectId='".$hostrecord_array['snmp_sysObjectID']."'";

	$result = db_fetch_row($sqlquery);
	if( empty($result) ) {
		// use default value
		$oid_model = ".1.3.6.1.2.1.47.1.1.1.1.13.1001";
	} else {
		$oid_model =  $result['oid_model'];
	}
extdb_log('get_model oid_model: '.$oid_model );

	// check if we have a stack, and how many device
	$stacknumber = cacti_snmp_walk( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmp_stackinfo, 
	$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
	$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
	$hostrecord_array['snmp_context'] ); // count() if 0 mean no stack possibility, or can't read (4500x)

extdb_log('get_model stacknumber: '.print_r($stacknumber, true) );

	$data_model = '';
	if( count($stacknumber) == 0) { // VSS ? or no answer to CISCO-STACKWISE-MIB
		// check if we have a vss
		$vssnumber = cacti_snmp_walk( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmp_vssinfo, 
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
		$hostrecord_array['snmp_context'] ); // count() if 0 mean no stack possibility, or can't read (4500x)
extdb_log('get_model vssnumber: '.print_r($vssnumber, true) );
	
		if( count($vssnumber) == 0) { // no vss either
			$data_model = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $oid_model, 
			$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
			$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
			$hostrecord_array['snmp_context'] );
			if( $force ) {
				cacti_log('get_model '.$hostrecord_array['hostname'].' data_model: '.print_r($data_model, true), FALSE, "EXTENDDB");
				cacti_log('get_model SN: '.$hostrecord_array['hostname'].' '. $hostrecord_array['snmp_community'].' '. $oid_model.' '. 
			$hostrecord_array['snmp_version'].' '. $hostrecord_array['snmp_username'].' '.$hostrecord_array['snmp_auth_protocol'].' '.
			$hostrecord_array['snmp_priv_protocol'].' x'.$hostrecord_array['snmp_context'].'x', FALSE, "EXTENDDB");
			}

extdb_log('get_model data_model1: '.$data_model );
		} else {
			// if VSS of 4500-X the device number are 1000 or 11000, can't find relationship with vssnumber
			foreach( $vssnumber as $stackitem ) {
				
				$regex = '~(.[0-9.]+)\.([0-9]+)~';
				preg_match( $regex, $oid_model, $result ); // extract base of the OID from the DB (left part)
				$stacksnmpswnum = $result[1];

			if( $stackitem == 1 ) $stacksnmpno = $stacksnmpswnum.'.1000';
			else $stacksnmpno = $stacksnmpswnum.'.11000';
			
				$data_model .= '|' . cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $stacksnmpno, 
				$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
				$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
				$hostrecord_array['snmp_context'] );
			}			
			$data_model = trim($data_model);
			$data_model = trim($data_model, '|');

extdb_log('get_model data_model2: '.$data_model );

		}
	} else {
extdb_log("get_model stacknumber for : " . $hostrecord_array['description'] .' nb:'. print_r($stacknumber, true) );
		foreach( $stacknumber as $stackitem ) {
			$regex = '~(.[0-9.]+)\.([0-9]+)~';
			preg_match( $regex, $oid_model, $result ); // extract base of the OID from the DB (left part)
			$stacksnmpswnum = $result[1];
	
			$regex = '~.[0-9].*\.([0-9].*)~';
			preg_match( $regex, $stackitem['oid'], $result ); // extract the OID of the switch number from the snmp query
			$stacksnmpno = $stacksnmpswnum.'.'.$result[1];
extdb_log("get_model stacknumber for : " . $hostrecord_array['description'] .' nb:'. print_r($stacksnmpno, true) );

			$data_model .= '|' . cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $stacksnmpno, 
			$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
			$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
			$hostrecord_array['snmp_context'] );
		}
		$data_model = trim($data_model);
		$data_model = trim($data_model, '|');

extdb_log('get_model data_model3: '.$data_model );
	}


	if( empty($data_model) ) {
		if( $force ) {
			cacti_log("Can t find model No for : " . $hostrecord_array['description'] .' '. $hostrecord_array['hostname'].' '.
			$hostrecord_array['snmp_sysObjectID'].' '.$hostrecord_array['snmp_sysObjectID'], TRUE, "EXTENDDB");
		}
		extdb_log( "Can t find model No for : " . $hostrecord_array['description'].'(oid:'.$hostrecord_array['snmp_sysObjectID'].') at: '. $oid_model);
	}

	return $data_model;
}

/*
Query the device to have the serial  number, based on the SysObjectID find by cacti, and a table inside extenddb
*/
function get_SN( $hostrecord_array, $SysObjId, $force=false ){
	$snmp_stackinfo = "1.3.6.1.4.1.9.9.500.1.2.1.1.1"; // will return an array of switch number, the id last number of the OID
	$snmp_vssinfo = "1.3.6.1.4.1.9.9.388.1.2.2.1.1"; // will return an array of switch number in a vss
	
//snmp_SysObjectId, oid_model, oid_sn, model
	$sqlquery = "SELECT * FROM plugin_extenddb_model WHERE snmp_SysObjectId='".$SysObjId."'";
extdb_log('get_SN SysObjId: '.$SysObjId );

	$result = db_fetch_row($sqlquery);
	if( empty($result) ) {
		// use default value
		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.1001";
	} else {
		$snmpserialno =  $result['oid_sn'];
	}
extdb_log('get_SN SerialNo: '.$snmpserialno );
	
	// check if we have a stack, and how many device
	$stacknumber = cacti_snmp_walk( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmp_stackinfo, 
	$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
	$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
	$hostrecord_array['snmp_context'] ); // count() if 0 mean no stack possibility, or can't read (4500x)
extdb_log('get_SN stacknumber: '.print_r($stacknumber, true) );
	
	$serialno = '';
	if( count($stacknumber) == 0) { // VSS ? or no answer to CISCO-STACKWISE-MIB
		// check if we have a vss
		$vssnumber = cacti_snmp_walk( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmp_vssinfo, 
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
		$hostrecord_array['snmp_context'] ); // count() if 0 mean no stack possibility, or can't read (4500x)
extdb_log('get_SN vssnumber: '.print_r($vssnumber, true) );
	
		if( count($vssnumber) == 0) { // no vss either
			$serialno = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno, 
			$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
			$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
			$hostrecord_array['snmp_context'] );
			if( $force ) {
				cacti_log('get_SN '.$hostrecord_array['hostname'].' serialno: '.print_r($serialno, true), FALSE, "EXTENDDB");
				cacti_log('debug SN: '.$hostrecord_array['hostname'].' '. $hostrecord_array['snmp_community'].' '. $snmpserialno.' '. 
			$hostrecord_array['snmp_version'].' '. $hostrecord_array['snmp_username'].' '.$hostrecord_array['snmp_auth_protocol'].' '.
			$hostrecord_array['snmp_priv_protocol'].' x'.$hostrecord_array['snmp_context'].'x', FALSE, "EXTENDDB");
			}
extdb_log('get_SN serialno: '.print_r($serialno, true) );
		} else {
			foreach( $vssnumber as $stackitem ) {
				$regex = '~(.[0-9.]+)\.[0-9]+~';
				preg_match( $regex, $snmpserialno, $result ); // extract base of the OID from the DB (left part)
				$stacksnmpswnum = $result[1];
		
				$regex = '~.[0-9].*\.([0-9].*)~';
				preg_match( $regex, $stackitem['oid'], $result ); // extract the OID of the switch number from the snmp query
				$stacksnmpno = $stacksnmpswnum.'.'.$result[1];
	
				$serialno .= '|' . cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $stacksnmpno, 
				$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
				$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
				$hostrecord_array['snmp_context'] );
			}			
			$serialno = trim($serialno);
			$serialno = trim($serialno, '|');
		}
	} else {
extdb_log("get_SN stacknumber for : " . $hostrecord_array['description'] .' nb:'. print_r($stacknumber, true) );
		foreach( $stacknumber as $stackitem ) {
			$regex = '~(.[0-9.]+)\.[0-9]+~';
			preg_match( $regex, $snmpserialno, $result ); // extract base of the OID from the DB (left part)
			$stacksnmpswnum = $result[1];
	
			$regex = '~.[0-9].*\.([0-9].*)~';
			preg_match( $regex, $stackitem['oid'], $result ); // extract the OID of the switch number from the snmp query
			$stacksnmpno = $stacksnmpswnum.'.'.$result[1];
extdb_log("get_SN stacknumber for : " . $hostrecord_array['description'] .' nb:'. print_r($stacksnmpno, true) );

			$serialno .= '|' . cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $stacksnmpno, 
			$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
			$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
			$hostrecord_array['snmp_context'] );
		}
		$serialno = trim($serialno);
		$serialno = trim($serialno, '|');
extdb_log('get_SN serialno: '.$serialno );
	}
	
	if( empty($serialno) ) {
		if( $force ) {
			cacti_log("get_SN Can t find SN for : " . $hostrecord_array['description'] .' '. $hostrecord_array['hostname'].' '.
			$hostrecord_array['snmp_sysObjectID'].' '.$hostrecord_array['snmp_sysObjectID'] , TRUE, "EXTENDDB");
		}

		extdb_log( "get_SN Can t find SN for : " . $hostrecord_array['description'].'(oid:'.$hostrecord_array['snmp_sysObjectID'].') at: '. $snmpserialno);
	}
	return $serialno;
}

function extenddb_device_action_array($device_action_array) {
    $device_action_array['fill_extenddb'] = __('Scan for model and Serial');

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
cacti_log("Fill ExtendDB value: ".$hostid." - ".print_r($dbquery[0])." - ".$dbquery[0]['description']."\n", FALSE, "EXTENDDB");
					update_sn_model( $dbquery[0], true );
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
		$action_description = 'Scan for model and Serial';
			print "<tr>
                    <td colspan='2' class='even'>
                            <p>" . __('Click \'Continue\' to %s on these Device(s)', $action_description) . "</p>
                            <p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
                    </td>
            </tr>";
    }
	return $save;
}

function update_sn_model( $host, $force=false ) {
	if( $host['status']!= '3' and !$force) {
extdb_log('Host not up: '.$host['description']);
	// host down do nothing
		return;
	}
	
extdb_log('update_sn_model host: ' . $host['description'] );
	$serial_nos = get_SN( $host, $host['snmp_sysObjectID'], $force );
	if( !empty($serial_nos) ) {
		foreach(explode( '|', $serial_nos) as $key => $serial_no ) {
			$mysql_insert = "INSERT INTO plugin_extenddb_host_serial_no (id, host_id, serial_no) VALUES('".$key."', '".$host['id']."', '".$serial_no."')
			ON DUPLICATE KEY UPDATE serial_no='".$serial_no."'";
			db_execute($mysql_insert);
extdb_log('update_sn_model End Extenddb serial_no: '.print_r($mysql_insert, true) );
		}
	} else {
extdb_log('update_sn_model can t SNMP read SN on ' . $host['description'] );
	}

	$models = get_model( $host, $force );
	if( !empty($models) ) {
		foreach(explode( '|', $models) as $key => $model ) {
			$mysql_insert = "INSERT INTO plugin_extenddb_host_model (id, host_id, model) VALUES('".$key."', '".$host['id']."', '".$model."') 
			ON DUPLICATE KEY UPDATE model='".$model."'";
			db_execute($mysql_insert);
extdb_log('update_sn_model End Extenddb model: '.print_r($mysql_insert, true) );
		}
	} else {
		extdb_log('update_sn_model can t SNMP read model on ' . $host['description'] );
	}

}

function extdb_log( $text ){
    	$dolog = read_config_option('extenddb_log_debug');
	if( $dolog ) cacti_log( $text, false, "EXTENDDB" );
}

function fill_model_db(){
/* insert values in plugin_ciscotools_modele */
	db_execute("INSERT INTO plugin_extenddb_model "
	."(snmp_SysObjectId, oid_model, oid_sn, model) VALUES "
	."('iso.3.6.1.4.1.9.1.1041','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','CISCO3945-CHASSIS'),"
	."('iso.3.6.1.4.1.9.1.1069','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','AIR-CT5508-K9'),"
	."('iso.3.6.1.4.1.9.1.1069','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','AIR-CT5508'),"
	."('iso.3.6.1.4.1.9.1.1084','.1.3.6.1.2.1.47.1.1.1.1.13.10','.1.3.6.1.2.1.47.1.1.1.1.11.10','N5K-C5548UP'),"
	."('iso.3.6.1.4.1.9.1.1208','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','WS-C2960X-24PS-L'),"
	."('iso.3.6.1.4.1.9.1.1378','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C819G-U-K9'),"
	."('iso.3.6.1.4.1.9.1.1384','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C819HG-U-K9'),"
	."('iso.3.6.1.4.1.9.1.1470','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-2000-4TC-G-B'),"
	."('iso.3.6.1.4.1.9.1.1470','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-2000-4TS-G-L'),"
	."('iso.3.6.1.4.1.9.1.1471','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-2000-4T-G-B'),"
	."('iso.3.6.1.4.1.9.1.1471','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-2000-4TS-G-B'),"
	."('iso.3.6.1.4.1.9.1.1473','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-2000-8TC-G-B'),"
	."('iso.3.6.1.4.1.9.1.1475','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-2000-16TC-G-E'),"
	."('iso.3.6.1.4.1.9.1.1497','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C819G-4G-G-K9'),"
	."('iso.3.6.1.4.1.9.1.1730','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-2000-16PTC-G-E'),"
	."('iso.3.6.1.4.1.9.1.1732','.1.3.6.1.2.1.47.1.1.1.1.13.1000','.1.3.6.1.2.1.47.1.1.1.1.11.1000','WS-C4500X-32'),"
	."('iso.3.6.1.4.1.9.1.1732','.1.3.6.1.2.1.47.1.1.1.1.13.1000','.1.3.6.1.2.1.47.1.1.1.1.11.1000','WS-C-4500X-32'),"
	."('iso.3.6.1.4.1.9.1.1745','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','WS-C3850-24XS-S'),"
	."('iso.3.6.1.4.1.9.1.1745','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','WS-C3850-24T-S'),"
	."('iso.3.6.1.4.1.9.1.1747','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','VG204XM'),"
	."('iso.3.6.1.4.1.9.1.1858','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C891F-K9'),"
	."('iso.3.6.1.4.1.9.1.2059','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C819G-4G-GA-K9'),"
	."('iso.3.6.1.4.1.9.1.2059','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','cisco819G-4G'),"
	."('iso.3.6.1.4.1.9.1.2134','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','WS-C3560CX-12PC-S'),"
	."('iso.3.6.1.4.1.9.1.2277','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','WS-C3560CX-12PD-S'),"
	."('iso.3.6.1.4.1.9.1.2506','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C1111-4PLTEEA'),"
	."('iso.3.6.1.4.1.9.1.2530','.1.3.6.1.2.1.47.1.1.1.1.13.2','.1.3.6.1.2.1.47.1.1.1.1.11.2','Cisco C9800-40-K9 Chassis'),"
	."('iso.3.6.1.4.1.9.1.2530','.1.3.6.1.2.1.47.1.1.1.1.13.2','.1.3.6.1.2.1.47.1.1.1.1.11.2','C9800-40-K9'),"
	."('iso.3.6.1.4.1.9.1.2560','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','IR807G-LTE-GA-K9'),"
	."('iso.3.6.1.4.1.9.1.2593','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C9500-16X'),"
	."('iso.3.6.1.4.1.9.1.2661','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','IR1101-K9'),"
	."('iso.3.6.1.4.1.9.1.2685','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-3300-8T2S'),"
	."('iso.3.6.1.4.1.9.1.2686','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-3300-8P2S'),"
	."('iso.3.6.1.4.1.9.1.2694','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C9200L-24P-4G'),"
	."('iso.3.6.1.4.1.9.1.2694','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C9200L-24P-4X'),"
	."('iso.3.6.1.4.1.9.1.2694','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C9200L-24P-4G-E'),"
	."('iso.3.6.1.4.1.9.1.2711','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','C921-4PLTEGB'),"
	."('iso.3.6.1.4.1.9.1.279','.1.3.6.1.2.1.47.1.1.1.1.2.2','.1.3.6.1.2.1.47.1.1.1.1.11.1','VG200'),"
	."('iso.3.6.1.4.1.9.1.3007','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-3300-8T2X'),"
	."('iso.3.6.1.4.1.9.1.3008','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-3300-8U2X'),"
	."('iso.3.6.1.4.1.9.1.558','.1.3.6.1.2.1.47.1.1.1.1.2.2','.1.3.6.1.2.1.47.1.1.1.1.11.1','VG224'),"
	."('iso.3.6.1.4.1.9.1.569','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','CISCO877-K9         Chassis'),"
	."('iso.3.6.1.4.1.9.1.571','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','CISCO871-K9         Chassis'),"
	."('iso.3.6.1.4.1.9.1.837','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','CISCO881'),"
	."('iso.3.6.1.4.1.9.1.857','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','CISCO891-K9'),"
	."('iso.3.6.1.4.1.9.1.959','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-3000-8TC'),"
	."('iso.3.6.1.4.1.9.1.959','.1.3.6.1.2.1.47.1.1.1.1.13.1001','.1.3.6.1.2.1.47.1.1.1.1.11.1001','IE-3000-8TC-E'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1062','.1.3.6.1.2.1.47.1.1.1.1.13.10','.1.3.6.1.2.1.47.1.1.1.1.11.10','UCS-FI-6248UP'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1084','.1.3.6.1.2.1.47.1.1.1.1.13.10','.1.3.6.1.2.1.47.1.1.1.1.11.10','5548UP'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1410','.1.3.6.1.2.1.47.1.1.1.1.13.10','.1.3.6.1.2.1.47.1.1.1.1.11.10','N5K-C5672UP'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1410','.1.3.6.1.2.1.47.1.1.1.1.13.10','.1.3.6.1.2.1.47.1.1.1.1.11.10','5672UP'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.1491','.1.3.6.1.2.1.47.1.1.1.1.13.10','.1.3.6.1.2.1.47.1.1.1.1.11.10','DS-C9148S-K9'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.2560','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','IR807-LTE-GA-K9'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.2684','.1.3.6.1.2.1.47.1.1.1.1.13.1','.1.3.6.1.2.1.47.1.1.1.1.11.1','IE-3200-8P2S'),"
	."('iso.3.6.1.4.1.9.12.3.1.3.840','.1.3.6.1.2.1.47.1.1.1.1.13.10','.1.3.6.1.2.1.47.1.1.1.1.11.10','Virtual Supervisor Module')"
	." ON DUPLICATE KEY UPDATE snmp_SysObjectId=VALUES(snmp_SysObjectId), oid_model=VALUES(oid_model), oid_sn=VALUES(oid_sn), model=VALUES(model)"
	);
}

function data_export () {
	// export CSV device list
	$dbquery = db_fetch_assoc("SELECT host.description as Description, host.hostname as Hostname, plugin_extenddb_host_model.model as Model, 
	plugin_extenddb_host_serial_no.serial_no as Serial_no, host.status
	FROM host
	INNER JOIN plugin_extenddb_host_model ON plugin_extenddb_host_model.host_id=host.id
	INNER JOIN plugin_extenddb_host_serial_no ON plugin_extenddb_host_serial_no.host_id=host.id
	WHERE host.disabled != 'on'
	AND host.snmp_sysDescr LIKE '%cisco%'
	AND host.snmp_version>0
	GROUP BY plugin_extenddb_host_serial_no.serial_no
	ORDER BY host.id");

extdb_log('data_export: file export start ');	

	$stdout = fopen('php://output', 'w');

	header('Content-type: application/excel');
	header('Content-Disposition: attachment; filename=cacti-devices-type-sn.csv');

	if (cacti_sizeof($dbquery)) {
		$columns = array_keys($dbquery[0]);
		fputcsv($stdout, $columns);

		foreach($dbquery as $h) {
			fputcsv($stdout, $h);
		}
	}

	fclose($stdout);
}

function extenddb_device_filters($filters) {

	$filters['model'] = array(
		'filter' => FILTER_DEFAULT,
		'pageset' => true,
		'default' => '-1'
	);

	return $filters;
}

function extenddb_device_sql_where($sql_where) {
	$sqlquery = 'SELECT model 
	FROM plugin_extenddb_model  
	ORDER BY model';
	
	$result = db_fetch_assoc($sqlquery);
	$models[-1] = 'Any';
	$models[0] = 'None';
	foreach( $result as $list ) {
		$models[] = $list['model'];
	}

	if (get_request_var('model') >= 0 ) {
		($sql_where != '' ? $sql_where=" INNER JOIN plugin_extenddb_host_model pehm ON pehm.host_id=host.id ". $sql_where ." AND pehm.model='".$models[get_request_var('model')]."'" :$sql_where=" INNER JOIN plugin_extenddb_host_model pehm ON pehm.host_id=host.id ". $sql_where ." AND pehm.model='".$models[get_request_var('model')]."'" );

	}
	
	return $sql_where;
}

function extenddb_device_table_bottom() {
	$sqlquery = 'SELECT model 
	FROM plugin_extenddb_model 
	ORDER BY model';
	$result = db_fetch_assoc($sqlquery);
	
	$models[-1] = 'Any';
	$models[0] = 'None';
	foreach( $result as $list ) {
		$models[] = $list['model'];
	}

	$select = '<td>' . __('Models') . '</td><td><select id="model">';
	foreach($models as $index => $model) {
		if ($index == get_request_var('model')) {
			$select .= '<option selected value="' . $index . '">' . $model . '</option>';
		} else {
			$select .= '<option value="' . $index . '">' . $model . '</option>';
		}
	}
	$select .= '</select></td>';
	
    ?>
    <script type='text/javascript'>
	$(function() {
		$('#rows').parent().after('<?php print $select;?>');
		<?php if (get_selected_theme() != 'classic') {?>
		$('#model').selectmenu({
			change: function() { 
				applyFilter(); 
			},
			overflow:auto
		});
		<?php } else { ?>
		$('#model').change(function() {
			applyFilter(); 
		});
		<?php } ?>
	});

	applyFilter = function() {
		strURL  = 'host.php?host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&site_id=' + $('#site_id').val();
		strURL += '&model=' + $('#model').val();
		strURL += '&criticality=' + $('#criticality').val();
		strURL += '&poller_id=' + $('#poller_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	};

	</script>
	<?php
}

?>
