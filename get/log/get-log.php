<?php
    include "./get/misc/get-misc.php";
    
    function getLogs($conn){
        $result = $conn->query("SELECT * FROM `logs` ORDER BY `log-id` DESC");
        if ($result && $result->num_rows > 0) {
            $data = array();
            while ($row = $result->fetch_assoc()) {
                // Check and decode valid JSON strings
                foreach (['log-item-details', 'log-instance-details', 'log-pdl-details', 'log-user-details', 'log-creditor-details'] as $column) {
                    if (isValidJson($row[$column])) {
                        $row[$column] = json_decode($row[$column], true);
                    }
                }
                $data[] = $row;
            }
            echo json_encode($data);
        } else {
            echo json_encode(array());
        }
    }

    function getLog($conn, $id){
        if($id){
            $result = $conn->query("SELECT * FROM `logs` WHERE `log-id` = $id");
        }
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            // Check and decode valid JSON strings
            foreach (['log-item-details', 'log-instance-details', 'log-pdl-details', 'log-user-details', 'log-creditor-details'] as $column) {
                if (isValidJson($data[$column])) {
                    $data[$column] = json_decode($data[$column], true);
                }
            }
            echo json_encode($data);
        } else {
            echo json_encode(array());
        }
    }
?>
