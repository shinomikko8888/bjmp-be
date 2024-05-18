<?php
    function getInstances($conn, $it, $in, $br, $archived){
        if($it && $in && $br){
            if($br = 'BJMPRO-III Main Office'){
             $result = $conn->query("SELECT * FROM `instances` WHERE `instance-type` = '$it' AND 
                `instance-name` = '$in' AND `is-archived` = $archived");
            }
            else{
            $result = $conn->query("SELECT * FROM `instances` WHERE `instance-type` = '$it' AND 
                `instance-name` = '$in' AND `instance-branch-location` = '$br' AND `is-archived` = $archived");
            }
            if ($result && $result->num_rows > 0) {
                $data = array();
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                echo json_encode($data);
    
            } else {
                echo json_encode(array());
            }
        }
    }


?>