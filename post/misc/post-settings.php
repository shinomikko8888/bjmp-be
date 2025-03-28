<?php
    include "./post/misc/post-misc.php";

    function handleSettings($conn, $data){
        $action = '';
        if(isset($data['account-action'])){
            $action = $data['account-action'];
        } else {
            $action = 'spending-total';
        }
        $actionTitle = '';
        $actionDescription = '';
        $response = [];
        if($data['from-home']){
            $checkifDupeEmail = $conn->prepare("SELECT `user-email` FROM users WHERE `user-email` = ? AND `is-archived` = 0");
            $checkifDupeEmail->bind_param("s", $data['active-email']);
            $checkifDupeEmail->execute();
            $dupeEmailCheck = $checkifDupeEmail->get_result();
            $dupeEmailData = $dupeEmailCheck->fetch_assoc();
            if (!$dupeEmailData) {
                $response = [
                    'success' => false,
                    'message' => "Email doesn't exists in the database.",
                    'user' => null
                ];
                echo json_encode($response);
                return;
            } else {
                $actionTitle .= 'Password request success!';
                $actionDescription .= 'You will be logged out!';
                $changePass = $conn->prepare("UPDATE users SET `user-password` =  NULL, `user-login-token` = NULL WHERE `user-email` = ? AND `is-archived` = 0");
                $changePass->bind_param("s", $data['active-email']);
                $changePass->execute();
                $resetToken = bin2hex(random_bytes(20));
                $giveToken = $conn->prepare("UPDATE users SET `user-reset-token` = ? WHERE `user-email` = ?");
                $giveToken->bind_param("ss", $resetToken, $data['active-email']);
                $giveToken->execute();
                $emailData = [
                    'type' => 'reset-password',
                    'email' => $data['active-email'] ?? '',
                    'token' => $resetToken ?? '',
                ];
                sendVerificationEmail($emailData); 
            }
            $response = [
                'success' => true,
                'title' => $actionTitle,
                'description' => $actionDescription,
            ];
        } else {
            $getPass = $conn->prepare("SELECT `user-password` FROM `users` WHERE `user-email` = ? AND `is-archived` = 0");
            $getPass->bind_param('s', $data['active-email']);
            $getPass->execute();
            $result = $getPass->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $userPassword = $row['user-password'];
                if(password_verify($data['confirm-password'], $userPassword)){
                   
                    switch ($action) {
                        case 'change-email':
                            $actionTitle .= 'Email change success!';
                            $actionDescription .= 'You will be logged out!';
                            //$changeEmail = $conn->prepare("UPDATE users SET `user-email` =  ?, `user-login-token` = NULL WHERE `user-email` = ? AND `is-archived` = 0");
                            //$changeEmail->bind_param("ss", $data['change-email'], $data['active-email']);
                            //$changeEmail->execute();
                            /*
                            $emailData = [
                                'type' => 'account-verification',
                                'email' => $data['active-email'] ?? '',
                            ];
                            sendVerificationEmail($emailData); */
                            break;
                        case 'change-password':
                            
                            $actionTitle .= 'Password request success!';
                            $actionDescription .= 'You will be logged out!';
                            $changePass = $conn->prepare("UPDATE users SET `user-password` =  NULL, `user-login-token` = NULL WHERE `user-email` = ? AND `is-archived` = 0");
                            $changePass->bind_param("s", $data['active-email']);
                            $changePass->execute();
                            $resetToken = bin2hex(random_bytes(20));
                            $giveToken = $conn->prepare("UPDATE users SET `user-reset-token` = ? WHERE `user-email` = ? AND `is-archived` = 1");
                            $giveToken->bind_param("ss", $resetToken, $data['active-email']);
                            $giveToken->execute();
                            $emailData = [
                                'type' => 'reset-password',
                                'email' => $data['active-email'] ?? '',
                                'token' => $resetToken ?? '',
                            ];
                            sendVerificationEmail($emailData); 
                            break;
                        case 'archive-account':
                            $actionTitle .= 'Account archived!';
                            $actionDescription .= 'You will be logged out! For reactivation, contact an administrator!';
                            /*
                            $archiveAccount = $conn->prepare("UPDATE users SET `is-archived` = 1 WHERE `user-email` = ?");
                            $archiveAccount->bind_param("s", $data['active-email']);
                            $archiveAccount->execute();
                            */
                            break;
                        case 'spending-total';
                            $actionTitle .= 'Spending total changed!';
                            $actionDescription .= 'New spending total will be followed by the branch!';
                            $updateTotal = $conn->prepare("UPDATE settings SET `setting-value` = ? WHERE `setting-name` = 'spending-limit'");
                            $updateTotal->bind_param('i', $data['spending-limit']);
                            $updateTotal->execute();
                            break;
                        default:
                            break;
                    }
                    $response = [
                        'success' => true,
                        'title' => $actionTitle,
                        'description' => $actionDescription,
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => "Password doesn't match. Try again!"
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => "User and password doesn't exist!"
                ];
            }
        }
        echo json_encode($response);
    }


?>
