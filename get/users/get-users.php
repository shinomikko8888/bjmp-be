<?php
    function getUsers($conn, $archived){
        $result = $conn->query("SELECT * FROM `users` WHERE `is-archived` = $archived");
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

    function getUser($conn, $id, $em){
        if($id){
            $result = $conn->query("SELECT * FROM `users` WHERE `user-id` = $id");
        }
        else if ($em){
            $result = $conn->query("SELECT * FROM `users` WHERE `user-email` = $em");
        }
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode($data);
        } else {
            echo json_encode(array());
        }
    }
?>