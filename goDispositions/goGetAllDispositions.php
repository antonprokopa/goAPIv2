<?php
 /**
 * @file 		goGetAllDispositions.php
 * @brief 		API for Dispositions
 * @copyright 	Copyright (c) 2018 GOautodial Inc.
 * @author		Demian Lizandro A. Biscocho
 * @author     	Jeremiah Sebastian Samatra
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
	} elseif (empty($campaigns) || is_null($campaigns)) {
		$err_msg 										= error_handle("40001");
        $apiresults 									= array(
			"code" 											=> "40001",
			"result" 										=> $err_msg
		);
    } else {
        $astDB->where('user_group', $log_group);
        $allowed_camps                                  = $astDB->getOne('vicidial_user_groups', 'allowed_campaigns');
        $allowed_campaigns                              = $allowed_camps['allowed_campaigns'];
		$allowed_campaigns = explode(" ", trim($allowed_campaigns));
        
		// check if goUser and goPass are valid
		$fresults										= $astDB
			->where("user", $goUser)
			->where("pass_hash", $goPass)
			->getOne("vicidial_users", "user,user_level");
		
		$goapiaccess									= $astDB->getRowCount();
		$userlevel										= $fresults["user_level"];
		
		if ($goapiaccess > 0 && $userlevel > 7) {    
			if (is_array($campaigns)) {			
				$cols 									= array(
					"status", 
					"status_name", 
					"campaign_id"
				);
			
				$cols2 = array("status", "status_name");
                
				if (!preg_match("/ALL-CAMPAIGN/", $allowed_campaigns)) {
                    $astDB->where("campaign_id", $allowed_campaigns, "IN");
                }
				$astDB->groupBy("status");
				$astDB->orderBy("status", "asc");			
				$result 								= $astDB->get("vicidial_campaign_statuses", NULL, $cols);
				
                $astDB->orderBy("status", "asc");
                $result2                                = $astDB->get("vicidial_statuses", NULL, $cols2);
                
				if (!preg_match("/ALL-CAMPAIGN/", $allowed_campaigns)) {
                    $astDB->where("campaign_id", $allowed_campaigns, "IN");
                }
				$astDB->orderBy("status", "asc");			
				$result3 								= $astDB->get("vicidial_campaign_statuses", NULL, $cols);
		
				if ($astDB->count > 0) {
					//GET CAMPAIGN STATUSES
					foreach ($result as $fresults) {
						$dataStat[] 					= $fresults["campaign_id"];			
						$dataStatName[] 				= $fresults["status"];
						$dataCampID[] 					= $fresults["status_name"];
					}
					
					//GET SYSTEM STATUSES
					foreach ($result2 as $fresults) {
                        $dataStat[]                     = $fresults["status"];
                        $dataStatName[]                 = $fresults["status_name"];
                    }
                    
					foreach ($result3 as $fresults) {
                        $cCamp                          = $fresults["campaign_id"];
                        $cStatus                        = $fresults["status"];
                        $cStatusName                    = $fresults["status_name"];
                        $custom_dispo[$cStatus][]       = $cCamp;
					}

					$apiresults 						= array(
						"result" 						=> "success", 
						"campaign_id" 					=> $dataCampID, 
						"status_name" 					=> $dataStatName, 
						"status" 						=> $dataStat,
                        "custom_dispo"                  => $custom_dispo
					);			
				}	 		
			} else {
				$err_msg 								= error_handle("10108", "status. No campaigns available");
				$apiresults								= array(
					"code" 									=> "10108", 
					"result" 								=> $err_msg
				);
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

