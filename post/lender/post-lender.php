<?php
    include "./post/misc/post-misc.php";
    include "./get/misc/get-misc.php";
    include "./post/log/post-log.php";

    function manageLender($conn, $data, $type){
        switch($type){
            case 'add-lender':
                if(isset($_FILES['lender-id-path'])){
                    addLender($conn, $data, $_FILES['lender-id-path']);
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'File not provided.',
                        'user' => null
                    ];
                    echo json_encode($response);
                }
                break;
                break;
            case 'edit-lender':
                //editLender($conn, $data);
                break;
            case 'delete-lender':
                //deleteLender($conn, $data);
                break;
            case 'review-lender':
                //reviewLender($conn, $data);
                break;
            default:
                break;
        }
    }

    function addLender($conn, $data, $doc){
        $currentDateTime = date('Y-m-d H:i:s');
        if($data){
            $checkIfDupeId = $conn->prepare("SELECT `lender-id` FROM lenders WHERE `lender-id` = ? AND `lender-branch-location` = ?");
            $checkIfDupeId->bind_param("ss", $data['lender-id'], $data['lender-branch-location']);
            $checkIfDupeId->execute();
            $dupeIdCheck = $checkIfDupeId->get_result();
            $dupeIdData = $dupeIdCheck->fetch_assoc();
            if ($dupeIdData) {
                $response = [
                    'success' => false,
                    'message' => 'Lender already exists in the database.',
                    'user' => null
                ];
            }
            else{
                $getLastID = $conn->prepare("SELECT MAX(`lender-id`) as last_id FROM lenders WHERE `lender-branch-location` = ?");
                $getLastID->bind_param("s", $data['lender-branch-location']);
                $getLastID->execute();
                $lastIDResult = $getLastID->get_result();
                $lastIDData = $lastIDResult->fetch_assoc();
                $newID = $lastIDData['last_id'] !== null ? $lastIDData['last_id'] + 1 : 1;
                
                $checkIfAdmin = $conn->prepare("SELECT `user-type` FROM users WHERE `user-email` = ?");
                $checkIfAdmin->bind_param("s", $data['active-email']);
                $checkIfAdmin->execute();
                $adminResult = $checkIfAdmin->get_result();
                $adminData = $adminResult->fetch_assoc();
                if ($adminData) {
                    $isAdmin = $adminData['user-type'] === 'Administrator' ? 1 : 0;
                } else {
                    $isAdmin = 0;
                }
                $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
                $docFileType = strtolower(pathinfo($doc["name"], PATHINFO_EXTENSION));
                $newFilename = $timestamp . '_' . $newID . 
                   str_replace(' ', '', $data['lender-related-pdl']) . 
                   str_replace(' ', '', $data['lender-first-name']) . '_' . 
                   str_replace(' ', '', $data['lender-last-name']) .
                   '.' . $docFileType;
                $targetDirectory = "../api/files/docs/ids/" . $newFilename; // Specify your target directory
                $uploadedDoc = uploadDoc($doc, $newID, $data, $targetDirectory, $newFilename);
                if (!$uploadedDoc) {
                    $response = [
                        'success' => false,
                        'message' => 'Error uploading image.',
                        'user' => null
                    ];
                    echo json_encode($response);
                    exit;
                } else {
                    $insert = $conn->prepare("INSERT INTO lenders (
                        `lender-id`,
                        `lender-first-name`,
                        `lender-middle-name`,
                        `lender-last-name`,
                        `lender-relationship`,
                        `lender-id-path`,
                        `lender-related-pdl`,
                        `lender-branch-location`,
                        `is-approved`,
                        `is-archived`
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                        $insert->bind_param("isssssssi", $newID, $data['lender-first-name'], $data['lender-middle-name'],
                        $data['lender-last-name'], $data['lender-relationship'], $targetDirectory, $data['lender-related-pdl'],
                        $data['lender-branch-location'], $isAdmin);
                    $logData = [
                        'id' => $newID,
                        'user' => $data['active-email'],
                        'reason' => '',
                    ];
                    if ($insert->execute()) {
                        createLog($conn, ['Creditor', 'Create', 'Single'], $logData, $currentDateTime);
                        $response = [
                            'success' => true,
                            'message' => 'Entry added successfully.',
                            'user' => [
                                'lender-id' => $newID,
                            ]
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Error adding lender to the database.',
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

?>