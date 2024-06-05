<?php
    include './get/misc/get-misc.php';
    function getDashboardInfo($conn, $fw, $em, $br){
        $data = [];
        switch ($fw) {
            case 'dashboard':
                getPDLSpendingTotalAllTime($conn, $em, $br, $data);
                getTotalTransactionsAllTime($conn, $em, $br, $data);
                getMostPopularProductAllTime($conn, $em, $br, $data);
                getMoneyInCirculation($conn, $em, $br, $data);
                break;
            default:
                
                break;
        }
        echo json_encode($data);
    }

    function getChartInfo($conn, $ctx, $ty, $br, $dt){
        $data = [];
        switch($ty) {
            case 'matrix':
                getMatrixData($conn, $ctx, $br, $dt, $data);
                break;
            case 'line':
                getLineData($conn, $ctx, $br, $dt, $data);
                break;
            case 'pie':
                getPieData($conn, $ctx, $br, $dt, $data);
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
            `pdl-branch-location` = '$br'");
        } else {
            $get = $conn->query("SELECT SUM(`pdl-balance`) AS in_circlulation FROM pdls");
        }
        if($get && $get->num_rows > 0){
            $result = $get->fetch_assoc();
            $data['money-in-circulation'] = $result['in_circlulation'];
        } else{
            $data['money-in-circulation'] = null;
        }
    }

    function getMatrixData($conn, $ctx, $br, $dt, &$data){
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
            default:
                break;
        }
    }

    function getLineData($conn, $ctx, $br, $dt, &$data){
        
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
                              WHERE DATE(`transaction-created-at`) BETWEEN '$selectedYear-01-01' AND '$selectedYear-12-31'
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
?>