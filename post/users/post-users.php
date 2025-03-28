<?php
    include "./post/misc/post-misc.php";
    include "./get/misc/get-misc.php";
    include "./post/log/post-log.php";

    //Login and Logout Functions
    function loginUser($conn, $data){
        $em = $data['user-email'];
        $ps = $data['user-password'];
        $hashedPs = hashPassword($ps);
        if($em === '' || $ps === ''){
            $response = [
                'success' => false,
                'message' => 'Fields are empty. Please add something',
                'user' => null
            ];
        }
        else{
            $checkLockout = $conn->prepare("SELECT `user-last-attempt-time`, `user-attempt-count` FROM users WHERE `user-email` = ?");
            $checkLockout->bind_param("s", $em);
            $checkLockout->execute();
            $lockoutResult = $checkLockout->get_result();
            $lockoutData = $lockoutResult->fetch_assoc();
            if ($lockoutData && $lockoutData['user-attempt-count'] >= 5) {
                $lastAttemptTime = strtotime($lockoutData['user-last-attempt-time']);
                $currentTime = time();
                $fiveMinutes = 5 * 60; // 5 minutes in seconds
        
                if (($currentTime - $lastAttemptTime) < $fiveMinutes) {
                    $remainingTime = $fiveMinutes - ($currentTime - $lastAttemptTime);
                    echo json_encode([
                        'success' => false,
                        'message' => "Account locked. Try again in " . $remainingTime . " seconds",
                        'user' => null
                    ]);
                    return;
                }
            }
            $stmt = $conn->prepare("SELECT * FROM users WHERE `user-email` = ? AND `is-archived` = 0");
            $stmt->bind_param("s", $em);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if($user && password_verify($ps, $user['user-password'])) {
                $loginToken = bin2hex(random_bytes(32)); 

                $storeToken = $conn->prepare("UPDATE users SET `user-login-token` = ? WHERE `user-email` = ?");
                $storeToken->bind_param("ss", $loginToken, $em);
                $storeToken->execute();
                
                $resetLockout = $conn->prepare("UPDATE users SET `user-attempt-count` = 0, `user-last-attempt-time` = NULL WHERE `user-email` = ?");
                $resetLockout->bind_param("s", $em);
                $resetLockout->execute();
                $response = [
                    'success' => true,
                    'message' => '',
                    'user' => [
                        'email' => $user['user-email'],
                        'login_token' => $loginToken,
                        'bjmp_branch'  => $user['user-branch-location'],
                        'type' => $user['user-type'],
                    ]
                ];
            } else if (!empty($user['user-reset-token'])){
                $resetLockout = $conn->prepare("UPDATE users SET `user-attempt-count` = 0, `user-last-attempt-time` = NULL WHERE `user-email` = ?");
                $resetLockout->bind_param("s", $em);
                $resetLockout->execute();
                $response = [
                    'success' => false,
                    'message' => "This email's password was reset! Check your email for the request link.",
                    'user' => null
                ];
            } else {
                // Update the attempt count and last attempt time
                if ($lockoutData && $lockoutData['user-attempt-count'] < 5) {
                    $updateLockout = $conn->prepare("UPDATE users SET `user-attempt-count` = `user-attempt-count` + 1, `user-last-attempt-time` = NOW() WHERE `user-email` = ?");
                    $updateLockout->bind_param("s", $em);
                    $updateLockout->execute();
                    $remainingAttempts = 5 - $lockoutData['user-attempt-count'] - 1;
                    $response = [
                        'success' => false,
                        'message' => 'Invalid password. ' . $remainingAttempts . ' attempts remaining',
                        'user' => null,
                        'verification' => password_verify($ps, $user['user-password'])
                    ];
                } else {
                    /*$updateLockout = $conn->prepare("UPDATE users SET `user-attempt-count` = 1, `user-last-attempt-time` = NOW() WHERE `user-email` = ?");
                    $updateLockout->bind_param("s", $em);
                    $updateLockout->execute();
                    $remainingAttempts = 4; */
                    $response = [
                        'success' => false,
                        'message' => 'Invalid email or password. ',
                        'user' => null
                    ];
                }
            }
        }
        echo json_encode($response);
    }
    function logoutUser($conn, $data){
        $em = $data['user-email'];
        $rt = $data['user-login-token'];
        $logoutUser = $conn->prepare("UPDATE users SET `user-login-token` = '' WHERE `user-email` = ? AND `user-login-token` = ?");
        $logoutUser->bind_param("ss", $em, $rt);
        $logoutUser->execute();
        $response = [
            'success' => true,
            'message' => 'User has been logged out.',
        ];
        echo json_encode($response);
    }

    // User Management Functions

    function manageUser($conn, $data, $type){
        switch($type){
            case 'add-user':
                if(isset($_FILES['user-image'])){
                    addUser($conn, $data, $_FILES['user-image']);
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Image not provided.',
                        'user' => null
                    ];
                    echo json_encode($response);
                }
                break;
            case 'edit-user':
                editUser($conn, $data);
                break;
            case 'archive-user':
                $method = $data['method'];
                archiveUser($conn, $data, $method);
                break;
            case 'retrieve-user':
                $method = $data['method'];
                retrieveUser($conn, $data, $method);
                break;
            case 'delete-user':
                $method = $data['method'];
                deleteUser($conn, $data, $method);
                break;
            case 'change-email':
                //changeEmail($conn, $data);
                break;
            case 'change-password':
                changePassword($conn, $data);
                break;
            default:
                break;
        }
    }
    function addUser($conn, $data, $image){
        $currentDateTime = date('Y-m-d H:i:s');
        
        if($data && $image){
            // Check if email already exists
            $checkifDupeEmail = $conn->prepare("SELECT `user-email` FROM users WHERE `user-email` = ? AND `is-archived` = 0");
            $checkifDupeEmail->bind_param("s", $data['user-email']);
            $checkifDupeEmail->execute();
            $dupeEmailCheck = $checkifDupeEmail->get_result();
            $dupeEmailData = $dupeEmailCheck->fetch_assoc();
    
            if ($dupeEmailData) {
                $response = [
                    'success' => false,
                    'message' => 'Email already exists in the database.',
                    'user' => null
                ];
            } else {
                $getLastUserID = $conn->prepare("SELECT MAX(`user-id`) as last_id FROM users");
                $getLastUserID->execute();
                $lastUserIDResult = $getLastUserID->get_result();
                $lastUserIDData = $lastUserIDResult->fetch_assoc();
                $newUserID = $lastUserIDData['last_id'] + 1;
    
                $password = $data['user-password'];
                $passwordHash = hashPassword($password);
    
                // Upload the image
                $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
                $imageFileType = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
                $newFilename = $timestamp . '_' . $newUserID . 
                   str_replace(' ', '', $data['user-first-name']) . 
                   str_replace(' ', '', $data['user-last-name']) . '_' . 
                   str_replace(' ', '', $data['user-branch-location']) . 
                   str_replace(' ', '', $data['user-position']) . 
                   '.' . $imageFileType;
                $targetDirectory = "../api/files/images/users/" . $newFilename; // Specify your target directory
                
                $uploadedImage = uploadImage($image, $newUserID, $data, $targetDirectory, $newFilename);
    
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
                    $insertUser = $conn->prepare("INSERT INTO users (
                        `user-id`, 
                        `user-email`, 
                        `user-password`, 
                        `user-first-name`,
                        `user-middle-name`,
                        `user-last-name`,
                        `user-type`,
                        `user-contact-number`,
                        `user-address`,
                        `user-branch-location`,
                        `user-position`,
                        `user-image`,
                        `is-archived`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        
                    $insertUser->bind_param("isssssssssss", $newUserID, $data['user-email'], $passwordHash, $data['user-first-name'], $data['user-middle-name'], $data['user-last-name'], $data['user-type'], 
                              $data['user-contact-number'], $data['user-address'], $data['user-branch-location'], $data['user-position'], $targetDirectory);
                    
                            $logData = [
                                'id' => $newUserID,
                                'user' => $data['active-email'],
                                'reason' => '',
                            ];

                    if ($insertUser->execute()) {
                        createLog($conn, ['User', 'Create', 'Single'], $logData, $currentDateTime);
                        $emailData = [
                            'type' => 'user-created',
                            'email' => $data['user-email'] ?? '',
                            'username' => $data['user-first-name'] ?? '',
                            'password' => $data['user-password'] ?? '',
                        ];
                        sendVerificationEmail($emailData);
                        $response = [
                            'success' => true,
                            'message' => 'User added successfully.',
                            'user' => [
                                'user-id' => $newUserID,
                                'user-email' => $data['user-email'],
                                'user-password' => $passwordHash
                            ]
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Error adding user to the database.',
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
    
    function editUser($conn, $data){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['user-id'])){
            $userID = $data['user-id'];
            $result = $conn->query("SELECT * FROM `users` WHERE `user-id` = $userID");
    
            if ($result && $result->num_rows > 0) {
                $existingData = $result->fetch_assoc();
                $updatedFields = [];
    
                // Compare each field with the existing data
                foreach ($data as $key => $value) {
                    if (isset($existingData[$key]) && $existingData[$key] !== $value) {
                        // If the field exists in the existing data and doesn't match, update the database
                        $conn->query("UPDATE `users` SET `$key` = '{$value}' WHERE `user-id` = $userID");
                        $updatedFields[$key] = $value;
                    }
                }
    
                if (!empty($updatedFields)) {
                    // If any field was updated, fetch the updated data
                    $result = $conn->query("SELECT * FROM `users` WHERE `user-id` = $userID");
                    $updatedData = $result->fetch_assoc();
                    $logExistingData = [];
                        foreach ($updatedFields as $key => $value) {
                            $logExistingData[$key] = $existingData[$key];
                        }
                    $logData = [
                        'id' => $userID,
                        'user' => $data['active-email'],
                        'reason' => '',
                        'existing' => $logExistingData,
                        'updated' => $updatedFields,
                    ];
                    createLog($conn, ['User', 'Edit', 'Single'], $logData, $currentDateTime);
                    $response = [
                        'success' => true,
                        'message' => 'User updated successfully.',
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
    
    function archiveUser($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $userID = $data['id'];
            $result = $conn->query("SELECT * FROM `users` WHERE `user-id` = $userID");
            if ($result && $result->num_rows > 0) {
                $conn->query("UPDATE `users` SET `is-archived` = 1, `date-archived` = '$currentDateTime' WHERE `user-id` = $userID");
                
                createLog($conn, ['User', 'Archive', 'Single'], $data, $currentDateTime);
                $updatedResult = $conn->query("SELECT * FROM `users` WHERE `user-id` = $userID");
                $updatedData = $updatedResult->fetch_assoc();
                $emailData = [
                    'type' => 'user-disabled',
                    'email' => $updatedData['user-email'] ?? '',
                    'username' => $updatedData['user-first-name'] ?? '',
                    'reason' => $data['reason'] ?? '',
                ];
                sendVerificationEmail($emailData);
                $response = [
                    'success' => true,
                    'message' => 'User archived successfully.',
                    'user' => $updatedData
                ];
            } else {
                // No user found with the given user ID
                $response = [
                    'success' => false,
                    'message' => 'User not found.'
                ];
            }
            
        }
        else if(isset($data['mult']) && $method === 'multiple'){
            $userIDs = json_decode($data['mult'], true);
            foreach($userIDs as $userID){
                $userMainIDs = $userID['pk'];
                $userMainPrimaryIDs = $userID['dbpk'];
                $conn->query("UPDATE `users` SET `is-archived` = 1, `date-archived` = '$currentDateTime' WHERE `user-id` = $userMainIDs AND `pk` = $userMainPrimaryIDs");
            }
            createLog($conn, ['User', 'Archive', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'Users archived successfully.',
            ];
        }
        echo json_encode($response);
    }
    
    function retrieveUser($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $userID = $data['id'];
            $primary = isset($data['prim']) ? $data['prim'] : '';
            $result = $conn->query("SELECT * FROM `users` WHERE `user-id` = $userID AND `pk` = $primary AND `is-archived` = 1");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                if($userID === $row['user-id']){
                    $updateSql = $conn->query("UPDATE `users` SET `is-archived` = 0, `date-archived` = NULL WHERE `user-id` = $userID");
                    createLog($conn, ['User', 'Retrieve', 'Single'], $data, $currentDateTime);
                    $row['is-archived'] = 0;
                    
                    $response = [
                        'success' => true,
                        'data' => $row
                    ];
                    echo json_encode($response);
                    return;
                }
                
                // Check if the current ID already exists in the database
                $checkResult = $conn->query("SELECT COUNT(*) as count FROM `users` WHERE `user-id` = $userID");
    
                if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                    $updateSql = "UPDATE `users` SET `is-archived` = 0 WHERE `user-id` = $userID";
                    $conn->query($updateSql);
                    $row['is-archived'] = 0;
    
                    $response = [
                        'success' => true,
                        'data' => $row
                    ];
                    echo json_encode($response);
                } else {
                    // Cases 2 and 3: Handle the previous entry's ID and current ID accordingly
                    if ($userID > $row['previousEntryID']) {
                        $newID = $row['previousEntryID'] + 1;
                    } else {
                        $newID = $userID + 1;
                    }
    
                    // Find the next available ID
                    while (true) {
                        $checkSql = "SELECT COUNT(*) as count FROM `users` WHERE `user-id` = $newID";
                        $checkResult = $conn->query($checkSql);
    
                        if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                            // Update the entry with the new ID and set isArchived to 0
                            $updateSql = "UPDATE `users` SET `user-id` = $newID, `is-archived` = 0 WHERE `pk` = $primary";
                            $conn->query($updateSql);
                            $row['user-id'] = $newID;
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
            $userIDs = json_decode($data['mult'], true);
            
            foreach($userIDs as $userID){
                $userMainIDs = $userID['pk'];
                $userMainPrimaryIDs = $userID['dbpk'];
                
                $result = $conn->query("SELECT * FROM `users` WHERE `user-id` = $userMainIDs AND `pk` = $userMainPrimaryIDs AND `is-archived` = 1");
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    
                    if($userMainIDs == $row['user-id']){
                        $updateSql = $conn->query("UPDATE `users` SET `is-archived` = 0, `date-archived` = NULL WHERE `user-id` = $userMainIDs");
                        $row['is-archived'] = 0;
                        
                    } else {
                        // Check if the current ID already exists in the database
                        $checkResult = $conn->query("SELECT COUNT(*) as count FROM `users` WHERE `user-id` = $userMainIDs");
                        
                        if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                            $updateSql = "UPDATE `users` SET `is-archived` = 0 WHERE `user-id` = $userMainIDs";
                            $conn->query($updateSql);
                            $row['is-archived'] = 0;

                            $response = [
                                'success' => true,
                                'data' => $row
                            ];

                            $responses[] = $response;
                        } else {
                            // Cases 2 and 3: Handle the previous entry's ID and current ID accordingly
                            if ($userMainIDs > $row['previousEntryID']) {
                                $newID = $row['previousEntryID'] + 1;
                            } else {
                                $newID = $userMainIDs + 1;
                            }

                            // Find the next available ID
                            while (true) {
                                $checkSql = "SELECT COUNT(*) as count FROM `users` WHERE `user-id` = $newID";
                                $checkResult = $conn->query($checkSql);

                                if ($checkResult && $checkResult->fetch_assoc()['count'] == 0) {
                                    // Update the entry with the new ID and set isArchived to 0
                                    $updateSql = "UPDATE `users` SET `user-id` = $newID, `is-archived` = 0 WHERE `pk` = $userMainPrimaryIDs";
                                    $conn->query($updateSql);
                                    $row['user-id'] = $newID;
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
            createLog($conn, ['User', 'Retrieve', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'Users archived successfully.',
            ];

            echo json_encode($response);
        }
        
    }
    
    function deleteUser($conn, $data, $method){
        $currentDateTime = date('Y-m-d H:i:s');
        if(isset($data['id']) && $method === 'single'){
            $userID = $data['id'];
            $primary = isset($data['prim']) ? $data['prim'] : '';
            
            $result = $conn->query("DELETE FROM `users` WHERE `user-id` = '$userID' AND `pk` = '$primary' AND `is-archived` = 1");
            createLog($conn, ['User', 'Delete', 'Single'], $data, $currentDateTime);
            if($result){
                $response = [
                    'success' => true,
                    'message' => 'item deleted successfully.'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Error deleting user.'
                ];
            }
        } 
        else if (isset($data['mult']) && $method === 'multiple'){
            $userIDs = json_decode($data['mult'], true);
            
            foreach($userIDs as $userID){
                $userMainIDs = $userID['pk'];
                $userMainPrimaryIDs = $userID['dbpk'];
                $result = $conn->query("DELETE FROM `users` WHERE `user-id` = $userMainIDs AND `pk` = $userMainPrimaryIDs AND `is-archived` = 1");
            }
            createLog($conn, ['User', 'Delete', 'Multiple'], $data, $currentDateTime);
            $response = [
                'success' => true,
                'message' => 'User deleted successfully.'
            ];
        }
        
        else {
            $response = [
                'success' => false,
                'message' => 'User ID not provided.'
            ];
            
        }
        echo json_encode($response);
        
    }
    /*$stmt = $conn->prepare("INSERT INTO users (`user-email`, `user-password`, `user-first-name`, `user-middle-name`, `user-last-name`, `user-type`, `user-contact-number`,
                    `user-address`, `user-branch-location`, `user-position`, `user-image`, `is-archived`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");

        $stmt->bind_param("sssssssssss", $data['user-email'], $data['user-password'], $data['user-first-name'], $data['user-middle-name'], $data['user-last-name'], $data['user-type'], 
                          $data['user-contact-number'], $data['user-address'], $data['user-branch-location'], $data['user-position'], $data['user-image']);
        
        if ($stmt->execute()) {
            echo json_encode(array("message" => "Data inserted successfully"));
        } else {
            echo json_encode(array("message" => "Error inserting data"));
        }*/


    //Utilities for Password
    function hashPassword($password) {
        // Validate password length
        if (strlen($password) < 6 || strlen($password) > 20) {
            $response = [
                'success' => false,
                'message' => 'Password must be between 6 and 20 characters long.',
                'user' => null
            ];
            echo json_encode($response);
            exit; // Exit the script if the password is invalid
        }
    
        // Hash the password using bcrypt
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        return $hashedPassword;
    }

    //Change Password and Email
    function changePassword($conn, $data){
        $response = [];
        if($data){
            $hashedPassword = hashPassword($data['password']);
            $updatePassword = $conn->prepare("UPDATE `users` SET `user-password` = ? WHERE `user-reset-token` = ?");
            $updatePassword->bind_param("ss", $hashedPassword, $data['reset-token']);
            $updatePassword->execute();
            if ($updatePassword->affected_rows === 0) {
                $response = [
                    'success' => false
                ];
                return;
            }
            $removeToken = $conn->prepare("UPDATE `users` SET `user-reset-token` = NULL WHERE `user-reset-token` = ?");
            $removeToken->bind_param("s", $data['reset-token']);
            $removeToken->execute();
            if ($removeToken->affected_rows === 0){
                $response = [
                    'success' => false
                ];
                return;
            }
            $selectEmail = $conn->prepare("SELECT `user-email` FROM `users` WHERE `user-password` = ?");
            $selectEmail->bind_param("s", $hashedPassword);
            $selectEmail->execute();
            $selectEmail->bind_result($userEmail);
            if ($selectEmail->fetch()) {
                $response['success'] = true;
                $response['email'] = $userEmail;
            }
        }
        echo json_encode($response);
    }
    
?>