<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2017 The Cacti Group                                 |
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

function create_telnet($deviceid) {
	$dbquery = db_fetch_row_prepared("SELECT description, hostname, login, password FROM host WHERE id=?", array($deviceid));
    if( $dbquery === false ){
        return false; // no host to connect to
    }
	
	// look for the login/password on the device, or take the default one
	$account=array();
    if(empty($dbquery['login'])) {
        $account['login'] = read_config_option('ciscotools_default_login');
        $account['password'] = read_config_option('ciscotools_default_password');
    } else {
        $account['login'] = $dbquery['login'];
        $account['password'] = $dbquery['password'];
    }
 	
	// open the telnet stream to the device
 	$stream = open_telnet($dbquery['hostname'], $account['login'], $account['password']);
	if($stream !== false){
		$data = telnet_read_stream($stream );
		if( $data === false ){
			ciscotools_log( 'Erreur can\'t read login prompt');
			return false;
		}
	}
	return $stream;
}

function open_telnet( $hostname, $username, $password ) {
    $connection = stream_socket_client ($hostname.':23');
    if($connection === false ) {
        cacti_log( "can't open Telnet session to ".$hostname." error: ".$connection, false, 'CISCOTOOLS');
        return false;
    }

	stream_set_timeout($connection, 5); // 5sec timeout
	stream_set_blocking($connection, true);

	// Username prompt
    do {
		$output = telnet_read_stream($connection, 'username:');
		if( !$output ) {
			cacti_log( "Login error for host ".$hostname." via Telnet session, log: ".$username, false, 'CISCOTOOLS');
			return false;
		}
	} while( !stripos( $output, 'username' ) );
	telnet_write_stream($connection, $username );
	
	// Password prompt
    do {
		$output = telnet_read_stream($connection, 'password:');
		if( !$output ) {
			cacti_log( "Password error for host ".$hostname." via Telnet session, log: ".$username, false, 'CISCOTOOLS');
			return false;
		}
	} while( !stripos( $output, 'password' ) );
	telnet_write_stream($connection, $password );

    return $connection;
}

function close_telnet($connection) {
    fclose($connection);
}

function telnet_read_stream($stream, $term='#') {
	$output = '';
	
    do {
		$stream_out = @fread ($stream, 1);
        //ciscotools_log('stream read: >'.$stream_out.'<('.strlen($stream_out).')'.' hex:'.bin2hex($stream_out));
		// Timeout occured
		if( $stream_out === false ){
			ciscotools_log('Timeout on telnet fread');
			break;
		}
		$output .= $stream_out;
        // if the terminal is waiting to go for the next screen, just issue a space to go one
        if( strpos($output, "--More--" ) !== false ) {
            telnet_write_stream($stream, ' ' );
        }
    } while ( !feof($stream) && $stream_out !== false && stripos($output, $term) === false );
   
    if(strlen($output)!=0) {
        return $output;
    }
    
    ciscotools_log('telnet_read_stream - Error - No output');
    return false;
}

function telnet_write_stream( $stream, $cmd){
    do {
        $write = fwrite( $stream, $cmd.PHP_EOL );
	} while( $write < strlen($cmd) );
}

?>