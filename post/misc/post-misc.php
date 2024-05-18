<?php
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

?>