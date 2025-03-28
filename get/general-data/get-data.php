<?php
    include './get/misc/get-misc.php';
    function getDashboardInfo($conn, $fw, $em, $br, $id){
        $data = [];
        switch ($fw) {
            case 'dashboard':
                getPDLSpendingTotalAllTime($conn, $em, $br, $data);
                getTotalTransactionsAllTime($conn, $em, $br, $data);
                getMostPopularProductAllTime($conn, $em, $br, $data);
                getMoneyInCirculation($conn, $em, $br, $data);
                getPDLsWithLowBalance($conn, $em, $br, $data);
                getHighestSpender($conn, $em, $br, $data);
                getPopularItems($conn, $em, $br, $data);
                getLowStockedItems($conn, $em, $br, $data);
                break;
            case 'profile':
                getPDLTotalTransactions($conn, $em, $data, $id);
                getPDLFavoriteProduct($conn, $em, $data, $id);
                getPDLAmountSpent($conn, $em, $data, $id);
                break;
            default:
                break;
        }
        echo json_encode($data);
    }

    function getChartInfo($conn, $ctx, $ty, $br, $dt, $id){
        $data = [];
        switch($ty) {
            case 'matrix':
                getMatrixData($conn, $ctx, $br, $dt, $data, $id);
                break;
            case 'line':
                getLineData($conn, $ctx, $br, $dt, $data, $id);
                break;
            case 'pie':
                getPieData($conn, $ctx, $br, $dt, $data);
                break;
            case 'bar':
                getBarData($conn, $ctx, $br, $dt, $data, $id);
                break;
            default:
                break;
        }
        echo json_encode($data);
    }
    function getPDLSpendingTotalAllTime($conn, $em, $br, &$data){
        if($br){
            $get = $conn->query("SELECT SUM(`transaction-amount`) AS total_spending FROM transactions WHERE 
            `transaction-branch-location` = '$br' AND `transaction-type` = 'Purchase'");
        } else {
            $get = $conn->query("SELECT SUM(`transaction-amount`) AS total_spending FROM transactions WHERE `transaction-type` = 'Purchase'");
        }
        if($get && $get->num_rows > 0){
            $result = $get->fetch_assoc();
            $data['all-time-pdl-spending-total'] = $result['total_spending'];
        } else{
            $data['all-time-pdl-spending-total'] = null;
        }
    }

    function getTotalTransactionsAllTime($conn, $em, $br, &$data){
        if($br){
            $get = $conn->query("SELECT COUNT(*) AS total_transactions FROM transactions WHERE `transaction-branch-location` = '$br'");
        } else {
            $get = $conn->query("SELECT COUNT(*) AS total_transactions FROM transactions");
        }
        if($get && $get->num_rows > 0){
            $result = $get->fetch_assoc();
            $data['all-time-total-transactions'] = $result['total_transactions'];
        } else{
            $data['all-time-total-transactions'] = null;
        }
    }

    function getMostPopularProductAllTime($conn, $em, $br, &$data){
        if($br){
            $query = "SELECT `transaction-items` FROM transactions WHERE `transaction-branch-location` = '$br' AND `transaction-type` = 'Purchase'";
        } else {
            $query = "SELECT `transaction-items` FROM transactions WHERE `transaction-type` = 'Purchase'";
        }
    
        $get = $conn->query($query);
    
        if ($get && $get->num_rows > 0) {
            $all_products = []; // Array to store all products
    
            // Loop through each row
            while ($row = $get->fetch_assoc()) {
                // Decode JSON
                $products = json_decode($row['transaction-items'], true);
                // Merge with existing products
                $all_products = array_merge($all_products, $products);
            }
    
            // Initialize an array to store product names and their quantities
            $product_quantities = [];
    
            // Loop through each product
            foreach ($all_products as $product) {
                $product_type = str_replace(' ', '', substr($product['type'], 0, 3));
                $product_name = str_replace(' ', '', substr($product['name'], 0, 3));
                $product_quantity = intval($product['quantity']); // Convert quantity to integer
                // Construct the abbreviated product name
                $product_key = $product_type . '-' . $product_name;
        
                // If the product name already exists in the array, increment its quantity
                if (isset($product_quantities[$product_key])) {
                    $product_quantities[$product_key] += $product_quantity;
                } else { // Otherwise, initialize it
                    $product_quantities[$product_key] = $product_quantity;
                }
            }
    
            // Sort the products by quantity in descending order
            arsort($product_quantities);
    
            // Assign to data
            $data['most-popular-products'] = $product_quantities;
        } else {
            $data['most-popular-products'] = null;
        }
    }

    function getMoneyInCirculation($conn, $em, $br, &$data){
        if($br){
            $get = $conn->query("SELECT SUM(`pdl-balance`) AS in_circlulation FROM pdls WHERE 
            `pdl-branch-location` = '$br' AND `is-archived` = 0");
        } else {
            $get = $conn->query("SELECT SUM(`pdl-balance`) AS in_circlulation FROM pdls WHERE `is-archived` = 0");
        }
        if($get && $get->num_rows > 0){
            $result = $get->fetch_assoc();
            $data['money-in-circulation'] = $result['in_circlulation'];
        } else{
            $data['money-in-circulation'] = null;
        }
    }

    function getPDLsWithLowBalance($conn, $em, $br, &$data){
        if($br){
            $get = $conn->query("SELECT * FROM pdls WHERE `pdl-balance` <= 50 AND `is-archived` = 0 AND
            `pdl-branch-location` = '$br'");
        } else {
            $get = $conn->query("SELECT * FROM pdls WHERE `pdl-balance` <= 50 AND `is-archived` = 0");
        }
        if($get && $get->num_rows > 0){
            $results = [];
            while($row = $get->fetch_assoc()) {
                $results[] = $row;
            }
            $data['pdls-with-low-balance'] = $results;
        } else {
            $data['pdls-with-low-balance'] = null;
        }
    }

    function getHighestSpender($conn, $em, $br, &$data){
        if($br){
            $get = $conn->query("SELECT `transaction-pdl`, SUM(`transaction-amount`) AS total_spending FROM transactions 
            WHERE `transaction-type` = 'Purchase' AND `transaction-branch-location` = '$br'
            GROUP BY `transaction-pdl` ORDER BY total_spending DESC");
        } else {
            $get = $conn->query("SELECT `transaction-pdl`, SUM(`transaction-amount`) AS total_spending FROM transactions 
            WHERE `transaction-type` = 'Purchase' 
            GROUP BY `transaction-pdl` ORDER BY total_spending DESC");
        }
        $highestSpenders = [];

    if ($get && $get->num_rows > 0) {
        while ($result = $get->fetch_assoc()) {
            $transactionPdl = $result['transaction-pdl'];
            $totalSpending = $result['total_spending'];

            // Query to get PDL information from pdls table
            $pdlInfoQuery = $conn->query("SELECT * FROM pdls WHERE `pdl-id` = '$transactionPdl'");

            if ($pdlInfoQuery && $pdlInfoQuery->num_rows > 0) {
                $pdlInfo = $pdlInfoQuery->fetch_assoc();

                // Combine the PDL info with the total spending
                $highestSpenders[] = array_merge($pdlInfo, ['total_spending' => $totalSpending]);
            }
        }
    }

    $data['highest-spenders'] = $highestSpenders;
    }

    function getPopularItems($conn, $em, $br, &$data){
        if($br){
            $query = "SELECT `transaction-items` FROM transactions WHERE `transaction-branch-location` = '$br' AND `transaction-type` = 'Purchase'";
        } else {
            $query = "SELECT `transaction-items` FROM transactions WHERE `transaction-type` = 'Purchase'";
        }
    
        $get = $conn->query($query);
    
        if ($get && $get->num_rows > 0) {
            $product_sales = []; // Array to store product sales
    
            // Loop through each row
            while ($row = $get->fetch_assoc()) {
                // Decode JSON
                $products = json_decode($row['transaction-items'], true);
    
                // Loop through each product
                foreach ($products as $product) {
                    $product_type = $product['type'];
                    $product_name = $product['name'];
                    $product_quantity = intval($product['quantity']); // Convert quantity to integer
    
                    // Create a unique key for each product to avoid duplication
                    $product_key = $product_type . '|' . $product_name;
    
                    // If the product already exists in the array, increment its quantity
                    if (isset($product_sales[$product_key])) {
                        $product_sales[$product_key]['sales'] += $product_quantity;
                    } else { // Otherwise, initialize it
                        $product_sales[$product_key] = [
                            'product-type' => $product_type,
                            'product-name' => $product_name,
                            'sales' => $product_quantity
                        ];
                    }
                }
            }
    
            // Convert associative array to indexed array
            $product_sales = array_values($product_sales);
    
            // Sort the products by sales in descending order
            usort($product_sales, function($a, $b) {
                return $b['sales'] - $a['sales'];
            });
    
            // Assign to data
            $data['popular-items'] = $product_sales;
        } else {
            $data['popular-items'] = null;
        }
    }

    function getLowStockedItems($conn, $em, $br, &$data){
        if($br){
            $query = "SELECT items.*, COALESCE(SUM(instances.`instance-remaining-stock`), 0) AS `item-remaining-stock`
            FROM `items`
            LEFT JOIN `instances` ON items.`item-type` = instances.`instance-type`
                                AND items.`item-name` = instances.`instance-name`
                                AND items.`item-branch-location` = instances.`instance-branch-location`
            WHERE items.`is-archived` = 0 AND items.`item-branch-location` = '$br'
            GROUP BY items.`item-id`";
        } else {
            $query = "SELECT items.*, COALESCE(SUM(instances.`instance-remaining-stock`), 0) AS `item-remaining-stock`
            FROM `items`
            LEFT JOIN `instances` ON items.`item-type` = instances.`instance-type`
                                AND items.`item-name` = instances.`instance-name`
                                AND items.`item-branch-location` = instances.`instance-branch-location`
            WHERE items.`is-archived` = 0
            GROUP BY items.`item-id`";
        }

        $result = $conn->query($query);

        // Initialize an array to hold the low-stocked items
        $lowStockedItems = array();

        // Check if the query was successful and fetch the results
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // You can add additional conditions to check for low stock
                // For example, if the item stock is below a certain threshold
                if ($row['item-remaining-stock'] < $row['item-critical-threshold']) {
                    $lowStockedItems[] = $row;
                }
            }
        }

        // Add the low-stocked items array to the $data dataset
        $data['low-stocked-items'] = $lowStockedItems;
    }

    function getPDLTotalTransactions($conn, $em, &$data, $id){
        $query = "SELECT COUNT(*) AS total_transactions FROM transactions WHERE `transaction-pdl` = '$id'";
        $result = $conn->query($query); 

        if ($result) {
            $row = $result->fetch_assoc();
            $data['pdl-total-transactions'] = $row['total_transactions']; 
        } else {
            $data['pdl-total-transactions'] = null; 
        }
    }
    
    function getPDLFavoriteProduct($conn, $em, &$data, $id){

        $query = "SELECT `transaction-items` FROM transactions WHERE `transaction-pdl` = '$id' AND `transaction-type` = 'Purchase'";
        $get = $conn->query($query);

        if ($get && $get->num_rows > 0) {
            $maxQuantity = 0; // Variable to store the maximum quantity
            $favoriteProduct = null; // Variable to store the favorite product

            // Loop through each row
            while ($row = $get->fetch_assoc()) {
                // Decode JSON
                $products = json_decode($row['transaction-items'], true);

                // Loop through each product
                foreach ($products as $product) {
                    $product_quantity = intval($product['quantity']); // Convert quantity to integer
                    
                    // If the current product quantity is greater than the maximum quantity found so far
                    if ($product_quantity > $maxQuantity) {
                        // Update the maximum quantity and favorite product
                        $maxQuantity = $product_quantity;
                        $favoriteProduct = $product;
                    }
                }
            }

            // If a favorite product was found, construct its abbreviated key
            if ($favoriteProduct) {
                $product_key = $favoriteProduct['type'] . '-' . $favoriteProduct['name'];

                // Assign the favorite product to data
                $data['favorite-product'] = [
                    'name' => $product_key
                ];
            } else {
                $data['favorite-product'] = null; // No favorite product found
            }
        } else {
            $data['favorite-product'] = null; // No data found
        }
    }

    function getPDLAmountSpent($conn, $em, &$data, $id){
        $query = "SELECT SUM(`transaction-amount`) AS total_spent FROM transactions WHERE `transaction-pdl` = '$id' AND
        `transaction-type` = 'Purchase'";
        $result = $conn->query($query); 

        if ($result) {
            $row = $result->fetch_assoc();
            $data['amount-spent'] = $row['total_spent']; 
        } else {
            $data['amount-spent'] = null; 
        }
    }




    // Charts
    function getMatrixData($conn, $ctx, $br, $dt, &$data, $id){
        $dtObject = json_decode($dt);
        if ($dtObject === null) {
            $selectedMonth = date('m'); // Current month number
            $selectedYear = date('Y'); // Current year
            $allTimeEnabled = false;
        } else {
            // Convert named month to number and ensure it has a leading zero if needed
            $selectedMonth = str_pad(date_parse($dtObject->{$ctx . '-selected-month'})['month'], 2, '0', STR_PAD_LEFT);
            $selectedYear = $dtObject->{$ctx . '-selected-year'};
            $allTimeEnabled = $dtObject->{$ctx . '-all-time-enabled'};
        }
        switch($ctx) {
            case 'spendTotal':
                if($allTimeEnabled){
                    $query = "SELECT `transaction-amount`, DATE(`transaction-created-at`) AS `transaction-date`
                        FROM transactions
                        WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-01-01' AND '$selectedYear-12-31'
                        AND `transaction-type` = 'Purchase'
                        ORDER BY `transaction-date`";
                } else {
                    $query = "SELECT `transaction-amount`, DATE(`transaction-created-at`) AS `transaction-date`
                    FROM transactions
                    WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-$selectedMonth-01' AND LAST_DAY('$selectedYear-$selectedMonth-01')
                    AND `transaction-type` = 'Purchase'
                    ORDER BY `transaction-date`";
                }
                $result = $conn->query($query);
                $transactions = array();
                    while ($row = $result->fetch_assoc()) {
                        $date = $row['transaction-date'];
                        $amount = floatval($row['transaction-amount']);
                        if (!isset($transactions[$date])) {
                            $transactions[$date] = 0;
                        }
                        $transactions[$date] += $amount;
                    }
                generateMatrixChart($selectedMonth, $selectedYear, $transactions, $ctx, $data, $allTimeEnabled);
                break;
            case 'totalTransac':
                if($allTimeEnabled){
                    $query = "SELECT COUNT(*) AS total_transactions, DATE(`transaction-created-at`) AS `transaction-date`
                    FROM transactions
                    WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-01-01' AND LAST_DAY('$selectedYear-12-31')
                    GROUP BY `transaction-date`
                    ORDER BY `transaction-date`";
                } else {
                    $query = "SELECT COUNT(*) AS total_transactions, DATE(`transaction-created-at`) AS `transaction-date`
                    FROM transactions
                    WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-$selectedMonth-01' AND LAST_DAY('$selectedYear-$selectedMonth-01')
                    GROUP BY `transaction-date`
                    ORDER BY `transaction-date`";
                }
                
                $result = $conn->query($query);
                $transactions = array();
                while ($row = $result->fetch_assoc()) {
                    $date = $row['transaction-date'];
                    $totalTransactions = intval($row['total_transactions']);
                    $transactions[$date] = $totalTransactions;
                }
                generateMatrixChart($selectedMonth, $selectedYear, $transactions, $ctx, $data, $allTimeEnabled);
                break;
            case 'transactionMetrics':
                if($allTimeEnabled){
                    $query = "SELECT COUNT(*) AS total_transactions, DATE(`transaction-created-at`) AS `transaction-date`
                    FROM transactions
                    WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-01-01' AND LAST_DAY('$selectedYear-12-31') AND `transaction-pdl` = '$id'
                    GROUP BY `transaction-date`
                    ORDER BY `transaction-date`";
                } else {
                    $query = "SELECT COUNT(*) AS total_transactions, DATE(`transaction-created-at`) AS `transaction-date`
                    FROM transactions
                    WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-$selectedMonth-01' AND LAST_DAY('$selectedYear-$selectedMonth-01')
                    AND `transaction-pdl` = '$id'
                    GROUP BY `transaction-date`
                    ORDER BY `transaction-date`";
                }
                
                $result = $conn->query($query);
                $transactions = array();
                while ($row = $result->fetch_assoc()) {
                    $date = $row['transaction-date'];
                    $totalTransactions = intval($row['total_transactions']);
                    $transactions[$date] = $totalTransactions;
                }
                generateMatrixChart($selectedMonth, $selectedYear, $transactions, $ctx, $data, $allTimeEnabled);
                break;
            default:
                break;
        }
    }

    function getLineData($conn, $ctx, $br, $dt, &$data, $id){
        
        $dtObject = json_decode($dt);
        if ($dtObject === null) {
            $selectedMonth = date('m'); // Current month number
            $selectedYear = date('Y'); // Current year
            $allTimeEnabled = false;
        } else {
            // Convert named month to number and ensure it has a leading zero if needed
            $selectedMonth = str_pad(date_parse($dtObject->{$ctx . '-selected-month'})['month'], 2, '0', STR_PAD_LEFT);
            $selectedYear = $dtObject->{$ctx . '-selected-year'};
            $allTimeEnabled = $dtObject->{$ctx . '-all-time-enabled'};
            $selectedBranch = $dtObject->{$ctx . '-selected-branch'};
        }
        $transactions = [];
        switch ($ctx) {
            case 'profit':
                if ($selectedBranch && $selectedBranch !== 'BJMPRO-III Main Office') {
                    $query = "SELECT `transaction-amount`, `transaction-items`, DATE(`transaction-created-at`) AS `transaction-date`
                              FROM transactions 
                              WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-01-01' AND '$selectedYear-12-31'
                              AND `transaction-type` = 'Purchase' AND `transaction-branch-location` = '$selectedBranch' 
                              ORDER BY `transaction-date`";
                } else {
                    $query = "SELECT `transaction-amount`, `transaction-items`, DATE(`transaction-created-at`) AS `transaction-date`
                              FROM transactions 
                              WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-$selectedMonth-01' AND LAST_DAY('$selectedYear-$selectedMonth-01')
                              AND `transaction-type` = 'Purchase'
                              ORDER BY `transaction-date`";
                }
    
                $result = $conn->query($query);
    
                while ($row = $result->fetch_assoc()) {
                    $date = $row['transaction-date'];
                    $transactionAmount = floatval($row['transaction-amount']);
                    $items = json_decode($row['transaction-items'], true);
    
                    if (!isset($transactions[$date])) {
                        $transactions[$date] = [
                            'revenue' => 0,
                            'costs' => 0
                        ];
                    }
    
                    $transactions[$date]['revenue'] += $transactionAmount;
    
                    foreach ($items as $item) {
                        $transactions[$date]['costs'] += $item['price'] * $item['quantity'];
                    }
                }
    
                generateLineChart($selectedYear, $selectedBranch, $transactions, $ctx, $data, $allTimeEnabled);
                break;
            case 'profitModal':
                $query = "SELECT `transaction-amount`, `transaction-items`, DATE(`transaction-created-at`) AS `transaction-date`
                              FROM transactions 
                              WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-01-01' AND '$selectedYear-12-31'
                              AND `transaction-type` = 'Purchase' 
                              ORDER BY `transaction-date`";
                $result = $conn->query($query);
    
                while ($row = $result->fetch_assoc()) {
                    $date = $row['transaction-date'];
                    $transactionAmount = floatval($row['transaction-amount']);
                    $items = json_decode($row['transaction-items'], true);
    
                    if (!isset($transactions[$date])) {
                        $transactions[$date] = [
                            'revenue' => 0,
                            'costs' => 0
                        ];
                    }
    
                    $transactions[$date]['revenue'] += $transactionAmount;
    
                    foreach ($items as $item) {
                        // Compare item id with passed id
                        if ($item['id'] == $id) {
                            $transactions[$date]['costs'] += $item['price'] * $item['quantity'];
                        }
                    }
                }
                generateLineChart($selectedYear, $selectedBranch, $transactions, $ctx, $data, $allTimeEnabled);
                break;
            default:
                break;
        }
    }

    function getPieData($conn, $ctx, $br, $dt, &$data){
        $dtObject = json_decode($dt);
        if ($dtObject === null) {
        } else {
            $selectedBranch = $dtObject->{$ctx . '-selected-branch'};
        }
        switch ($ctx) {
            case 'vulnerable':
                if ($selectedBranch && $selectedBranch !== 'BJMPRO-III Main Office') {
                    $query = "SELECT 
                    CASE WHEN `pdl-age` > 65 THEN 'Senior' 
                    WHEN `pdl-gender` NOT IN ('Male', 'Female') AND `pdl-other-gender` IS NOT NULL THEN 'LGBT' 
                    WHEN `pdl-medical-condition` IS NOT NULL THEN 'PWD' ELSE 'Regular' 
                    END AS category, COUNT(*) AS count 
                    FROM pdls WHERE `is-archived` = 0 AND `pdl-branch-location` = '$selectedBranch'
                    GROUP BY category";
                } else {
                    $query = "SELECT 
                    CASE WHEN `pdl-age` > 65 THEN 'Senior' 
                    WHEN `pdl-gender` NOT IN ('Male', 'Female') AND `pdl-other-gender` IS NOT NULL THEN 'LGBT' 
                    WHEN `pdl-medical-condition` IS NOT NULL THEN 'PWD' ELSE 'Regular' 
                    END AS category, COUNT(*) AS count 
                    FROM pdls WHERE `is-archived` = 0
                    GROUP BY category";
                }
                $result = $conn->query($query);
            
                // Check if the query executed successfully
                if ($result) {
                    $data = array();
                    // Fetch associative array
                    while ($row = $result->fetch_assoc()) {
                        $data[] = array(
                            "label" => $row['category'],
                            "value" => $row['count']
                        );
                    }
                    // Free result set
                    $result->free();
                } else {
                    // Handle query execution error
                    // Example: echo "Error: " . $conn->error;
                }
                break;
            default:
                break;
        }
    }

    function getBarData($conn, $ctx, $br, $dt, &$data, $id){
        $dtObject = json_decode($dt);
        if ($dtObject === null) {
            $selectedMonth = date('m'); // Current month number
            $selectedYear = date('Y'); // Current year
            $allTimeEnabled = false;
        } else {
            // Convert named month to number and ensure it has a leading zero if needed
            $selectedMonth = str_pad(date_parse($dtObject->{$ctx . '-selected-month'})['month'], 2, '0', STR_PAD_LEFT);
            $selectedYear = $dtObject->{$ctx . '-selected-year'};
            $allTimeEnabled = $dtObject->{$ctx . '-all-time-enabled'};
            $selectedBranch = $dtObject->{$ctx . '-selected-branch'};
        }
        switch ($ctx) {
            case 'productOverview':
                if($allTimeEnabled){
                    $query = "SELECT `transaction-items`, DATE(`transaction-created-at`) AS `transaction-date`
                     FROM transactions WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-01-01' AND '$selectedYear-12-31' AND
                      `transaction-type` = 'Purchase' AND `transaction-pdl` = '$id'";
                } else {
                    $query = "SELECT `transaction-items`, DATE(`transaction-created-at`) AS `transaction-date`
                     FROM transactions WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-$selectedMonth-01' AND LAST_DAY('$selectedYear-$selectedMonth-01') AND
                      `transaction-type` = 'Purchase' AND `transaction-pdl` = '$id'";
                }

                $result = $conn->query($query);
                $transactions = array();

                // Process each row from the query result
                while ($row = $result->fetch_assoc()) {
                                    $items = json_decode($row['transaction-items'], true);

                    // Process each item in the transaction
                    foreach ($items as $item) {
                        $product = $item['type'] . ' - ' . $item['name']; // Construct product name and type
                        $quantity = intval($item['quantity']); // Convert quantity to integer

                        // Check if the product already exists in $transactions
                        $productFound = false;
                        foreach ($transactions as &$transaction) {
                            if ($transaction['product'] === $product) {
                                // If product found, update sales
                                $transaction['sales'] += $quantity;
                                $productFound = true;
                                break;
                            }
                        }

                        // If product not found, add it to $transactions
                        if (!$productFound) {
                            $transactions[] = array(
                                'product' => $product,
                                'sales' => $quantity
                            );
                        }
                    }
                }
                $data[] = $transactions;
                break;
            default:
                break;
        }
    }
?>