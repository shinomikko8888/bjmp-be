<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");
    header("Content-Type: application/json");

    include './db-connect.php';
    //Get Functions
    /*
    include './get/general-data/get-data.php';
    */
    include './get/instance/get-ins.php';
    include './get/item/get-item.php';
    /*
    include './get/lender/get-lender.php';
    */
    include './get/log/get-log.php';
    /*
    include './get/misc/get-misc.php';
    include './get/misc/get-settings.php';
    */
    include './get/pdl/get-pdl.php';
    /*
    include './get/transactions/get-tran.php';
    */
    include './get/users/get-users.php';
    //Post Functions
    include './post/instance/post-ins.php';
    include './post/item/post-item.php';
    /*
    include './post/lender/post-lender.php';
    include './post/log/post-log.php';
    include './post/misc/post-misc.php';
    include './post/misc/post-settings.php';
    */
    include './post/pdl/post-pdl.php';
    /* 
    include './post/transactions/load/load.php';
    include './post/transactions/pos/pos.php';
    include './post/transactions/generate-receipt.php';
    */
    include './post/users/post-users.php';

    $objDB = new dbConnect;
    $conn = $objDB->connect();
    $method = $_SERVER['REQUEST_METHOD'];
    date_default_timezone_set('Asia/Manila');
    

    if ($method === "GET"){
        $action = isset($_GET['a']) ? $_GET['a'] : '';
        switch($action){
            case "get-users":
                getUsers($conn, 0);
                break;
            case "get-archived-users":
                getUsers($conn, 1);
                break;
            case "get-user":
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $em = isset($_GET['em']) ? $_GET['em'] : '';
                getUser($conn, $id, $em);
                break;
            case "get-pdls":
                getPdls($conn, 0);
                break;
            case "get-archived-pdls":
                getPdls($conn, 1);
                break;
            case "get-pdl":
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getPdl($conn, $id, $br);
                break;
            case "get-items":
                getItems($conn, 0);
                break;
            case "get-archived-items":
                getItems($conn, 1);
                break;
            case "get-item":
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getItem($conn, $id, $br);
                break;
            case "get-instances":
                $it = isset($_GET['it']) ? $_GET['it'] : '';
                $in = isset($_GET['in']) ? $_GET['in'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';   
                getInstances($conn, $it, $in, $br, 0);
                break;
             case "get-archived-instances":
                $it = isset($_GET['it']) ? $_GET['it'] : '';
                $in = isset($_GET['in']) ? $_GET['in'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';   
                getInstances($conn, $it, $in, $br, 1);
                break;
            case "get-instance":
                /*
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                $ii = isset($_GET['ii']) ? $_GET['ii'] : '';
                getInstance($conn, $id, $br, $ii);
                */ 
                break;
            case "get-lenders":
                //getLenders($conn, 0);
                break;
            case "get-archived-lenders":
                //getLenders($conn, 1);
                break;
            case "get-lender":
                /*
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                $pdl = isset($_GET['pdl']) ? $_GET['pdl'] : '';
                getLender($conn, $id, $br, $pdl);
                */ 
                break;
            case "get-transactions":
                //getTransactions($conn);
                break;
            case "get-transaction":
                /*
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getTransaction($conn, $id, $br);
                */ 
                break;
            case "get-logs":
                getLogs($conn);
                break;
            case "get-log":
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getLog($conn, $id);
                break;
            case "get-commodities":
                /*
                $em = isset($_GET['em']) ? $_GET['em'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getCommodities($conn, $em, $br);
                */
                break;
            case "get-commodity":
                /*
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $em = isset($_GET['em']) ? $_GET['em'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getLog($conn, $id, $em, $br);
                */ 
                break;
            case "get-settings":
                /*
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getSettings($conn, $id);
                */
                break;
            case "get-details":
                /*
                $fw = isset($_GET['fw']) ? $_GET['fw'] : '';        //note: fw stands for "from where" meaning from where was it called, the dashboard page, pdl profile, etc.
                $ty = isset($_GET['ty']) ? $_GET['ty'] : '';
                $em = isset($_GET['em']) ? $_GET['em'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getDashboardInfo($conn, $fw, $ty, $em, $br);
                */
            default:
                echo "default";
                break;   
        }
    }
    elseif ($method === "POST"){
        $data = $_POST;
        $action = $data['action'];
        switch($action){
            case "login-user":
                loginUser($conn, $data);
                break;
            case "logout-user":
                logoutUser($conn, $data);
                break;
            case "add-user":
            case "edit-user":
            case "archive-user":
            case "retrieve-user":
            case "delete-user":
                manageUser($conn, $data, $action);
                break;
            case "add-pdl":
            case "edit-pdl":
            case "archive-pdl":
            case "retrieve-pdl":
            case "delete-pdl":
                managePdl($conn, $data, $action);
                break;
            case "add-item":
            case "edit-item":
            case "archive-item":
            case "retrieve-item":
            case "delete-item":
                manageItem($conn, $data, $action);
                break;
            case "add-instance":
            case "edit-instance":
            case "archive-instance":
            case "retrieve-instance":
            case "delete-instance":
                manageInstance($conn, $data, $action);
                break;
            case "add-commodity":
            case "edit-commodity":
            case "delete-commodity":
                //manageCommodity($conn, $data, $action);
                break;
            case "add-lender":
            case "edit-lender":
            case "archive-lender":
            case "retrieve-lender":
            case "delete-lender":
                //manageLender($conn, $data, $action);
                break;
            case "purchase-item":
                //handlePurchase($conn, $data);
                break;
            case "load-user":
                //handleLoad($conn, $data);
                break;
            case "change-settings":
                //handleSettings($conn, $data);
            default:
                echo 'default';
                break;
            //Note to self: Add a util folder for changelog recording for ease of use.
        }
    }
?>