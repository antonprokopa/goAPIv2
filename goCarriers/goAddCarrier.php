<?php
 /**
 * @file 		goAddCarrier.php
 * @brief 		API for Carriers
 * @copyright 	Copyright (c) 2018 GOautodial Inc.
 * @author		Demian Lizandro A. Biscocho
 * @author     	Jeremiah Sebastian Samatra
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

	@include_once ("goAPI.php");
	
	### POST or GET Variables
	$carrier_type = $astDB->escape($_REQUEST['carrier_type']);
	
	if($carrier_type == "justgo"){
		$company		= $astDB->escape($_REQUEST['company']);
		$firstname 		= $astDB->escape($_REQUEST['firstname']);
		$lastname 		= $astDB->escape($_REQUEST['lastname']);
		$address 		= $astDB->escape($_REQUEST['address']);
		$city 			= $astDB->escape($_REQUEST['city']);
		$state 			= $astDB->escape($_REQUEST['state']);
		$postal 		= $astDB->escape($_REQUEST['postal']);
		$country 		= $astDB->escape($_REQUEST['country']);
		$timezone 		= $astDB->escape($_REQUEST['timezone']);
		$phone 			= $astDB->escape($_REQUEST['phone']);
		$mobilephone 	= $astDB->escape($_REQUEST['mobilephone']);
		$email 			= $astDB->escape($_REQUEST['email']);
	}
	
	if($carrier_type == "manual" || $carrier_type == "copy"){
		$carrier_id		= $astDB->escape($_REQUEST['carrier_id']);
		$carrier_name 	= $astDB->escape($_REQUEST['carrier_name']);
		$active			= $astDB->escape($_REQUEST['active']);
	}
		
	if($carrier_type == "manual"){
		$carrier_description	= $astDB->escape($_REQUEST['carrier_description']);
		$user_group		= $astDB->escape($_REQUEST['user_group']);
		$authentication	= $astDB->escape($_REQUEST['authentication']);
		
		if($authentication == "auth_ip"){
			$host = $astDB->escape($_REQUEST['sip_server_ip']);
		}
		
		if($authentication == "auth_reg"){
			$host 		= $astDB->escape($_REQUEST['reg_host']);
			$username 	= $astDB->escape($_REQUEST['username']);
			$password 	= $astDB->escape($_REQUEST['password']);
			$reg_port 	= $astDB->escape($_REQUEST['reg_port']);
		}
		
		$codecs			= $_REQUEST['codecs'];
		$dtmf			= $astDB->escape($_REQUEST['dtmf']);
		$custom_dtmf	= $astDB->escape($_REQUEST['custom_dtmf']);
		$dialprefix		= $astDB->escape($_REQUEST['dialprefix']);
		$protocol		= $astDB->escape($_REQUEST['protocol']);
		$server_ip		= $astDB->escape($_REQUEST['manual_server_ip']);
		
		if($protocol != "CUSTOM"){
			$registration_string = "";
			$account_entry = "";
			$globals_string = "";
			if($authentication == "auth_reg"){
				$registration_string .= "register => ".$username.":".$password."@".$host.":".$reg_port."/".$username;
			}
			
			$account_entry .= "[".$carrier_id."]\r\n";
			$account_entry .= "disallow=all\r\n";
			
			if(in_array("GSM", $codecs)) { $account_entry .= "allow=gsm\r\n"; }
			if(in_array("ULAW", $codecs)) { $account_entry .= "allow=ulaw\r\n"; }
			
			$account_entry .= "type=friend\r\n";
			
			if($dtmf == "custom") { $account_entry .= "dtmfmode=".$custom_dtmf."\r\n"; } 
				else { $account_entry .= "dtmfmode=".$dtmf."\r\n"; }
				
			$account_entry .= "context=trunkinbound\r\n";
			$account_entry .= "qualify=yes\r\n";
			$account_entry .= "insecure=invite,port\r\n";
			$account_entry .= "nat=force_rport,comedia\r\n";
			$account_entry .= "host=".$host."\r\n";
			
			if($authentication == "auth_reg"){
				$account_entry .= "username=".$username."\r\n";
				$account_entry .= "secret=".$password."\r\n";
			}
			
			if(in_array("ALAW", $codecs)) { $account_entry .= "allow=alaw\r\n"; }
			if(in_array("G729", $codecs)) { $account_entry .= "allow=g729\r\n"; }
			
			$protocol_m = $protocol."/";	
			
			$dialplan_entry = "";
			$dialplan_entry .= "exten => _". $dialprefix .".,1,AGI(agi://127.0.0.1:4577/call_log)\r\n";
			$dialplan_entry .= "exten => _". $dialprefix .".,2,Dial(". $protocol_m ."\${EXTEN:10}@". $carrier_id .",,tTo)\r\n";
			$dialplan_entry .= "exten => _". $dialprefix .".,3,Hangup";
			
		}else{
			$protocol				= $astDB->escape($_REQUEST['cust_protocol']);
			$registration_string	= $astDB->escape($_REQUEST['registration_string']);
			$account_entry			= $astDB->escape($_REQUEST['account_entry']);
			$globals_string			= $astDB->escape($_REQUEST['globals_string']);
			$dialplan_entry			= $astDB->escape($_REQUEST['dialplan_entry']);
		}		
	}
		
	if($carrier_type == "copy"){
		$server_ip			= $astDB->escape($_REQUEST['copy_server_ip']);
		$source_carrier		= $astDB->escape($_REQUEST['source_carrier']);
	}
	
	$log_user 	= $session_user;
	$log_group 	= go_get_groupid($session_user, $astDB);
	$ip_address = $astDB->escape($_REQUEST['log_ip']);

	$defProtocol 	= array('SIP','Zap','IAX2','EXTERNAL');
    $defActive 		= array("Y","N");
		
	if($carrier_id == null && $carrier_type != "justgo") {
		$apiresults = array("result" => "Error: Set a value for Carrier ID.");
	} elseif($carrier_name == null && $carrier_type != "justgo") {
		$apiresults = array("result" => "Error: Set a value for Carrier Name.");
	} elseif(!in_array($active,$defActive) && $active != null && $carrier_type != "justgo") {
		$apiresults = array("result" => "Error: Default value for active is Y or N only.");
	} elseif(!in_array($protocol,$defProtocol) && $protocol != null && $carrier_type != "justgo") {
		$apiresults = array("result" => "Error: Default value for protocol is SIP, Zap, IAX2 or EXTERNAL only.");
	} elseif((preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $carrier_id)) && $carrier_type != "justgo"){
		$apiresults = array("result" => "Error: Special characters found in carrier_id");
	} else {		
		if (!checkIfTenant($log_group, $goDB)) {
			//$astDB->where('user_group', $log_group);
		}
		
		if($carrier_type == "copy"){
			$astDB->where("carrier_id", $source_carrier);
			$fresults = $astDB->get("vicidial_server_carriers");
			
			if ($astDB->count > 0) {
				foreach ($fresults as $fetch_copy) {
					$user_group = $fetch_copy["user_group"];
					$protocol = $fetch_copy["protocol"];
					$carrier_description = $astDB->escape($fetch_copy["carrier_description"]);
					$registration_string = $astDB->escape($fetch_copy["registration_string"]);
					$account_entry = $astDB->escape($fetch_copy["account_entry"]);
					$globals_string = $astDB->escape($fetch_copy["globals_string"]);
					$dialplan_entry = $astDB->escape($fetch_copy["dialplan_entry"]);				
				}				
				
				$account_entry = str_replace('['.$source_carrier.']','['.$carrier_id.']',$account_entry);
				$dialplan_entry = str_replace('@'.$source_carrier,'@'.$carrier_id,$dialplan_entry);
				$astDB->where('user_group', $user_group);
			}						
		} else { $astDB->where('user_group', $log_group); }
		
		$astDB->getOne("vicidial_user_groups");
		$countResult = $astDB->getRowCount();

		if($countResult <= 0 && $user_group != "---ALL---") {
			$apiresults = array("result" => "Error: Invalid usergroup.");
		} else {
			$astDB->where('carrier_id', $carrier_id);
			$astDB->getOne("vicidial_server_carriers");
			//$select = $astDB->get("vicidial_server_carriers", NULL, $cols);	
			$countCheck = $astDB->getRowCount();

			if($countCheck > 0) {
				$apiresults = array("result" => "Error: Carrier already exist.");
			} else {
				$data = Array (
							"carrier_id" 			=> $carrier_id,
							"carrier_name" 			=> $carrier_name, 
							"registration_string"	=> $registration_string, 
							"account_entry" 		=> $account_entry, 
							"carrier_description" 	=> $carrier_description,
							"user_group" 			=> $user_group, 
							"protocol"				=> $protocol, 
							"dialplan_entry" 		=> $dialplan_entry, 
							"server_ip" 			=> $server_ip, 
							"globals_string" 		=> $globals_string, 
							"active" 				=> $active
						);

				$resultVSC = $astDB->insert("vicidial_server_carriers", $data);
			
				if($resultVSC) {			
					$astDB->where("generate_vicidial_conf", "Y");
					$astDB->where("active_asterisk_server", "Y");
					$astDB->where("server_ip", $server_ip);
					$queryUpdate = $astDB->update("servers", array("rebuild_conf_files" => "Y"));

					$log_id = log_action($goDB, 'ADD', $log_user, $ip_address, "Added a New Carrier: $carrier_id", $log_group, $resultVSC);
					$apiresults = array("result" => "success", "data" => $data);
				}else{
					$apiresults = array("result" => "Error in Saving: It appears something has occured, please consult the system administrator", "data" => $resultVSC);
				}
			}
		}
	}
?>
