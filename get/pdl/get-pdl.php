<?php
    function getPdls($conn, $br, $archived){
        if ($br) {
            $result = $conn->query("SELECT * FROM `pdls` WHERE `is-archived` = $archived AND `pdl-branch-location` = '$br'");
        } else {
            $result = $conn->query("SELECT * FROM `pdls` WHERE `is-archived` = $archived");
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
    function getPdl($conn, $id, $br){
        if($id){
            $result = $conn->query("SELECT * FROM `pdls` WHERE `pdl-id` = $id");
        }
        else if ($br){
            $result = $conn->query("SELECT * FROM `pdls` WHERE `pdl-id` = $id AND `pdl-branch-location` = $br");
        }
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode($data);
        } else {
            echo json_encode(array());
        }
    }
?>