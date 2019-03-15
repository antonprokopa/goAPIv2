<?php
 /**
 * @file 		goGetRealtimeAgentsMonitoring.php
 * @brief 		API for Dashboard
 * @copyright 	Copyright (c) 2018 GOautodial Inc.
 * @author		Jerico James Milo
 * @author     	Demian Lizandro A. Biscocho
 * @author     	Chris Lomuntad
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

    include_once ("goAPI.php");
 
	$log_user 											= $session_user;
	$log_group 											= go_get_groupid($session_user, $astDB); 
	$log_ip 											= $astDB->escape($_REQUEST['log_ip']);
	$goUser												= $astDB->escape($_REQUEST['goUser']);
	$goPass												= (isset($_REQUEST['log_pass'])) ? $astDB->escape($_REQUEST['log_pass']) : $astDB->escape($_REQUEST['goPass']);
	$campaigns 											= allowed_campaigns($log_group, $goDB, $astDB);

	// ERROR CHECKING 
	if (empty($goUser) || is_null($goUser)) {
		$apiresults 									= array(
			"result" 										=> "Error: goAPI User Not Defined."
		);
	} elseif (empty($goPass) || is_null($goPass)) {
		$apiresults 									= array(
			"result" 										=> "Error: goAPI Password Not Defined."
		);
	} elseif (empty($log_user) || is_null($log_user)) {
		$apiresults 									= array(
			"result" 										=> "Error: Session User Not Defined."
		);
	} else {
		// check if goUser and goPass are valid
		$fresults										= $astDB
			->where("user", $goUser)
			->where("pass_hash", $goPass)
			->getOne("vicidial_users", "user,user_level");
		
		$goapiaccess									= $astDB->getRowCount();
		$userlevel										= $fresults["user_level"];
		
		if ($goapiaccess > 0 && $userlevel > 7) {
			// set tenant value to 1 if tenant - saves on calling the checkIfTenantf function
			// every time we need to filter out requests
			$tenant										= (checkIfTenant($log_group, $goDB)) ? 1 : 0;
			
			if ($tenant) {
				$astDB->where("user_group", $log_group);
				$astDB->orWhere("user_group", "---ALL---");
			} else {
				if (strtoupper($log_group) != 'ADMIN') {
					if ($userlevel > 8) {
						$astDB->where("user_group", $log_group);
						$astDB->orWhere("user_group", "---ALL---");
					}
				}					
			}
				
			$rsltvGo									= $goDB->get("users", NULL, "userid,avatar");		
				
			if ($goDB->count > 0) {
				$dataGo 								= array();
				foreach ($rsltvGo as $fresultsGo){
					array_push($dataGo, $fresultsGo);
				}
			} 	
			
			$cols 										= array(
				"channel as 'pc_channel'",
				"server_ip as 'pc_server_ip'",
				"channel_group as 'pc_channel_group'",
				"extension as 'pc_extension'",
				"parked_by as 'pc_parked_by'",
				"UNIX_TIMESTAMP(parked_time) as 'pc_parked_time'"
			);
				
			$resultsPCs									= $astDB
				->where("channel", 0, ">")
				->get("parked_channels", NULL, $cols);	
				
			$tableQuery 								= "SHOW tables LIKE 'online'";
			$checkTable 								= $astDB->rawQuery($tableQuery);
			
			if ($checkTable) {
				$cols 									= array(
					"vl.phone_number as 'vl_phone_number'",
					"vla.extension as 'vla_extension'",
					"vla.user as 'vla_user'",
					"vu.full_name as 'vu_full_name'",
					"vu.user_group as 'vu_user_group'",
					"vu.phone_login as 'vu_phone_login'",
					"vla.conf_exten as 'vla_conf_exten'",
					"vla.status as 'vla_status'",
					"vla.comments as 'vla_comments'",
					"vla.server_ip as 'vla_server_ip'",
					"vla.call_server_ip as 'vla_call_server_ip'",
					"UNIX_TIMESTAMP(last_call_time) as 'last_call_time'",
					"UNIX_TIMESTAMP(last_update_time) as 'last_update_time'",
					"UNIX_TIMESTAMP(last_call_finish) as 'last_call_finish'",
					"vla.campaign_id as 'vla_campaign_id'",
					"UNIX_TIMESTAMP(last_state_change) as 'last_state_change'",
					"vla.lead_id as 'vla_lead_id'",
					"vla.agent_log_id as 'vla_agent_log_id'",
					"vu.user_id as 'vu_user_id'",
					"vu.user as 'vu_user'",
					"vla.callerid as 'vla_callerid'",
					"val.sub_status as 'vla_pausecode'",
					"vc.campaign_name as 'vla_campaign_name'",
					"ol.conference as 'ol_conference'",
					"ol.name as 'ol_callerid'"
				);
				
				$table									= "vicidial_users as vu, vicidial_agent_log as val, vicidial_campaigns as vc, online as ol, vicidial_live_agents as vla";				
				//$onlineAgents 							= $astDB
				//	->join("vicidial_list as vl", "vla.lead_id = vl.lead_id", "LEFT")
				//	//->join("online as ol", "ol.name = vla.callerid", "LEFT")
				//	//->joinOrWhere("online as ol", "ol.conference", "vla.conf_exten")			
				//	->where("ol.name = vla.callerid")
				//	->orWhere("ol.conference = vla.conf_exten")
				//	->where("vla.campaign_id", $campaigns, "IN")
				//	->where("vla.campaign_id = vc.campaign_id")
				//	->where("vla.user = vu.user")
				//	->where("vla.user_level != 4")
				//	->where("vla.user", DEFAULT_USERS, "NOT IN")
				//	->where("vla.agent_log_id = val.agent_log_id")
				//	->groupBy("ol.conference")
				//	->orderBy("last_call_time")		
				//	->get($table, NULL, $cols);
                
                $allowedCampaigns = implode("','", $campaigns);
                $defaultUsers = implode("','", DEFAULT_USERS);
                $SQLquery = "SELECT vl.phone_number as 'vl_phone_number', vla.extension as 'vla_extension', vla.user as 'vla_user',
                        vu.full_name as 'vu_full_name', vu.user_group as 'vu_user_group', vu.phone_login as 'vu_phone_login',
                        vla.conf_exten as 'vla_conf_exten', vla.status as 'vla_status', vla.comments as 'vla_comments',
                        vla.server_ip as 'vla_server_ip', vla.call_server_ip as 'vla_call_server_ip', UNIX_TIMESTAMP(last_call_time) as 'last_call_time',
                        UNIX_TIMESTAMP(last_update_time) as 'last_update_time', UNIX_TIMESTAMP(last_call_finish) as 'last_call_finish',
                        vla.campaign_id as 'vla_campaign_id', UNIX_TIMESTAMP(last_state_change) as 'last_state_change',
                        vla.lead_id as 'vla_lead_id', vla.agent_log_id as 'vla_agent_log_id', vu.user_id as 'vu_user_id',
                        vu.user as 'vu_user', vla.callerid as 'vla_callerid', val.sub_status as 'vla_pausecode',
                        vc.campaign_name as 'vla_campaign_name', ol.conference as 'ol_conference', ol.name as 'ol_callerid'
                    FROM vicidial_users as vu, vicidial_agent_log as val, vicidial_campaigns as vc, online as ol, vicidial_live_agents as vla
                    LEFT JOIN vicidial_list as vl ON vla.lead_id = vl.lead_id
                    WHERE (ol.name = vla.callerid OR ol.conference = vla.conf_exten) AND vla.campaign_id IN ('$allowedCampaigns')
                        AND (vla.user = vu.user AND vla.user NOT IN ('$defaultUsers')) AND vla.user_level != '4' AND vla.agent_log_id = val.agent_log_id
                    GROUP BY ol.conference
                    ORDER BY last_call_time";
                $onlineAgents = $astDB->rawQuery($SQLquery);
					
				if ($astDB->count > 0) {
					$dataPCs 							= array();
					
					if ($resultsPCs) {
						foreach ($resultsPCs as $resultsPC) {               
							array_push($dataPCs, $resultsPC);
						}				
					}                        
					
					$apiresults 						= array(
						"result" 							=> "success", 
						//"query" 							=> $astDB->getLastQuery(),
                        "query"                             => $SQLquery,
						"data" 								=> $onlineAgents, 
						"dataGo" 							=> $dataGo,
						"parked" 							=> $dataPCs
					);			
				} else {
					$apiresults 						= array(
						"result" 							=> "success", 
						"data" 								=> 0
					);		
				}
					
			} else {	
				// online agents
				$query_OnlineAgents						= $astDB
					->where("campaign_id", $campaigns, "IN")
					->where("user_level < 8")
					->where("user_level != 4")
					->where("user", DEFAULT_USERS, "NOT IN")
					->getValue("vicidial_live_agents", "count(*)");		
				
				$cols 									= array(
					"channel as 'pc_channel'",
					"server_ip as 'pc_server_ip'",
					"channel_group as 'pc_channel_group'",
					"extension as 'pc_extension'",
					"parked_by as 'pc_parked_by'",
					"UNIX_TIMESTAMP(parked_time) as 'pc_parked_time'"
				);
				
				// caller id
				$cols 									= array(
					"callerid as 'vac_callerid'",
					"lead_id as 'vac_lead_id'",
					"phone_number as 'vac_phone_number'"
				);
				
				$rsltvCallerIDsFromVAC					= $astDB
					->where("campaign_id", $campaigns, "IN")
					->get("vicidial_auto_calls", NULL, $cols);
					
				// waiting for calls
				$cols 									= array(
					"vicidial_live_agents.extension as 'vla_extension'",
					"vicidial_live_agents.user as 'vla_user'",
					"vicidial_users.full_name as 'vu_full_name'",
					"vicidial_users.user_group as 'vu_user_group'",
					"vicidial_users.phone_login as 'vu_phone_login'",
					"vicidial_live_agents.conf_exten as 'vla_conf_exten'",
					"vicidial_live_agents.status as 'vla_status'",
					"vicidial_live_agents.comments as 'vla_comments'",
					"vicidial_live_agents.server_ip as 'vla_server_ip'",
					"vicidial_live_agents.call_server_ip as 'vla_call_server_ip'",
					"UNIX_TIMESTAMP(last_call_time) as 'last_call_time'",
					"UNIX_TIMESTAMP(last_update_time) as last_update_time",
					"UNIX_TIMESTAMP(last_call_finish) as last_call_finish",
					"vicidial_live_agents.campaign_id as 'vla_campaign_id'",
					"UNIX_TIMESTAMP(last_state_change) as 'last_state_change'",
					"vicidial_live_agents.lead_id as 'vla_lead_id'",
					"vicidial_live_agents.agent_log_id as 'vla_agent_log_id'",
					"vicidial_users.user_id as 'vu_user_id'",
					"vicidial_users.user as 'vu_user'",
					"vicidial_live_agents.callerid as 'vla_callerid'",
					"vicidial_agent_log.sub_status as 'vla_pausecode'", 
					"vicidial_campaigns.campaign_name as 'vla_campaign_name'"
				);
				
				$table									= "vicidial_live_agents, vicidial_users, vicidial_agent_log, vicidial_campaigns";				
				$rsltvNoCalls 							= $astDB
					->where("vicidial_campaigns.campaign_id", $campaigns, "IN")
					->where("vicidial_live_agents.campaign_id = vicidial_campaigns.campaign_id")
					->where("vicidial_live_agents.user = vicidial_users.user")
					->where("vicidial_live_agents.lead_id = 0")
					->where("vicidial_live_agents.user_level != 4")
					->where("vicidial_live_agents.user", DEFAULT_USERS, "NOT IN")
					->where("vicidial_live_agents.agent_log_id = vicidial_agent_log.agent_log_id")
					->orderBy("last_call_time")		
					->get($table, NULL, $cols);
				
				// live call
				$cols 									= array(
					"vicidial_live_agents.extension as 'vla_extension'",
					"vicidial_live_agents.user as 'vla_user'",
					"vicidial_users.full_name as 'vu_full_name'",
					"vicidial_users.user_group as 'vu_user_group'",
					"vicidial_users.phone_login as 'vu_phone_login'",
					"vicidial_live_agents.conf_exten as 'vla_conf_exten'",
					"vicidial_live_agents.status as 'vla_status'",
					"vicidial_live_agents.comments as 'vla_comments'",
					"vicidial_live_agents.server_ip as 'vla_server_ip'",
					"vicidial_live_agents.call_server_ip as 'vla_call_server_ip'",
					"UNIX_TIMESTAMP(last_call_time) as 'last_call_time'",
					"UNIX_TIMESTAMP(last_update_time) as 'last_update_time'",
					"UNIX_TIMESTAMP(last_call_finish) as 'last_call_finish'",
					"vicidial_live_agents.campaign_id as 'vla_campaign_id'",
					"UNIX_TIMESTAMP(last_state_change) as 'last_state_change'",
					"vicidial_live_agents.lead_id as 'vla_lead_id'",
					"vicidial_live_agents.agent_log_id as 'vla_agent_log_id'",
					"vicidial_users.user_id as 'vu_user_id'",
					"vicidial_users.user as 'vu_user'",
					"vicidial_live_agents.callerid as 'vla_callerid'",
					"vicidial_list.phone_number as 'vl_phone_number'",
					"vicidial_agent_log.sub_status as 'vla_pausecode'", 
					"vicidial_campaigns.campaign_name as 'vla_campaign_name'"
				);
				
				$table									= "vicidial_live_agents, vicidial_users, vicidial_list, vicidial_agent_log, vicidial_campaigns ";				
				$rsltvInCalls 							= $astDB
					->where("vicidial_campaigns.campaign_id", $campaigns, "IN")
					->where("vicidial_live_agents.campaign_id = vicidial_campaigns.campaign_id")
					->where("vicidial_live_agents.user = vicidial_users.user")
					->where("vicidial_live_agents.lead_id = vicidial_list.lead_id")
					->where("vicidial_live_agents.user_level != 4")
					->where("vicidial_live_agents.agent_log_id = vicidial_agent_log.agent_log_id")
					->orderBy("last_call_time")		
					->get($table, NULL, $cols);				   

				if ($query_OnlineAgents > 0) {				
					$dataInCalls 						= array();
					$dataCallerIDsFromVAC 				= array();
					$dataPCs 							= array();
					$dataNoCalls 						= array();
					
					foreach ($rsltvInCalls as $resultsInCalls){              
						array_push($dataInCalls, $resultsInCalls);
					}
											
					foreach ($rsltvCallerIDsFromVAC as $resultsCallerIDsFromVAc) {               
						array_push($dataCallerIDsFromVAC, $resultsCallerIDsFromVAc);
					}
					
					foreach ($resultsPCs as $resultsPC) {               
						array_push($dataPCs, $resultsPC);
					}       
										        
					foreach ($rsltvNoCalls as $resultsNoCalls) {               
						array_push($dataNoCalls, $resultsNoCalls);
					}                

					$data 								= array_merge($dataInCalls, $dataNoCalls);
					$apiresults 						= array(
						"result" 							=> "success", 
						//"query" 							=> $astDB->getLastQuery(),
						"data" 								=> $data, 
						"dataGo" 							=> $dataGo, 
						"parked" 							=> $dataPCs, 
						"callerids" 						=> $dataCallerIDsFromVAC
					);		
				} else {
					$apiresults 						= array(
						"result" 							=> "success", 
						"data" 								=> 0
					);		
				}
			}
		} else {
			$err_msg 									= error_handle("10001");
			$apiresults 								= array(
				"code" 										=> "10001", 
				"result" 									=> $err_msg
			);		
		}
	}
	
?>
