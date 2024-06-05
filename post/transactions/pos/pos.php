<?php
    include './post/transactions/generate-receipt.php';
    include './post/log/post-log.php';
    function manageCommodity($conn, $data, $action){
        switch ($action) {
            case 'add-commodity':
                addCommodity($conn, $data);
                break;
            case 'delete-commodity':
                deleteCommodity($conn, $data);
                break;
            default:
                break;
        }
    }
    function addCommodity($conn, $data){
        if($data){
            $getLastID = $conn->prepare("SELECT MAX(`commodity-id`) as last_id FROM commodities WHERE `commodity-user` = ?");
            $getLastID->bind_param("s", $data['active-email']);
            $getLastID->execute();
            $lastIDResult = $getLastID->get_result();
            $lastIDData = $lastIDResult->fetch_assoc();
            $newID = $lastIDData['last_id'] !== null ? $lastIDData['last_id'] + 1  : 1;

            $getSelectedItem = $conn->prepare("SELECT * FROM commodities WHERE `commodity-item-id` = ? 
            AND `commodity-type` = ? AND `commodity-name` = ? AND `commodity-user` = ? ");
            $getSelectedItem->bind_param("ssss", $data['commodity-item-id'], $data['commodity-type'], $data['commodity-name'],
                $data['active-email']);
            $getSelectedItem->execute();
            $selectedItemResult = $getSelectedItem->get_result();
            if ($selectedItemResult->num_rows > 0) {
                $update = $conn->prepare("UPDATE commodities SET `commodity-quantity` = `commodity-quantity` + 1 
                WHERE `commodity-item-id` = ? AND `commodity-type` = ? AND `commodity-name` = ? AND `commodity-user` = ? AND `commodity-branch-location` = ?");
                $update->bind_param("sssss", $data['commodity-item-id'], $data['commodity-type'], $data['commodity-name'],
                $data['active-email'], $data['commodity-branch-location']);
                $update->execute(); 
                if ($update->affected_rows > 0) {
                    $response = [
                        'status' => true,
                        'type' => 'Update',
                    ];
                } else {
                    $response = [
                        'status' => false,
                        'type' => 'Update',
                    ];
                }
                
                
            }
            else{
                $insert  = $conn->prepare("INSERT INTO commodities (
                    `commodity-id`,
                    `commodity-item-id`,
                    `commodity-type`,
                    `commodity-name`,
                    `commodity-quantity`,
                    `commodity-remaining-stock`,
                    `commodity-price`,
                    `commodity-branch-location`,
                    `commodity-image`,
                    `commodity-user`
                ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
                $insert->bind_param("iisssssss", $newID, $data['commodity-item-id'], 
                $data['commodity-type'], $data['commodity-name'], $data['commodity-remaining-stock'], $data['commodity-price'],
                $data['commodity-branch-location'], $data['commodity-image'], $data['active-email']);
                $insert->execute(); // Execute the insert statement
                if ($insert->affected_rows > 0) {
                    $response = [
                        'status' => true,
                        'type' => 'Add',
                    ];
                } else {
                    $response = [
                        'status' => false,
                        'type' => 'Add',
                    ];
                }

            }
        }
        echo json_encode($response);
    }

    function deleteCommodity($conn, $data){
        if($data){
            
            $delete = $conn->prepare("DELETE FROM commodities WHERE `pk` = ? 
            AND `commodity-type` = ? AND `commodity-name` = ? AND `commodity-user` = ?");
            $delete->bind_param("isss", $data['commodity-item-id'], $data['commodity-type'], 
            $data['commodity-name'], $data['active-email']);
            if ($delete->execute()) {
                $response = [
                    'status' => true,
                    'type' => 'Delete',
                ];
            } else {
                $response = [
                    'status' => false,
                    'type' => 'Delete',
                ];
            }
            
        }
        echo json_encode($response);
    }

    function handlePurchase($conn, $data){
        $currentDateTime = date('Y-m-d H:i:s');
        if($data){
            $getLastID = $conn->prepare("SELECT MAX(`transaction-id`) as last_id FROM transactions WHERE `transaction-branch-location` = ?");
            $getLastID->bind_param("s", $data['pdl-data']['pdl-branch-location']);
            $getLastID->execute();
            $lastIDResult = $getLastID->get_result();
            $lastIDData = $lastIDResult->fetch_assoc();
            $newID = $lastIDData['last_id'] !== null ? $lastIDData['last_id'] + 1 : 1;

            $timestamp = round(microtime(true) * 1000);
            $fileName = $timestamp . '_' . sprintf("%011d", $newID) .
            str_replace(' ', '', $data['purchase-type']) . '_' .
            str_replace(' ', '', $data['pdl-data']['pdl-branch-location']) . '.pdf';
            $pdfFilePath = __DIR__ . '\..\..\..\files\docs\receipts\purchase/' . $fileName;
            $transactionData = [
                $id = sprintf("%011d", $newID),
                $involvedUser = $data['active-email'],
                $pdlId = $data['pdl-data']['pdl-id'],
                $oldBalance = $data['pdl-data']['pdl-balance'],
                $typeOfTransaction = $data['purchase-type'],
                $transactionBranchLocation = $data['pdl-data']['pdl-branch-location'],
                $itemsBought = $data['commodity-data'],
                $totalPrice = $data['total-price']
            ];
            #Balance Update
            $updateBalance = $conn->prepare("UPDATE `pdls` SET `pdl-balance` = `pdl-balance` - ? WHERE `pdl-id` = ? AND `pdl-branch-location` = ?");
            $updateBalance->bind_param("iis", $data['total-price'], $data['pdl-data']['pdl-id'], $data['pdl-data']['pdl-branch-location']);
            if($updateBalance->execute()){
                #Log Generation
                createTransaction($conn, 'Purchase', $transactionData, $currentDateTime, $fileName);
                #Receipt Generation
                generatePurchaseReceipt($data, $currentDateTime, $newID, $pdfFilePath);
                #Stock Update
                foreach ($data['commodity-data'] as $item) {
                    $itemType = $item['commodity-type'];
                    $itemName = $item['commodity-name'];
                    $itemQty = $item['commodity-quantity'];
                    $itemBranchLocation = $item['commodity-branch-location'];
                    while ($itemQty > 0) {
                        $selectInstance = $conn->prepare("SELECT * FROM `instances` WHERE `instance-type` = ? AND 
                        `instance-name` = ? AND `instance-branch-location` = ?");
                        $selectInstance->bind_param('sss', $itemType, $itemName, $itemBranchLocation);
                        $selectInstance->execute();
                        $result = $selectInstance->get_result();
                        if ($result->num_rows > 0) {
                            $instanceData = $result->fetch_assoc();
                            $remainingStock = $instanceData['instance-remaining-stock'];
                            $instanceId = $instanceData['instance-id'];
                            if ($itemQty >= $remainingStock) {
                                $itemQty -= $remainingStock;
                
                                $deleteInstance = $conn->prepare("DELETE FROM `instances` WHERE `instance-id` = ?");
                                $deleteInstance->bind_param('i', $instanceId);
                                $deleteInstance->execute();
                                $deleteInstance->close();
                            } else {
                                $newRemainingStock = $remainingStock - $itemQty;
                                $itemQty = 0;
                
                                $updateInstance = $conn->prepare("UPDATE `instances` SET `instance-remaining-stock` = ? WHERE `instance-id` = ?");
                                $updateInstance->bind_param('ii', $newRemainingStock, $instanceId);
                                $updateInstance->execute();
                                $updateInstance->close();
                            } 
                        } else {
                            
                            break; 
                        }
                    }
                }
                #Cleaner
                $removeAllPos = $conn->prepare("DELETE FROM `commodities` WHERE `commodity-user` = ?");
                $removeAllPos->bind_param("s", $data['active-email']);
                $removeAllPos->execute();
                
                $response = [
                    'success' => true,
                    'filepath' => $fileName,
                ];
                
            } else {
                $response = [
                    'success' => false,
                ];
            }
        }
        echo json_encode($response);
    }
?>