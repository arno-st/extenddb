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

function create_ssh($deviceid) {
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
 	
	// open the ssh stream to the device
 	$stream = open_ssh($dbquery['hostname'], $account['login'], $account['password']);
	if($stream !== false){
		$data = ssh_read_stream($stream );
		if( $data === false ){
			extdb_log( 'Erreur can\'t read login prompt');
			return false;
		}
	}
	return $stream;
}

function open_ssh( $hostname, $username, $password ) {
    $connection = @ssh2_connect($hostname, 22);
    if($connection === false ) {
        cacti_log( "can't open SSH session to ".$hostname, false, 'CISCOTOOLS');
        return false;
    }

    if( !@ssh2_auth_password($connection, $username, $password) ) {
        cacti_log( "can't login to host ".$hostname." via SSH session, log: ".$username, false, 'CISCOTOOLS');
        return false;
   }

    $stream = @ssh2_shell($connection, 'vt100', null, 80, 24, SSH2_TERM_UNIT_CHARS );
	stream_set_timeout($stream, 210);
	stream_set_blocking($stream, true);

    return $stream;
}

function close_ssh($connection) {
    @ssh2_disconnect ($connection);
}

function ssh_read_stream($stream) {
	$output = '';
	
    do {
		$stream_out = fread ($stream, 1);
        // ciscotools_log('stream read: >'.$stream_out.'<('.strlen($stream_out).')'.' hex:'.bin2hex($stream_out));
		$output .= $stream_out;
        // if the terminal is waiting to go for the next screen, just issue a space to go one
        if( strpos($output, "--More--" ) !== false ) {
            ssh_write_stream($stream, ' ' );
        }
     } while ( !feof($stream) && $stream_out !== false && $stream_out != '#');
   
    if(strlen($output)!=0) {
	extdb_log('SSH read stream: '.$output);
        return $output;
    }
    
    extdb_log('ssh_read_stream - Error - No output');
    return false;
}

function ssh_write_stream( $stream, $cmd){
    do {
        $write = fwrite( $stream, $cmd.PHP_EOL );
	} while( $write < strlen($cmd) );
	extdb_log('SSH write stream: '.print_r($cmd, true));
}
?>