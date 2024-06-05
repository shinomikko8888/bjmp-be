<?php
    function getItems($conn, $br, $archived){
        if ($br){
            $result = $conn->query("SELECT items.*, COALESCE(SUM(instances.`instance-remaining-stock`), 0) AS `item-remaining-stock`
            FROM `items`
            LEFT JOIN `instances` ON items.`item-type` = instances.`instance-type`
                                AND items.`item-name` = instances.`instance-name`
                                AND items.`item-branch-location` = instances.`instance-branch-location`
            WHERE items.`is-archived` = $archived AND items.`item-branch-location` = '$br'
            GROUP BY items.`item-id`");
        } else {
            $result = $conn->query("SELECT items.*, COALESCE(SUM(instances.`instance-remaining-stock`), 0) AS `item-remaining-stock`
            FROM `items`
            LEFT JOIN `instances` ON items.`item-type` = instances.`instance-type`
                                AND items.`item-name` = instances.`instance-name`
                                AND items.`item-branch-location` = instances.`instance-branch-location`
            WHERE items.`is-archived` = $archived
            GROUP BY items.`item-id`");
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
    function getItem($conn, $id, $br){
        if($id){
            $result = $conn->query("SELECT items.*, COALESCE(SUM(instances.`instance-remaining-stock`), 0) AS `item-remaining-stock`
                                    FROM `items`
                                    LEFT JOIN `instances` ON items.`item-type` = instances.`instance-type`
                                                        AND items.`item-name` = instances.`instance-name`
                                                        AND items.`item-branch-location` = instances.`instance-branch-location`
                                    WHERE items. `item-id` = $id
                                    GROUP BY items.`item-id`");
        }
        else if ($br){
            $result = $conn->query("SELECT items.*, COALESCE(SUM(instances.`instance-remaining-stock`), 0) AS `item-remaining-stock`
                                    FROM `items`
                                    LEFT JOIN `instances` ON items.`item-type` = instances.`instance-type`
                                                        AND items.`item-name` = instances.`instance-name`
                                                        AND items.`item-branch-location` = instances.`instance-branch-location`
                                    WHERE items. `item-id` = $id AND items. `item-branch-location` = $br
                                    GROUP BY items.`item-id`");
        }
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode($data);
        } else {
            echo json_encode(array());
        }
    }
?>