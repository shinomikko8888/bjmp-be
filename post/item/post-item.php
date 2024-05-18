<?php
    include "./post/misc/post-misc.php";
    include "./get/misc/get-misc.php";
    include "./post/log/post-log.php";
function manageItem($conn, $data, $type){
        switch($type){
            case 'add-item':
                if(isset($_FILES['item-image'])){
                    addItem($conn, $data, $_FILES['item-image']);
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Image not provided.',
                        'user' => null
                    ];
                    echo json_encode($response);
                }
                break;
            case 'edit-item':
                editItem($conn, $data);
                break;
            case 'archive-item':
                $method = $data['method'];
                archiveItem($conn, $data, $method);
                break;
            case 'retrieve-item':
                $method = $data['method'];
                retrieveItem($conn, $data, $method);
                break;
            case 'delete-item':
                $method = $data['method'];
                deleteItem($conn, $data, $method);
                break;
            default:
                break;
        }
    }
function addItem($conn, $data, $image){
    $currentDateTime = date('Y-m-d H:i:s');
    if($data && $image){
            $checkIfDupeId = $conn->prepare("SELECT `item-id` FROM items WHERE `item-id` = ? AND `is-archived` = 0");
            $checkIfDupeId->bind_param("s", $data['item-id']);
            $checkIfDupeId->execute();
            $dupeIdCheck = $checkIfDupeId->get_result();
            $dupeIdData = $dupeIdCheck->fetch_assoc();
    
            if ($dupeIdData) {
                $response = [
                    'success' => false,
                    'message' => 'Item already exists in the database.',
                    'user' => null
                ];
            } else {
                $getLastID = $conn->prepare("SELECT MAX(`item-id`) as last_id FROM items");
                $getLastID->execute();
                $lastIDResult = $getLastID->get_result();
                $lastIDData = $lastIDResult->fetch_assoc();
                $newID = $lastIDData['last_id'] + 1;
    
                // Upload the image
                $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
                $imageFileType = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
                $newFilename = $timestamp . '_' . $newID . 
                   str_replace(' ', '', $data['item-type']) . 
                   str_replace(' ', '', $data['item-name']) . '_' . 
                   str_replace(' ', '', $data['item-branch-location']) .
                   '.' . $imageFileType;
                $targetDirectory = "../api/files/images/items/" . $newFilename; // Specify your target directory
                
                $uploadedImage = uploadImage($image, $newID, $data, $targetDirectory, $newFilename);
                if (!$uploadedImage) {
                    $response = [
                        'success' => false,
                        'message' => 'Error uploading image.',
                        'user' => null
                    ];
                    echo json_encode($response);
                    exit;
                }
                else{
                    $insert = $conn->prepare("INSERT INTO items (
                        `item-id`, 
                        `item-type`,
                        `item-name`,
                        `item-category`,
                        `item-price`,
                        `item-critical-threshold`,
                        `item-branch-location`,
                        `item-image`,
                        `is-archived`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        
                    $insert->bind_param("isssiiss", $newID, $data['item-type'], $data['item-name'], $data['item-category'], $data['item-price'],
                    $data['item-critical-threshold'], $data['item-branch-location'], $targetDirectory);
                            $logData = [
                                'id' => $newID,
                                'user' => $data['active-email'],
                                'reason' => '',
                            ];

                    if ($insert->execute()) {
                        createLog($conn, ['Item', 'Create', 'Single'], $logData, $currentDateTime);
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
                            'message' => 'Error adding item to the database.',
                            'user' => null
                        ];
                    }
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

    function editItem($conn, $data){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['item-id'])){
            $itemId = $data['item-id'];
            $resultItem = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemId");
            if ($resultItem && $resultItem->num_rows > 0) {
                $existingData = $resultItem->fetch_assoc();
                $updatedFields = [];
                foreach ($data as $key => $value) {
                    if (isset($existingData[$key]) && $existingData[$key] !== $value) {
                        // If the field exists in the existing data and doesn't match, update the database
                        $conn->query("UPDATE `items` SET `$key` = '{$value}' WHERE `item-id` = $itemId");
                        $updatedFields[$key] = $value;
                    }
                }
                if (!empty($updatedFields)) {
                    // If any field was updated, fetch the updated data
                    $result = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemId");
                    $updatedData = $result->fetch_assoc();
                    $logExistingData = [];
                        foreach ($updatedFields as $key => $value) {
                            $logExistingData[$key] = $existingData[$key];
                        }
                    $logData = [
                        'id' => $itemId,
                        'user' => $data['active-email'],
                        'reason' => '',
                        'existing' => $logExistingData,
                        'updated' => $updatedFields,
                    ];
                    createLog($conn, ['Item', 'Edit', 'Single'], $logData, $currentDateTime);
                    $response = [
                        'success' => true,
                        'message' => 'Item updated successfully.',
                        'user' => $updatedData
                    ];
                } else {
                    // If all fields match, return an error
                    $response = [
                        'success' => false,
                        'message' => 'Values are the same.',
                        'user' => $existingData
                    ];
                }
                $instanceType = $existingData['item-type'];
                $instanceName = $existingData['item-name'];
                $instanceBranch = $existingData['item-branch-location'];
                $resultInstance = $conn->query("SELECT * FROM `instances` WHERE `instance-type` = '$instanceType' AND `instance-name` = '$instanceName' AND `instance-branch-location` = '$instanceBranch'");
                if ($resultInstance && $resultInstance->num_rows > 0) {
                    $existingInstance = $resultInstance->fetch_assoc();
                    foreach ($data as $key => $value) {
                        if (isset($existingInstance[$key]) && $existingInstance[$key] !== $value) {
                            // If the field exists in the existing data and doesn't match, update the database
                            $conn->query("UPDATE `instances` SET `$key` = '{$value}' WHERE `instance-type` = $instanceType AND `instance-name` = $instanceName AND `instance-branch-location` = $instanceBranch");
                            $updatedFields[$key] = $value;
                        }
                    }   
                }
        } echo json_encode($response);
    } else {
        // No user found with the given user ID
        $response = [
            'success' => false,
            'message' => 'Item not found.'
        ];
        echo json_encode($response);
    }
    }

    function archiveItem($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $itemID = $data['id'];
            $result = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemID");
            if ($result && $result->num_rows > 0) {
                $conn->query("UPDATE `items` SET `is-archived` = 1, `date-archived` = '$currentDateTime' WHERE `item-id` = $itemID");
                createLog($conn, ['Item', 'Archive', 'Single'], $data, $currentDateTime);
                $updatedResult = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemID");
                $updatedData = $updatedResult->fetch_assoc();
                $response = [
                    'success' => true,
                    'message' => 'Item archived successfully.',
                    'user' => $updatedData
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Item not found.'
                ];
            }
            
        }
        else if(isset($data['mult']) && $method === 'multiple'){
            $itemIDs = json_decode($data['mult'], true);
            foreach($itemIDs as $itemID){
                $itemMainIDs = $itemID['pk'];
                $itemMainPrimaryIDs = $itemID['dbpk'];
                $conn->query("UPDATE `items` SET `is-archived` = 1, `date-archived` = '$currentDateTime' WHERE `item-id` = $itemMainIDs AND `pk` = $itemMainPrimaryIDs");
            }
            createLog($conn, ['item', 'Archive', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'items archived successfully.',
            ];
        }
        echo json_encode($response);
    }
    function retrieveItem($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $itemID = $data['id'];
            $primary = isset($data['prim']) ? $data['prim'] : '';
            $result = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemID AND `pk` = $primary AND `is-archived` = 1");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                if($itemID === $row['item-id']){
                    $updateSql = $conn->query("UPDATE `items` SET `is-archived` = 0, `date-archived` = NULL WHERE `item-id` = $itemID");
                    createLog($conn, ['item', 'Retrieve', 'Single'], $data, $currentDateTime);
                    $row['is-archived'] = 0;
                    
                    $response = [
                        'success' => true,
                        'data' => $row
                    ];
                    echo json_encode($response);
                    return;
                }
                
                // Check if the current ID already exists in the database
                $checkResult = $conn->query("SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $itemID");
    
                if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                    $updateSql = "UPDATE `items` SET `is-archived` = 0 WHERE `item-id` = $itemID";
                    $conn->query($updateSql);
                    $row['is-archived'] = 0;
    
                    $response = [
                        'success' => true,
                        'data' => $row
                    ];
                    echo json_encode($response);
                } else {
                    // Cases 2 and 3: Handle the previous entry's ID and current ID accordingly
                    if ($itemID > $row['previousEntryID']) {
                        $newID = $row['previousEntryID'] + 1;
                    } else {
                        $newID = $itemID + 1;
                    }
    
                    // Find the next available ID
                    while (true) {
                        $checkSql = "SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $newID";
                        $checkResult = $conn->query($checkSql);
    
                        if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                            // Update the entry with the new ID and set isArchived to 0
                            $updateSql = "UPDATE `items` SET `item-id` = $newID, `is-archived` = 0 WHERE `pk` = $primary";
                            $conn->query($updateSql);
                            $row['item-id'] = $newID;
                            $row['is-archived'] = 0;
    
                            $response = [
                                'success' => true,
                                'data' => $row
                            ];
                            echo json_encode($response);
                            break;
                        } else {
                            $newID++;
                        }
                    }
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Entry not found or not archived'
                ];
                echo json_encode($response);
            }
        } 
        else if(isset($data['mult']) && $method === 'multiple'){
            $itemIDs = json_decode($data['mult'], true);
            
            foreach($itemIDs as $itemID){
                $itemMainIDs = $itemID['pk'];
                $itemMainPrimaryIDs = $itemID['dbpk'];
                
                $result = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemMainIDs AND `pk` = $itemMainPrimaryIDs AND `is-archived` = 1");
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    
                    if($itemMainIDs == $row['item-id']){
                        $updateSql = $conn->query("UPDATE `items` SET `is-archived` = 0, `date-archived` = NULL WHERE `item-id` = $itemMainIDs");
                        $row['is-archived'] = 0;
                        
                    } else {
                        // Check if the current ID already exists in the database
                        $checkResult = $conn->query("SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $itemMainIDs");
                        
                        if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                            $updateSql = "UPDATE `items` SET `is-archived` = 0 WHERE `item-id` = $itemMainIDs";
                            $conn->query($updateSql);
                            $row['is-archived'] = 0;

                            $response = [
                                'success' => true,
                                'data' => $row
                            ];

                            $responses[] = $response;
                        } else {
                            // Cases 2 and 3: Handle the previous entry's ID and current ID accordingly
                            if ($itemMainIDs > $row['previousEntryID']) {
                                $newID = $row['previousEntryID'] + 1;
                            } else {
                                $newID = $itemMainIDs + 1;
                            }

                            // Find the next available ID
                            while (true) {
                                $checkSql = "SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $newID";
                                $checkResult = $conn->query($checkSql);

                                if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                                    // Update the entry with the new ID and set isArchived to 0
                                    $updateSql = "UPDATE `items` SET `item-id` = $newID, `is-archived` = 0 WHERE `pk` = $itemMainPrimaryIDs";
                                    $conn->query($updateSql);
                                    $row['item-id'] = $newID;
                                    $row['is-archived'] = 0;

                                    $response = [
                                        'success' => true,
                                        'data' => $row
                                    ];

                                   
                                    break;
                                } else {
                                    $newID++;
                                }
                            }
                        }
                    }
                }
            }
            createLog($conn, ['Item', 'Retrieve', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'Item retrieved successfully.',
            ];

            echo json_encode($response);
        }
        
    }

    function deleteItem($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $itemID = $data['id'];
            $primary = isset($data['prim']) ? $data['prim'] : '';
            
            $result = $conn->query("DELETE FROM `items` WHERE `item-id` = $itemID AND `pk` = $primary AND `is-archived` = 1");
            createLog($conn, ['Item', 'Delete', 'Single'], $data, $currentDateTime);
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
            $itemIDs = json_decode($data['mult'], true);
            
            foreach($itemIDs as $itemID){
                $itemMainIDs = $itemID['pk'];
                $itemMainPrimaryIDs = $itemID['dbpk'];
                $result = $conn->query("DELETE FROM `items` WHERE `item-id` = $itemMainIDs AND `pk` = $itemMainPrimaryIDs AND `is-archived` = 1");
            }
            createLog($conn, ['Item', 'Delete', 'Multiple'], $data, $currentDateTime);
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