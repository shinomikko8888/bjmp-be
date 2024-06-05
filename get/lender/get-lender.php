<?php
    function getLenders($conn, $pid, $br){
        if ($pid && $br){
            $result = $conn->query("SELECT * FROM `lenders` WHERE `lender-related-pdl` = '$pid' AND `lender-branch-location` = '$br'");
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
    function getLender($conn, $id, $br, $pid){
        $result = $conn->query("SELECT * FROM `lenders` WHERE `lender-id` = '$id' AND `lender-branch-location` = '$br' AND `lender-related-pdl` = '$pid'");
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode($data);
        } else {
            echo json_encode(array());
        }
    }
?>