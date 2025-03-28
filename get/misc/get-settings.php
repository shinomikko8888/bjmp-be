<?php
    
    function getSettings($conn, $br){
        $result = $conn->query("SELECT * FROM `settings`");
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode($data);
        } else {
            echo json_encode(array());
        }
    }


?>
