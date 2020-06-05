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

		if( $old < '1.2.3' ) {
// Device action
   	 		api_plugin_register_hook('extenddb', 'device_action_array', 'extenddb_device_action_array', 'setup.php');
    		api_plugin_register_hook('extenddb', 'device_action_execute', 'extenddb_device_action_execute', 'setup.php');
    		api_plugin_register_hook('extenddb', 'device_action_prepare', 'extenddb_device_action_prepare', 'setup.php');
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
			<a href='utilities.php?action=extenddb_complete'>Complete Serial Number and Type.</a>
		</td>
		<td class="textArea">
			Complete Serial Number anb Type of all non filed device
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=extenddb_rebuild'>Recheck All Cisco Device.</a>
		</td>
		<td class="textArea">
			Build Serial Number anb Type of All Cisco Device
		</td>
	<?php
	form_end_row();
}

function extenddb_utilities_action ($action) {
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

		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	} 
	return $action;
}

function extenddb_api_device_new($hostrecord_array) {
	// don't do it for disabled, and not UP
	if ($hostrecord_array['disabled'] == 'on' && $hostrecord_array['status'] != '3') {
		return $hostrecord_array;
	}

// host record_array just contain the basic information, need to be pooled for extenddb value
	$hostrecord_array['snmp_sysDescr'] = db_fetch_cell_prepared('SELECT snmp_sysDescr
			FROM host
			WHERE id ='.
			$hostrecord_array['id']);

	$hostrecord_array['snmp_sysObjectID'] = db_fetch_cell_prepared('SELECT snmp_sysObjectID 
			FROM host
			WHERE id ='.
			$hostrecord_array['id']);

        // do it for Cisco type
	if( mb_stripos( $hostrecord_array['snmp_sysDescr'], 'cisco') === false ) {
		return $hostrecord_array;
	}
	
	if (!empty($_POST['serial_no'])) {
		$hostrecord_array['serial_no'] = form_input_validate($_POST['serial_no'], 'serial_no', '', true, 3);
	} else {
		$host_extend_record['serial_no'] = get_SN( $hostrecord_array, $hostrecord_array['snmp_sysObjectID'] );
		$hostrecord_array['serial_no'] = form_input_validate($host_extend_record['serial_no'], 'serial_no', '', true, 3);
	}
	
	if (!empty($_POST['type']))
		$hostrecord_array['type'] = form_input_validate($_POST['type'], 'type', '', true, 3);
	else
		$hostrecord_array['type'] = get_type( $hostrecord_array );

	if (isset($_POST['isPhone']))
		$hostrecord_array['isPhone'] = form_input_validate($_POST['isPhone'], 'isPhone', '', true, 3);
	else
		$hostrecord_array['isPhone'] = form_input_validate('off', 'isPhone', '', true, 3);

	sql_save($hostrecord_array, 'host');

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

function get_type( $hostrecord_array ) {
	$stream = create_ssh($hostrecord_array['id']);
	if( $stream === false ) {
		return;
	}
	
	if(ssh_write_stream($stream, 'term length 0' ) === false) return;
	$data = ssh_read_stream($stream);
	if( $data === false ){
		ciscotools_log( 'Erreur can\'t read term length 0');
		return;
	}
	
	if ( ssh_write_stream($stream, 'sh inventory | inc PID') === false ) return;
	$data = ssh_read_stream($stream);
	if( $data === false ){
		ciscotools_log( 'Erreur can\'t read sh inventory');
		return;
	}
	$record = preg_split('/\n|\r\n?/', $data);
	
	$SysObjId = substr($hostrecord_array['snmp_sysObjectID'], strrpos( $hostrecord_array['snmp_sysObjectID'], '.' )+1 );

	switch( $SysObjId ) {
		case "1732": // WS-C4500X-32
		case "1410": // Nexus 5672UP
		case "1084": // Nexus 5548UP
			$data = $record[2];
			break;
	
		default:
			$data = $record[1];
			break;
	}

	$record_array = explode(',', $data);
	return trim(substr( $record_array[0], stripos($record_array[0], 'PID:')+4 ));

}

function get_SN( $hostrecord_array, $SysObjId ){
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
	case "2694": // 9200
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
					update_sn_type( $dbquery[0] );
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

function update_sn_type( $hostrecord_array ) {
	extdb_log('host: ' . $hostrecord_array['description'] );
		$host_extend_record['serial_no'] = get_SN( $hostrecord_array, $hostrecord_array['snmp_sysObjectID'] );
		$hostrecord_array['serial_no'] = form_input_validate($host_extend_record['serial_no'], 'serial_no', '', true, 3);
		$hostrecord_array['type'] = get_type( $hostrecord_array );
	extdb_log('SN: ' . $hostrecord_array['serial_no'] );
	extdb_log('type: ' . $hostrecord_array['type'] );

	sql_save($hostrecord_array, 'host');
}

function extdb_log( $text ){
    cacti_log( $text, false, "EXTENDDB" );
}
?>
