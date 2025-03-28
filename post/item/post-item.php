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
                if(isset($_FILES['item-image'])){
                    editItem($conn, $data, $_FILES['item-image']);
                } else {
                    editItem($conn, $data, null);
                }
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
            $checkIfDupeId = $conn->prepare("SELECT `item-id` FROM items WHERE `item-id` = ? AND `is-archived` = 0 AND `item-branch-location` = ?");
            $checkIfDupeId->bind_param("ss", $data['item-id'], $data['item-branch-location']);
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
                $getLastID = $conn->prepare("SELECT MAX(`item-id`) as last_id FROM items WHERE `item-branch-location` = ? AND `is-archived` = 0");
                $getLastID->bind_param("s", $data['item-branch-location']);
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

    function editItem($conn, $data, $image){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['item-id'])){
            $itemId = $data['pk'];
            $resultItem = $conn->query("SELECT * FROM `items` WHERE `pk` = $itemId");
            if ($resultItem && $resultItem->num_rows > 0) {
                $existingData = $resultItem->fetch_assoc();
                $updatedFields = [];
                foreach ($data as $key => $value) {
                    if (isset($existingData[$key]) && $existingData[$key] !== $value) {
                        // If the field exists in the existing data and doesn't match, update the database
                        $conn->query("UPDATE `items` SET `$key` = '{$value}' WHERE `pk` = $itemId");
                        $updatedFields[$key] = $value;
                    }
                }
                if ($image) {
                    // Compare new image with the existing image (if needed)
                    if ($image['name'] !== $existingData['item-image']) {
                        $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
                        $imageFileType = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
                        $newFilename = $timestamp . '_' . $data['item-id'] . 
                        str_replace(' ', '', $data['item-type']) . 
                        str_replace(' ', '', $data['item-name']) . '_' . 
                        str_replace(' ', '', $data['item-branch-location']) .
                        '.' . $imageFileType;
                        $targetDirectory = "../api/files/images/items/" . $newFilename; // Specify your target directory
    
                        // Upload the image
                        $uploadedImage = uploadImage($image, $data['item-id'], $data, $targetDirectory, $newFilename);
    
                        if ($uploadedImage) {
                            // Update the database with the new image path
                            $conn->query("UPDATE `items` SET `item-image` = '{$newFilename}' WHERE `pk` = $itemId");
                            $updatedFields['item-image'] = $newFilename;
                        } else {
                            $response = [
                                'success' => false,
                                'message' => 'Error uploading image.',
                            ];
                            echo json_encode($response);
                            exit;
                        }
                    }
                }
                if (!empty($updatedFields)) {
                    // If any field was updated, fetch the updated data
                    $result = $conn->query("SELECT * FROM `items` WHERE `pk` = $itemId");
                    $updatedData = $result->fetch_assoc();
                    $logExistingData = [];
                        foreach ($updatedFields as $key => $value) {
                            $logExistingData[$key] = $existingData[$key];
                        }
                    $logData = [
                        'id' => $data['item-id'],
                        'user' => $data['active-email'],
                        'reason' => '',
                        'existing' => $logExistingData,
                        'updated' => $updatedFields,
                    ];
                    createLog($conn, ['Item', 'Edit', 'Single'], $logData, $currentDateTime);
                    $instancePk = $existingData['pk'];
                    $resultInstance = $conn->query("SELECT * FROM `instances` WHERE `instance-item-pk` = '$instancePk'");
                    if ($resultInstance && $resultInstance->num_rows > 0) {
                        $existingInstance = $resultInstance->fetch_assoc();
                        foreach ($data as $key => $value) {
                            if (isset($existingInstance[$key]) && $existingInstance[$key] !== $value) {
                                // If the field exists in the existing data and doesn't match, update the database
                                $conn->query("UPDATE `instances` SET `$key` = '{$value}' WHERE `instance-item-pk` = '$instancePk'");
                                $updatedFields[$key] = $value;
                            }
                        }   
                    }
                    $response = [
                        'success' => true,
                        'message' => 'Item updated successfully.',
                        'user' => $updatedData,
                        'pk' => $instancePk
                    ];
                } else {
                    // If all fields match, return an error
                    $response = [
                        'success' => false,
                        'message' => 'Values are the same.',
                        'user' => $existingData
                    ];
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
            $result = $conn->query("SELECT * FROM `items` WHERE `pk` = $itemID");
            if ($result && $result->num_rows > 0) {
                $conn->query("UPDATE `items` SET `is-archived` = 1, `date-archived` = '$currentDateTime' WHERE `pk` = $itemID");
                createLog($conn, ['Item', 'Archive', 'Single'], $data, $currentDateTime);
                $updatedResult = $conn->query("SELECT * FROM `items` WHERE `pk` = $itemID");
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
            $primary = isset($data['prim']) ? (int) $data['prim'] : '';
            $branch = isset($data['branch']) ? $data['branch'] : '';
            $result = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemID AND `is-archived` = 1");
            $itemIDResult = $result->fetch_assoc()['item-id'];
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                /*if($itemID === $row['item-id']){
                    $updateSql = $conn->query("UPDATE `items` SET `is-archived` = 0, `date-archived` = NULL WHERE `item-id` = $itemID AND `pk` = $primary ");
                    
                    $row['is-archived'] = 0;
                    
                    $response = [
                        'success' => true,
                        'data' => $row
                    ];
                    echo json_encode($response);
                    return;
                }*/
                
                // Check if the current ID already exists in the database
                $checkResult = $conn->query("SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $itemIDResult AND `item-branch-location` = '$branch' AND `is-archived` = 0");
                if ($checkResult->fetch_assoc()['count'] < 1) {
                    $updateSql = "UPDATE `items` SET `is-archived` = 0 WHERE `item-id` = $itemID AND `pk` = $primary AND `item-branch-location` = '$branch'";
                    createLog($conn, ['item', 'Retrieve', 'Single'], $data, $currentDateTime);
                    $conn->query($updateSql);
                    $response = [
                        'success' => true,
                        'data' => $row
                    ];
                    echo json_encode($response);
                } else {
                    $newID = 1;
                    $allIdsSql = "SELECT `item-id` FROM `items` WHERE `is-archived` = 0 AND `item-branch-location` = '$branch' ORDER BY `item-id` ASC";
                    $allIdsResult = $conn->query($allIdsSql);

                    $existingIds = [];
                    while ($row = $allIdsResult->fetch_assoc()) {
                        $existingIds[] = (int) $row['item-id']; // Store all existing IDs in an array
                    }
                    while (in_array($newID, $existingIds)) {
                        $newID++;
                    }
                    // Now that we have the next available ID, update the entry
                    $updateSql = "UPDATE `items` SET `item-id` = $newID, `is-archived` = 0 WHERE `pk` = $primary AND `is-archived` = 1";
                    $conn->query($updateSql);
                    createLog($conn, ['item', 'Retrieve', 'Single'], $data, $currentDateTime);

                    $response = [
                        'existingIDs' => $existingIds,
                        'newID' => $newID,
                        'primary' => $primary,
                        'success' => true,
                    ];
                    echo json_encode($response);
                    /*
                    while (true) {
                        $checkSql = "SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $newID AND `is-archived` = 0";
                        $checkResult = $conn->query($checkSql);
    
                        if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                            // Update the entry with the new ID and set isArchived to 0
                            $updateSql = "UPDATE `items` SET `item-id` = $newID, `is-archived` = 0 WHERE `pk` = $primary";
                            $conn->query($updateSql);
                            $row['item-id'] = $newID;
                            $row['is-archived'] = 0;
    
                            $response = [
                                'newID' => $newID,
                                'success' => true,
                                'data' => $row
                            ];
                            echo json_encode($response);
                            break;
                        } else {
                            $newID++;
                        }
                    }*/
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
                $itemBranchLoc = $itemID['bjmpBranch'];
                
                $result = $conn->query("SELECT * FROM `items` WHERE `item-id` = $itemMainIDs AND `pk` = $itemMainPrimaryIDs AND `item-branch-location` = '$itemBranchLoc' AND `is-archived` = 1");
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $itemIDResult = $row['item-id'];
                    /*if($itemMainIDs == $row['item-id']){
                        $updateSql = $conn->query("UPDATE `items` SET `is-archived` = 0, `date-archived` = NULL WHERE `item-id` = $itemMainIDs AND `pk` = $itemMainPrimaryIDs");
                        $row['is-archived'] = 0;
                        
                    } else {*/
                        $checkResult = $conn->query("SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $itemIDResult AND `is-archived` = 0");
                        if ($checkResult->fetch_assoc()['count'] < 1) {
                            $updateSql = "UPDATE `items` SET `is-archived` = 0 WHERE `item-id` = $itemMainIDs AND `pk` = $itemMainPrimaryIDs";
                            $conn->query($updateSql);
                            $row['is-archived'] = 0;

                            $response = [
                                'success' => true,
                                'data' => $row
                            ];
                            $responses[] = $response;
                        } else {
                            $newID = 1;
                            $allIdsSql = "SELECT `item-id` FROM `items` WHERE `is-archived` = 0 AND `item-branch-location` = '$itemBranchLoc' ORDER BY `item-id` ASC";
                            $allIdsResult = $conn->query($allIdsSql);
                            $existingIds = [];
                            while ($row = $allIdsResult->fetch_assoc()) {
                                $existingIds[] = (int) $row['item-id']; // Store all existing IDs in an array
                            }
                            while (in_array($newID, $existingIds)) {
                                $newID++;
                            }
                            $updateSql = "UPDATE `items` SET `item-id` = $newID, `is-archived` = 0 WHERE `pk` = $itemMainPrimaryIDs AND `is-archived` = 1";
                            $conn->query($updateSql);

                            $response = [
                                'success' => true,
                                'data' => $row
                            ];
                            $responses[] = $response;
                            /*
                            while (true) {
                                $checkSql = "SELECT COUNT(*) as count FROM `items` WHERE `item-id` = $newID AND `pk` = $itemMainPrimaryIDs";
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
                            }*/
                        }
                    //}
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