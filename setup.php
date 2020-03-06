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
	api_plugin_register_hook('extenddb', 'utilities_action', 'extenddb_utilities_action', 'setup.php');
	api_plugin_register_hook('extenddb', 'utilities_list', 'extenddb_utilities_list', 'setup.php');

}

function plugin_extenddb_uninstall () {
	// Do any extra Uninstall stuff here

	// Remove items from the settings table
	db_execute('ALTER TABLE host DROP COLUMN serial_no, DROP COLUMN type, DROP COLUMN isPhone, DROP COLUMN SysObjId');
}

function plugin_extenddb_check_config () {
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
	global $config;

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
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'serial_no', 'type' => 'char(50)', 'NULL' => true, 'default' => ''));
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'type', 'type' => 'char(50)', 'NULL' => true, 'default' => ''));
		}
		if( $old < '1.1.2' ) {
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'isPhone', 'type' => 'char(2)', 'NULL' => true, 'default' => ''));
		}
	if( $old < '1.2.1' ) {
			api_plugin_db_add_column ('extenddb', 'host', array('name' => 'SysObjId', 'type' => 'char(50)', 'NULL' => true, 'default' => ''));
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
			$fields_host_edit3['isPhone'] = array(
				'friendly_name' => 'isPhone',
				'description' => 'Is it a phone ?',
				'method' => 'checkbox',
				'value' => '|arg1:isPhone|',
				'default' => '',
			);
			$fields_host_edit3['SysObjId'] = array(
				'friendly_name' => 'SysObjId',
				'description' => 'Sys Object ID definition',
				'method' => 'textbox',
				'max_length' => 50,
				'value' => '|arg1:SysObjId|',
				'default' => '',
			);
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function extenddb_utilities_list () {
	global $colors;
	html_header(array("extenddb Plugin"), 4);
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_rebuild'>Complete Serial Number and SysObjId.</a>
		</td>
		<td class="textArea">
			Complete Serial Number anb SysObjId of all non filed device
		</td>
	<?php
	form_end_row();
}

function extenddb_utilities_action ($action) {
	// get device list,  where serial number is empty, or sysobjID
	$dbquery = db_fetch_assoc("SELECT  * FROM host WHERE serial_no is NULL OR SysObjId IS NULL OR serial_no = '' OR SysObjId = '' ORDER BY id");
	if ( ($dbquery > 0) && $action == 'extenddb_rebuild' ){
		if ($action == 'extenddb_rebuild') {
		// Upgrade the map address table
			foreach ($dbquery as $host) {
				extenddb_api_device_new( $host );
			}
		}
		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	} 
	return $action;
}

function extenddb_api_device_new ($hostrecord_array) {

	$snmpsysobjid		 = ".1.3.6.1.2.1.1.2.0"; // return Cisco OID type

	// don't do it for disabled
	if( $hostrecord_array['disabled'] == 'on' ) {
		return $hostrecord_array;
	}

	// look for the equipement type
	$searchtype = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpsysobjid,
	$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
	$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], 
	$hostrecord_array['snmp_priv_protocol'], $hostrecord_array['snmp_context'] ); 

	if( strcmp( $searchtype, 'U' ) == 0 ) {
extdb_log("recu: ". $hostrecord_array['description'] );
extdb_log("SysObjId: ". $searchtype );
		return $hostrecord_array;
	}

	// find and store device SysObjId
	$hostrecord_array['SysObjId'] = trim( substr($searchtype, strpos( $searchtype, ':' )+1) );
	db_execute("update host set SysObjId='".$hostrecord_array['SysObjId']. "' where id=" . $hostrecord_array['id'] );


	// find and store Serial Number 
	$hostrecord_array['serial_no'] = getSN( $hostrecord_array, $hostrecord_array['SysObjId'] );
	db_execute("update host set serial_no='".$hostrecord_array['serial_no']. "' where id=" . $hostrecord_array['id'] );

	return $hostrecord_array;
}

function extenddb_config_settings () {
	global $tabs, $settings, $extenddb_poller_frequencies, $extenddb_get_host_template, $extenddb_cpu_graph;

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;
}

function extenddb_check_dependencies() {
	global $plugins, $config;

	return true;
}

function getSN( $hostrecord_array, $SysObjId ){
	$SysObjId = substr($SysObjId, strrpos( $SysObjId, '.' )+1 );

	switch( $SysObjId ) {
	case "1732": // WS-C4500X-32
		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.500"; // module 1

		$serialno = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno, 
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
		$hostrecord_array['snmp_context'] );

		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.1000"; // module 2

		$serialno = $serialno . " ". cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno,
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'], 
		$hostrecord_array['snmp_context'] );
		break;

	case "2593": // C9500-16X
		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.1000"; // module 1

		$serialno = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno, 
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
		$hostrecord_array['snmp_context'] );

		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.2000"; // module 2

		$serialno = $serialno . " ". cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno,
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'], 
		$hostrecord_array['snmp_context'] );
		break;

	case "1410": // Nexus 5672UP
	case "1084": // Nexus 5548UP
		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.22";

		$serialno = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno, 
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'], 
		$hostrecord_array['snmp_context'] );
		break;


        case "324": // WS-C2950
        case "359": // WS-C2950
        case "540": // WS-C2940
	case "578": // 2800 
	case "837": // CISCO881
	case "857": // CISCO891
	case "1378": // C819G-G-U
	case "1858": // C891F
	case "1041": // 3945
	case "2059": // C819G-4G-GA-K9
                $snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.1";

                $serialno = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno,
                $hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'],
                $hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
                $hostrecord_array['snmp_context'] );
                break;

	default:	
		$snmpserialno = ".1.3.6.1.2.1.47.1.1.1.1.11.1001";

		$serialno = cacti_snmp_get( $hostrecord_array['hostname'], $hostrecord_array['snmp_community'], $snmpserialno, 
		$hostrecord_array['snmp_version'], $hostrecord_array['snmp_username'], $hostrecord_array['snmp_password'], 
		$hostrecord_array['snmp_auth_protocol'], $hostrecord_array['snmp_priv_passphrase'], $hostrecord_array['snmp_priv_protocol'],
		$hostrecord_array['snmp_context'] );
	}

	return $serialno;
}

function extdb_log( $text ){
    cacti_log( $text, false, "EXTENDDB" );
}

?>
