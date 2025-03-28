<?php
    include "./post/misc/post-misc.php";
    include "./get/misc/get-misc.php";
    include "./post/log/post-log.php";
    function managePdl($conn, $data, $type){
        switch($type){
            case 'add-pdl':
                if(isset($_FILES['pdl-image'])){
                    addPdl($conn, $data, $_FILES['pdl-image']);
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Image not provided.',
                        'user' => null
                    ];
                    echo json_encode($response);
                }
                break;
            case 'edit-pdl':
                if(isset($_FILES['pdl-image'])){
                    editPdl($conn, $data, $_FILES['pdl-image']);
                } else {
                    editPdl($conn, $data, null);
                } 
                break;
            case 'archive-pdl':
                $method = $data['method'];
                archivePdl($conn, $data, $method);
                break;
            case 'retrieve-pdl':
                $method = $data['method'];
                retrievePdl($conn, $data, $method);
                break;
            case 'delete-pdl':
                $method = $data['method'];
                deletePdl($conn, $data, $method);
                break;
            case 'set-fingerprint':
                setFingerprintData($conn, $data);
                break;
            case 'remove-fingerprint':
                removeFingerprint($conn, $data);
                break;
            default:
                break;
        }
    }
    function addPdl($conn, $data, $image){
        $currentDateTime = date('Y-m-d H:i:s');
        if($data && $image){
            $checkIfDupeId = $conn->prepare("SELECT `pdl-id` FROM pdls WHERE `pdl-id` = ? AND `pdl-branch-location` = ? AND `is-archived` = 0");
            $checkIfDupeId->bind_param("ss", $data['pdl-id'], $data['pdl-branch-location']);
            $checkIfDupeId->execute();
            $dupeIdCheck = $checkIfDupeId->get_result();
            $dupeIdData = $dupeIdCheck->fetch_assoc();
    
            if ($dupeIdData) {
                $response = [
                    'success' => false,
                    'message' => 'PDL already exists in the database.',
                    'user' => null
                ];
            } else {
                $getLastID = $conn->prepare("SELECT MAX(`pdl-id`) as last_id FROM pdls WHERE `pdl-branch-location` = ?");
                $getLastID->bind_param("s", $data['pdl-branch-location']);
                $getLastID->execute();
                $lastIDResult = $getLastID->get_result();
                $lastIDData = $lastIDResult->fetch_assoc();
                $newID = $lastIDData['last_id'] + 1;
    
                // Upload the image
                $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
                $imageFileType = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
                $newFilename = $timestamp . '_' . $newID . 
                   str_replace(' ', '', $data['pdl-first-name']) . 
                   str_replace(' ', '', $data['pdl-last-name']) . '_' . 
                   str_replace(' ', '', $data['pdl-branch-location']) .
                   '.' . $imageFileType;
                $targetDirectory = "../api/files/images/pdls/" . $newFilename; // Specify your target directory
                
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
                    $insert = $conn->prepare("INSERT INTO pdls (
                        `pdl-id`, 
                        `pdl-first-name`,
                        `pdl-middle-name`,
                        `pdl-last-name`,
                        `pdl-age`,
                        `pdl-gender`,
                        `pdl-other-gender`,
                        `pdl-cell-no`,
                        `pdl-medical-condition`,
                        `pdl-branch-location`,
                        `pdl-image`,
                        `pdl-balance`,
                        `is-archived`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0)");
        
                    $insert->bind_param("issssssssss", $newID, $data['pdl-first-name'], $data['pdl-middle-name'], $data['pdl-last-name'], $data['pdl-age'], 
                              $data['pdl-gender'], $data['pdl-other-gender'], $data['pdl-cell-no'], $data['pdl-medical-condition'], 
                              $data['pdl-branch-location'], $targetDirectory);
                    
                            $logData = [
                                'id' => $newID,
                                'user' => $data['active-email'],
                                'reason' => '',
                            ];

                    if ($insert->execute()) {
                        createLog($conn, ['PDL', 'Create', 'Single'], $logData, $currentDateTime);
                        $response = [
                            'success' => true,
                            'message' => 'Entry added successfully.',
                            'user' => [
                                'pdl-id' => $newID,
                            ]
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Error adding PDL to the database.',
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
    function editPdl($conn, $data, $image){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['pk'])){
            $pdlID = $data['pk'];
            $result = $conn->query("SELECT * FROM `pdls` WHERE `pk` = $pdlID");
    
            if ($result && $result->num_rows > 0) {
                $existingData = $result->fetch_assoc();
                $updatedFields = [];
    
                // Compare each field with the existing data
                foreach ($data as $key => $value) {
                    if (isset($existingData[$key]) && $existingData[$key] !== $value) {
                        // If the field exists in the existing data and doesn't match, update the database
                        $conn->query("UPDATE `pdls` SET `$key` = '{$value}' WHERE `pk` = $pdlID");
                        $updatedFields[$key] = $value;

                    }
                }
                if ($image) {
                    // Compare new image with the existing image (if needed)
                    if ($image['name'] !== $existingData['pdl-image']) {
                        $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
                        $imageFileType = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
                        $newFilename = $timestamp . '_' . $data['pdl-id'] . 
                            str_replace(' ', '', $data['pdl-first-name']) . 
                            str_replace(' ', '', $data['pdl-last-name']) . '_' . 
                            str_replace(' ', '', $data['pdl-branch-location']) . 
                            '.' . $imageFileType;
                        $targetDirectory = "../api/files/images/pdls/" . $newFilename;
    
                        // Upload the image
                        $uploadedImage = uploadImage($image, $data['pdl-id'], $data, $targetDirectory, $newFilename);
    
                        if ($uploadedImage) {
                            // Update the database with the new image path
                            $conn->query("UPDATE `pdls` SET `pdl-image` = '{$newFilename}' WHERE `pk` = $pdlID");
                            $updatedFields['pdl-image'] = $newFilename;
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
                    $result = $conn->query("SELECT * FROM `pdls` WHERE `pk` = $pdlID");
                    $updatedData = $result->fetch_assoc();
                    $logExistingData = [];
                        foreach ($updatedFields as $key => $value) {
                            $logExistingData[$key] = $existingData[$key];
                        }
                    $logData = [
                        'id' => $data['pdl-id'],
                        'user' => $data['active-email'],
                        'reason' => '',
                        'existing' => $logExistingData,
                        'updated' => $updatedFields,
                    ];
                    createLog($conn, ['PDL', 'Edit', 'Single'], $logData, $currentDateTime);
                    $response = [
                        'success' => true,
                        'message' => 'PDL updated successfully.',
                        'user' => $updatedData,
                        
                    ];
                } else {
                    // If all fields match, return an error
                    $response = [
                        'success' => false,
                        'message' => 'Values are the same.',
                        'user' => $existingData,
                        'image1' => $data['pdl-image'],
                        'image2' => $existingData['pdl-image']
                    ];
                }
    
                echo json_encode($response);
            } else {
                // No user found with the given user ID
                $response = [
                    'success' => false,
                    'message' => 'User not found.'
                ];
                echo json_encode($response);
            }
        }
    }
    function archivePdl($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $pdlID = $data['id'];
            $result = $conn->query("SELECT * FROM `pdls` WHERE `pk` = $pdlID");
            if ($result && $result->num_rows > 0) {
                $conn->query("UPDATE `pdls` SET `is-archived` = 1, `date-archived` = '$currentDateTime' WHERE `pk` = $pdlID");
                createLog($conn, ['PDL', 'Archive', 'Single'], $data, $currentDateTime);
                $updatedResult = $conn->query("SELECT * FROM `pdls` WHERE `pdl-id` = $pdlID");
                $updatedData = $updatedResult->fetch_assoc();
                $response = [
                    'success' => true,
                    'message' => 'PDL archived successfully.',
                    'user' => $updatedData
                ];
            } else {
                // No user found with the given user ID
                $response = [
                    'success' => false,
                    'message' => 'PDL not found.'
                ];
            }
            
        }
        else if(isset($data['mult']) && $method === 'multiple'){
            $pdlIDs = json_decode($data['mult'], true);
            foreach($pdlIDs as $pdlID){
                $pdlMainIDs = $pdlID['pk'];
                $pdlMainPrimaryIDs = $pdlID['dbpk'];
                $conn->query("UPDATE `pdls` SET `is-archived` = 1, `date-archived` = '$currentDateTime' WHERE `pdl-id` = $pdlMainIDs AND `pk` = $pdlMainPrimaryIDs");
            }
            createLog($conn, ['PDL', 'Archive', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'PDLs archived successfully.',
            ];
        }
        echo json_encode($response);
    }
    function retrievePdl($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $pdlID = $data['id'];
            $primary = isset($data['prim']) ? (int) $data['prim'] : '';
            $branch = isset($data['branch']) ? $data['branch'] : '';
            $result = $conn->query("SELECT * FROM `pdls` WHERE `pdl-id` = $pdlID AND `is-archived` = 1");
            $pdlIDResult = $result->fetch_assoc()['pdl-id'];
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                /*if($pdlID === $row['pdl-id']){
                    $updateSql = $conn->query("UPDATE `pdls` SET `is-archived` = 0, `date-archived` = NULL WHERE `pdl-id` = $pdlID");
                    createLog($conn, ['PDL', 'Retrieve', 'Single'], $data, $currentDateTime);
                    $row['is-archived'] = 0;
                    
                    $response = [
                        'success' => true,
                        'data' => $row
                    ];
                    echo json_encode($response);
                    return;
                }*/
                
                // Check if the current ID already exists in the database
                $checkResult = $conn->query("SELECT COUNT(*) as count FROM `pdls` WHERE `pdl-id` = $pdlIDResult AND `pdl-branch-location` = '$branch' AND `is-archived` = 0");
                if ($checkResult->fetch_assoc()['count'] < 1) {
                    $updateSql = "UPDATE `pdls` SET `is-archived` = 0 WHERE `pdl-id` = $pdlID AND `pk` = $primary AND `pdl-branch-location` = '$branch'";
                    createLog($conn, ['PDL', 'Retrieve', 'Single'], $data, $currentDateTime);
                    $conn->query($updateSql);
                    $response = [
                        'success' => true,
                        'data' => $row,
                        'outcome' => 'No ID change'
                    ];
                    echo json_encode($response);
                } else {
                    
                    $newID = 1;
                    $allIdsSql = "SELECT `pdl-id` FROM `pdls` WHERE `is-archived` = 0 AND `pdl-branch-location` = '$branch' ORDER BY `pdl-id` ASC";
                    $allIdsResult = $conn->query($allIdsSql);

                    $existingIds = [];
                    while ($row = $allIdsResult->fetch_assoc()) {
                        $existingIds[] = (int) $row['pdl-id']; // Store all existing IDs in an array
                    }
                    while (in_array($newID, $existingIds)) {
                        $newID++;
                    }

                    $updateSql = "UPDATE `pdls` SET `pdl-id` = $newID, `is-archived` = 0 WHERE `pk` = $primary AND `is-archived` = 1";
                    $conn->query($updateSql);
                    createLog($conn, ['PDL', 'Retrieve', 'Single'], $data, $currentDateTime);
                    $response = [
                        'existingIDs' => $existingIds,
                        'newID' => $newID,
                        'primary' => $primary,
                        'success' => true,
                        'outcome' => 'ID change'
                    ];
                    echo json_encode($response);
                    /*if ($pdlID > $row['previousEntryID']) {
                        $newID = $row['previousEntryID'] + 1;
                    } else {
                        $newID = $pdlID + 1;
                    }
    
                    // Find the next available ID
                    while (true) {
                        $checkSql = "SELECT COUNT(*) as count FROM `pdls` WHERE `pdl-id` = $newID";
                        $checkResult = $conn->query($checkSql);
    
                        if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                            // Update the entry with the new ID and set isArchived to 0
                            $updateSql = "UPDATE `pdls` SET `pdl-id` = $newID, `is-archived` = 0 WHERE `pk` = $primary";
                            $conn->query($updateSql);
                            $row['pdl-id'] = $newID;
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
            $userIDs = json_decode($data['mult'], true);
            
            foreach($userIDs as $pdlID){
                $pdlMainIDs = $pdlID['pk'];
                $pdlMainPrimaryIDs = $pdlID['dbpk'];
                $pdlBranchLoc = $pdlID['bjmpBranch'];
                
                $result = $conn->query("SELECT * FROM `pdls` WHERE `pdl-id` = $pdlMainIDs AND `pk` = $pdlMainPrimaryIDs AND `pdl-branch-location` = '$pdlBranchLoc' AND `is-archived` = 1");
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $pdlIDResult = $row['pdl-id'];

                    /*if($pdlMainIDs == $row['pdl-id']){
                        $updateSql = $conn->query("UPDATE `pdls` SET `is-archived` = 0, `date-archived` = NULL WHERE `pdl-id` = $pdlMainIDs");
                        $row['is-archived'] = 0;
                        
                    } else {*/
                        // Check if the current ID already exists in the database
                        $checkResult = $conn->query("SELECT COUNT(*) as count FROM `pdls` WHERE `pdl-id` = $pdlMainIDs AND `is-archived` = 0");
                        
                        if ($checkResult->fetch_assoc()['count'] < 1) {
                            $updateSql = "UPDATE `pdls` SET `is-archived` = 0 WHERE `pdl-id` = $pdlMainIDs AND `pk` = $pdlMainPrimaryIDs";
                            $conn->query($updateSql);
                            $row['is-archived'] = 0;

                            $response = [
                                'success' => true,
                                'data' => $row
                            ];

                            $responses[] = $response;
                        } else {
                            // Cases 2 and 3: Handle the previous entry's ID and current ID accordingly
                            $newID = 1;
                            $allIdsSql = "SELECT `pdl-id` FROM `pdls` WHERE `is-archived` = 0 AND `pdl-branch-location` = '$pdlBranchLoc' ORDER BY `pdl-id` ASC";
                            $allIdsResult = $conn->query($allIdsSql);
                            $existingIds = [];
                            while ($row = $allIdsResult->fetch_assoc()) {
                                $existingIds[] = (int) $row['pdl-id']; // Store all existing IDs in an array
                            }
                            while (in_array($newID, $existingIds)) {
                                $newID++;
                            }
                            $updateSql = "UPDATE `pdls` SET `pdl-id` = $newID, `is-archived` = 0 WHERE `pk` = $pdlMainPrimaryIDs AND `is-archived` = 1";
                            $conn->query($updateSql);
                            
                            $response = [
                                'success' => true,
                                'data' => $row
                            ];
                            $responses[] = $response;
                            /*if ($pdlMainIDs > $row['previousEntryID']) {
                                $newID = $row['previousEntryID'] + 1;
                            } else {
                                $newID = $pdlMainIDs + 1;
                            }

                            // Find the next available ID
                            while (true) {
                                $checkSql = "SELECT COUNT(*) as count FROM `pdls` WHERE `pdl-id` = $newID";
                                $checkResult = $conn->query($checkSql);

                                if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                                    // Update the entry with the new ID and set isArchived to 0
                                    $updateSql = "UPDATE `pdls` SET `pdl-id` = $newID, `is-archived` = 0 WHERE `pk` = $pdlMainPrimaryIDs";
                                    $conn->query($updateSql);
                                    $row['pdl-id'] = $newID;
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
            createLog($conn, ['PDL', 'Retrieve', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'PDL archived successfully.',
            ];

            echo json_encode($response);
        }
        
    }
    function deletePdl($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $pdlID = $data['id'];
            $primary = isset($data['prim']) ? $data['prim'] : '';
            
            $result = $conn->query("DELETE FROM `pdls` WHERE `pdl-id` = $pdlID AND `pk` = $primary AND `is-archived` = 1");
            createLog($conn, ['PDL', 'Delete', 'Single'], $data, $currentDateTime);
            if($result){
                $response = [
                    'success' => true,
                    'message' => 'item deleted successfully.'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Error deleting PDL.'
                ];
            }
        } 
        else if (isset($data['mult']) && $method === 'multiple'){
            $userIDs = json_decode($data['mult'], true);
            
            foreach($userIDs as $pdlID){
                $pdlMainIDs = $pdlID['pk'];
                $pdlMainPrimaryIDs = $pdlID['dbpk'];
                $result = $conn->query("DELETE FROM `pdls` WHERE `pdl-id` = $pdlMainIDs AND `pk` = $pdlMainPrimaryIDs AND `is-archived` = 1");
            }
            createLog($conn, ['PDL', 'Delete', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'PDL deleted successfully.'
            ];
        }
        
        else {
            $response = [
                'success' => false,
                'message' => 'PDL ID not provided.'
            ];
            
        }
        echo json_encode($response);
        
    }
    //Set Fingerprint
    function setFingerprintData($conn, $data){
        if($data){
            
            $set = $conn->prepare("UPDATE `pdls` SET `pdl-fingerprint-id` = ? WHERE `pdl-id` = ? AND 
            `pdl-first-name` = ? AND `pdl-middle-name` = ? AND `pdl-last-name` = ? AND `pdl-branch-location` = ?");
            $set->bind_param('sissss', $data['fingerprint-data'], $data['pdl-data']['pdl-id'], $data['pdl-data']['pdl-first-name'], 
            $data['pdl-data']['pdl-middle-name'], $data['pdl-data']['pdl-last-name'], $data['pdl-data']['pdl-branch-location']);
            if($set->execute()){
                $response = [
                    'success' => true, 
                    "message" => 'PDL updated successfully.',
                    "data" => $data
                ];
                
            }
            else{
                $response = [
                    'success' => false, 
                    "message" => 'Error creating record. Please try again.'
                ];
            }
        }
        
        echo json_encode($response);
    }
    //Remove Fingerprint
    function removeFingerprint($conn, $data){
        if($data){
            $remove = $conn->prepare("UPDATE `pdls` SET `pdl-fingerprint-id` = NULL WHERE `pk` = ?");
            $remove->bind_param("i", $data['pk']);
            if($remove->execute()){
                $response = [
                    'success' => true, 
                    "message" => 'PDL updated successfully.',
                    "data" => $data
                ];
                
            }
            else{
                $response = [
                    'success' => false, 
                    "message" => 'Error creating record. Please try again.'
                ];
            }
        }
        echo json_encode($response);
    }
?>