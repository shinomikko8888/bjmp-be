<?php
    function getTransactions($conn, $br, $pid){
        if($pid && $br){
            $result = $conn->query("SELECT * FROM `transactions` WHERE `transaction-pdl-pk` = '$pid' AND `transaction-branch-location` = '$br' ORDER BY `transaction-id` DESC");
        }
        else if($br){
            $result = $conn->query("SELECT * FROM `transactions` WHERE `transaction-branch-location` = '$br' ORDER BY `transaction-id` DESC");
        }
        else{
            $result = $conn->query("SELECT * FROM `transactions` ORDER BY `transaction-id` DESC");
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

    function getCommodities($conn, $em, $br){
        if ($em){
            $result = $conn->query("SELECT * FROM `commodities` WHERE `commodity-user` = '$em'");
        }
        else{
            $result = $conn->query("SELECT * FROM `commodities`");
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

?>