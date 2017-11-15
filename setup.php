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

function plugin_extenddb_install () {
	api_plugin_register_hook('extenddb', 'config_settings', 'extenddb_config_settings', 'setup.php');
	api_plugin_register_hook('extenddb', 'config_form', 'extenddb_config_form', 'setup.php');
	api_plugin_register_hook('extenddb', 'api_device_new', 'extenddb_api_device_new', 'setup.php');

}

function plugin_extenddb_uninstall () {
	// Do any extra Uninstall stuff here

	// Remove items from the settings table
	db_execute('ALTER TABLE host DROP COLUMN serial_no, DROP COLUMN type');
}

function plugin_extenddb_check_config () {
	global $config;
	// Here we will check to ensure everything is configured
	extenddb_check_upgrade ();

	return true;
}

function plugin_extenddb_upgrade () {
	// Here we will upgrade to the newest version
	extenddb_check_upgrade();
	return false;
}

function extenddb_check_upgrade() {
	$version = plugin_extenddb_version ();
	$current = $version['version'];
	$old     = read_config_option('plugin_extenddb_version');
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
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'serial_no', 'type' => 'char(20)', 'NULL' => false, 'default' => ''));
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'type', 'type' => 'char(50)', 'NULL' => false, 'default' => ''));
		}

	}
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
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function extenddb_api_device_new ($save) {
	$snmptype		 = ".1.0.8802.1.1.2.1.5.4795.1.2.7.0";
	$snmpserialno		= ".1.3.6.1.2.1.47.1.1.1.1.11.1001";

	if( isset($_POST['serial_no']) ){
		if ( empty($_POST['serial_no']) ) {
			$save['serial_no'] = parseDevice($save, $snmpserialno );
		} else {
			$save['serial_no'] = form_input_validate($_POST['serial_no'], 'serial_no', '', true, 3);
		}
	} else {
		$save['serial_no'] = parseDevice($save, $snmpserialno );
	}
	
	if( isset($_POST['type']) ){
		if ( empty($_POST['type']) ) {
			$save['type'] = parseDevice($save, $snmptype );
		} else {
			$save['type'] = form_input_validate($_POST['type'], 'type', '', true, 3);
		}
	} else {
		$save['type'] = parseDevice($save, $snmptype );
	}
	return $save;
}

function extenddb_config_settings () {
	global $tabs, $settings, $extenddb_poller_frequencies, $extenddb_get_host_template, $extenddb_cpu_graph;

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;
/*
	$tabs['misc'] = 'Misc';

	$temp = array(
		'extenddb' => array(
			'friendly_name' => 'ExtendDB',
			'method' => 'spacer',
			),
	);

	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;
*/
}

function extenddb_check_dependencies() {
	global $plugins, $config;

	return true;
}

function parseDevice( $hostrecord_array, $OID ) {

	$ret = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $OID, $hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], $hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'], $hostrecord_array['snmp_context'] );


	return $ret;

}

?>
