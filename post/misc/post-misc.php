<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

    if(!function_exists('uploadImage')){
        function uploadImage($image, $userID, $data, $td, $newFilename) {
        
            // Generate a unique filename
            $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
            $imageFileType = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
            
            $targetFile = $td;
            
            $uploadOk = 1;
            
            // Check if image file is an actual image or fake image
            $check = getimagesize($image["tmp_name"]);
            if($check !== false) {
                $uploadOk = 1;
            } else {
                $response = [
                    'success' => false,
                    'message' => 'File is not an image.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file is not an image
            }
            
            // Check if file already exists
            if (file_exists($targetFile)) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, file already exists.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file already exists
            }
            
            // Check file size
            if ($image["size"] > 5000000) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, your file is too large.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file is too large
            }
            
            // Allow certain file formats
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif" ) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file format is not allowed
            }
            
            // Check if $uploadOk is set to 0 by an error
            if ($uploadOk == 0) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, your file was not uploaded.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file was not uploaded
            } else {
                if (move_uploaded_file($image["tmp_name"], $targetFile)) {
                    return $newFilename;
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Sorry, there was an error uploading your file.',
                        'user' => null
                    ];
                    echo json_encode($response);
                    exit; // Exit the script if there was an error uploading the file
                }
            }
        }
    }
    if(!function_exists('uploadDoc')){
        function uploadDoc($doc, $userID, $data, $td, $newFilename) {
        
            // Generate a unique filename
            $timestamp = round(microtime(true) * 1000);  // Current timestamp in milliseconds
            $docFileType = strtolower(pathinfo($doc["name"], PATHINFO_EXTENSION));
            
            $targetFile = $td;
            
            $uploadOk = 1;
            
            // Check if file already exists
            if (file_exists($targetFile)) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, file already exists.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file already exists
            }
            
            // Check file size
            if ($doc["size"] > 5000000) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, your file is too large.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file is too large
            }
            
            // Allow certain file formats
            $allowedFormats = ["pdf", "doc", "docx", "txt", "png", "jpg", "jpeg"];
            if(!in_array($docFileType, $allowedFormats)) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, only PDF, DOC, DOCX, TXT & Image files are allowed.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file format is not allowed
            }
            
            // Check if $uploadOk is set to 0 by an error
            if ($uploadOk == 0) {
                $response = [
                    'success' => false,
                    'message' => 'Sorry, your file was not uploaded.',
                    'user' => null
                ];
                echo json_encode($response);
                exit; // Exit the script if file was not uploaded
            } else {
                if (move_uploaded_file($doc["tmp_name"], $targetFile)) {
                    return $newFilename;
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Sorry, there was an error uploading your file.',
                        'user' => null
                    ];
                    echo json_encode($response);
                    exit; // Exit the script if there was an error uploading the file
                }
            }
        }
    }

    if(!function_exists('sendVerificationEmail')){
        function sendVerificationEmail($data){
            $year = date('Y');
            $htmlContent = file_get_contents('./templates/' . $data['type'] .'.html');
            $emailTitle = '';
            
            switch ($data['type']) {
                case 'user-created':
                    $htmlContent = str_replace('{{password}}', $data['password'], $htmlContent);
                    $htmlContent = str_replace('{{name}}', $data['username'], $htmlContent);
                    $emailTitle .= 'User Creation Notice';
                    break;
                case 'user-disabled':
                    $htmlContent = str_replace('{{reason}}', $data['reason'], $htmlContent);
                    $emailTitle .= 'Disabling of Account Notice';
                    break;
                case 'reset-password':
                    $htmlContent = str_replace('{{domain}}', 'http://localhost:3000', $htmlContent);
                    $htmlContent = str_replace('{{token}}', $data['token'], $htmlContent);
                    $emailTitle .= 'Reset Password Request';
                    break;
                default:
                    break;
            }
            $htmlContent = str_replace('{{year}}', $year, $htmlContent);
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = ''; // Replace with your email
                $mail->Password   = ''; // Replace with your password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                $mail->setFrom('noreply@example.com', 'BJMPRO-III POS'); // Replace with your email and name
                $mail->addAddress($data['email']);
                $mail->isHTML(true);                                        // Set email format to HTML
                $mail->Subject = $emailTitle;
                $mail->Body    = $htmlContent;
                $mail->send();
                return true;
            } catch (Exception $e) {
                // Log the error or handle it appropriately
                return false;
            }
        }
    }
?>
