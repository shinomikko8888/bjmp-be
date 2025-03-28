<?php
    function manageInstance($conn, $data, $type){
        switch($type){
            case 'add-instance':
                addInstance($conn, $data);
                break;
            case 'delete-instance':
                $method = $data['method'];
                deleteInstance($conn, $data, $method);
                break;
            default:
                break;
        }
    }
    
    function addInstance($conn, $data){
        $currentDateTime = date('Y-m-d H:i:s');
        if($data){
            $checkIfDupeId = $conn->prepare("SELECT `instance-id` FROM instances WHERE `instance-id` = ? AND `instance-branch-location` = ?");
            $checkIfDupeId->bind_param("ss", $data['instance-id'], $data['instance-branch-location']);
            $checkIfDupeId->execute();
            $dupeIdCheck = $checkIfDupeId->get_result();
            $dupeIdData = $dupeIdCheck->fetch_assoc();
            if ($dupeIdData) {
                $response = [
                    'success' => false,
                    'message' => 'Item already exists in the database.',
                    'user' => null
                ];
            }
            else {
                $getLastID = $conn->prepare("SELECT MAX(`instance-id`) as last_id FROM instances WHERE `instance-item-pk` = ? AND `instance-branch-location` = ?");
                $getLastID->bind_param("ss", $data['instance-item-pk'], $data['instance-branch-location']);
                $getLastID->execute();
                $lastIDResult = $getLastID->get_result();
                $lastIDData = $lastIDResult->fetch_assoc();
                $newID = $lastIDData['last_id'] + 1;
                $exp = $data['instance-expiration-date']; // Get the expiration date

                // Check if the expiration date is set and if it equals 'N/A'
                if (isset($exp) && $exp === 'N/A') {
                    $exp = null; // Set expiration date to null if it is 'N/A'
                }
                            
                $insert = $conn->prepare("INSERT INTO instances (
                        `instance-id`, 
                        `instance-item-pk`,
                        `instance-remaining-stock`,
                        `instance-branch-location`,
                        `instance-created-at`,
                        `instance-expiration-date`,
                        `is-archived`) VALUES (?, ?, ?, ?, ?, ?, 0)");
        
                $insert->bind_param("isisss", $newID, $data['instance-item-pk'], $data['instance-remaining-stock'], $data['instance-branch-location'],
                    $data['instance-date-time'], $exp);
                            $logData = [
                                'id' => $newID,
                                'user' => $data['active-email'],
                                'reason' => '',
                            ];
                if ($insert->execute()) {
                    createLog($conn, ['Instance', 'Create', 'Single'], $logData, $currentDateTime);
                    $response = [
                        'success' => true,
                        'message' => 'Entry added successfully.',
                        'user' => [
                            'item-id' => $newID,
                        ]
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Error adding instance to the database.',
                        'user' => null
                    ];
                }
                
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Invalid data provided.',
                'user' => null
            ];
        }
        echo json_encode($response);
    }
    function deleteInstance($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $instanceID = $data['id'];
            $primary = isset($data['prim']) ? $data['prim'] : '';
            
            $result = $conn->query("DELETE FROM `instances` WHERE `instance-id` = $instanceID AND `pk` = $primary");
            createLog($conn, ['Instance', 'Delete', 'Single'], $data, $currentDateTime);
            if($result){
                    $response = [
                        'success' => true,
                        'message' => 'item deleted successfully.'
                    ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Error deleting item.'
                ];
            }
        } 
        else if (isset($data['mult']) && $method === 'multiple'){
            $instanceIDs = json_decode($data['mult'], true);
            
            foreach($instanceIDs as $instanceID){
                $instanceMainIDs = $instanceID['pk'];
                $instanceMainPrimaryIDs = $instanceID['dbpk'];
                $result = $conn->query("DELETE FROM `instances` WHERE `instance-id` = $instanceMainIDs AND `pk` = $instanceMainPrimaryIDs");
            }
            createLog($conn, ['Instance', 'Delete', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'item deleted successfully.'
            ];
        }
        
        else {
            $response = [
                'success' => false,
                'message' => 'item ID not provided.'
            ];
            
        }
        echo json_encode($response);
        
    }
?>