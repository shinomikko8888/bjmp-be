<?php
    if(!function_exists('createLog')){
        function createLog($conn, $action, $data, $date){
            if($data){
                $getLastLogId = $conn->prepare("SELECT MAX(`log-id`) as last_id FROM `logs`");
                $getLastLogId->execute();
                $lastIdResult = $getLastLogId->get_result();
                $lastIdData = $lastIdResult->fetch_assoc();
                $newId = $lastIdData['last_id'] + 1;
                $details = [
                    'id'=> $data[$action[2] === 'Single' ? 'id' : 'mult'],
                    'existingFields' => isset($data['existing']) ? $data['existing'] : [],
                    'updatedFields' => isset($data['updated']) ? $data['updated'] : [],
                ];
                $details = array_filter($details, function($value) {
                    return !empty($value);
                });            
                $detailsJson = json_encode($details);
                $type = strtolower($action[0]);
    
                $insertLog = $conn->prepare("INSERT INTO `logs` (
                    `log-id`,
                    `log-date`,
                    `log-user`,
                    `log-action`,
                    `log-$type-details`,
                    `log-reason`    
                    ) VALUES(?, ?, ?, ?, ?, ?)");
                $insertLog->bind_param("isssss", 
                $newId, $date, $data['user'], $action[1], $detailsJson, $data['reason']);
                $insertLog->execute();
    
                $details = [];
            }
        }
    }
?>