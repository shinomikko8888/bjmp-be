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

    if(!function_exists('createTransaction')){
        function createTransaction($conn, $action, $data, $date, $filepath){
            if($data){
                if($action === 'Load'){
                    $insertTransaction = $conn->prepare("INSERT INTO `transactions` (
                        `transaction-id`,
                        `transaction-created-at`,
                        `transaction-user`,
                        `transaction-type`,
                        `transaction-branch-location`,
                        `transaction-amount`,
                        `transaction-pdl`,
                        `transaction-pdl-pk`,
                        `transaction-lender`,
                        `transaction-receipt`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" );
                    $insertTransaction->bind_param("issssdiiss", $data['id'], $date, 
                    $data['invUser'], $action, $data['branch'], $data['amtLoad'], 
                    $data['pdlId'], $data['pk'], $data['cred'], $filepath);
                    $insertTransaction->execute();
                }
                else if ($action === 'Purchase'){
                    $items = [];
                    foreach ($data['bought'] as $item) {
                        $items[] = [
                            'id' => $item['commodity-item-id'], 
                            'type' => $item['commodity-type'], 
                            'name' => $item['commodity-name'], 
                            'quantity' => $item['commodity-quantity'], 
                            'price' => $item['commodity-price'],
                        ];
                    }
                    $itemsJson = json_encode($items);
                    $insertTransaction = $conn->prepare("INSERT INTO `transactions` (
                        `transaction-id`,
                        `transaction-created-at`,
                        `transaction-user`,
                        `transaction-type`,
                        `transaction-branch-location`,
                        `transaction-amount`,
                        `transaction-pdl`,
                        `transaction-pdl-pk`,
                        `transaction-items`,
                        `transaction-receipt`
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insertTransaction->bind_param("issssdiiss", $data['id'], $date, $data['invUser'], $action, 
                    $data['branch'], $data['price'], $data['pdlId'], $data['pk'],$itemsJson, $filepath);
                    $insertTransaction->execute();

                }
            }
            else{
                echo 'Data not found';
            }
        }
    }
?>