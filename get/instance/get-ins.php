<?php
    function getInstances($conn, $pk, $br, $archived){
        if($pk && $br){
            if($br = 'BJMPRO-III Main Office'){
             $result = $conn->query("SELECT * FROM `instances` WHERE `instance-item-pk` = '$pk' AND `is-archived` = $archived");
            }
            else{
             $result = $conn->query("SELECT * FROM `instances` WHERE  `instance-item-pk` = '$pk' AND `instance-branch-location` = '$br' AND `is-archived` = $archived");
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