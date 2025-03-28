<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");
    header("Content-Type: application/json");
    require_once 'vendor/autoload.php';
    include './db-connect.php';
    //Get Functions

    include './get/general-data/get-data.php';
    include './get/instance/get-ins.php';
    include './get/item/get-item.php';
    include './get/lender/get-lender.php';
    include './get/log/get-log.php';
    /*
    include './get/misc/get-misc.php';
    */
    include './get/misc/get-settings.php';
    include './get/pdl/get-pdl.php';
    include './get/transactions/get-tran.php';
    include './get/users/get-users.php';
    //Post Functions
    include './post/instance/post-ins.php';
    include './post/item/post-item.php';
    include './post/lender/post-lender.php';
    include './post/log/post-log.php';
    /*
    include './post/misc/post-misc.php';
    */
    include './post/misc/post-settings.php';
    include './post/misc/post-report.php';
    include './post/pdl/post-pdl.php';
    include './post/transactions/load/load.php';
    include './post/transactions/pos/pos.php';
    /* 
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
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getPdls($conn, $br, 0);
                break;
            case "get-archived-pdls":
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getPdls($conn, $br, 1);
                break;
            case "get-pdl":
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getPdl($conn, $id, $br);
                break;
            case "get-items":
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getItems($conn, $br, 0);
                break;
            case "get-archived-items":
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getItems($conn, $br, 1);
                break;
            case "get-item":
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getItem($conn, $id, $br);
                break;
            case "get-instances":
                $pk = isset($_GET['pk']) ? $_GET['pk'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';   
                getInstances($conn, $pk, $br, 0);
                break;
             case "get-archived-instances":
                $pk = isset($_GET['pk']) ? $_GET['pk'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';   
                getInstances($conn, $pk, $br, 1);
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
                $pid = isset($_GET['pid']) ? $_GET['pid'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getLenders($conn, $pid, $br);
                break;
            case "get-lender":
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                $pid = isset($_GET['pid']) ? $_GET['pid'] : '';
                getLender($conn, $id, $br, $pid);
                break;
            case "get-transactions":
                $pid = isset($_GET['pid']) ? $_GET['pid'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getTransactions($conn, $br, $pid);
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
                $em = isset($_GET['em']) ? $_GET['em'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getCommodities($conn, $em, $br);
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
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                getSettings($conn, $br);
                break;
            case "get-details":
                
                $fw = isset($_GET['fw']) ? $_GET['fw'] : '';        //note: fw stands for "from where" meaning from where was it called, the dashboard page, pdl profile, etc.
                $em = isset($_GET['em']) ? $_GET['em'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getDashboardInfo($conn, $fw, $em, $br, $id);
                
                break;
            case "get-charts":
                $ctx = isset($_GET['ctx']) ? $_GET['ctx'] : '';
                $ty = isset($_GET['ty']) ? $_GET['ty'] : '';
                $br = isset($_GET['br']) ? $_GET['br'] : '';
                $dt = isset($_GET['dt']) ? $_GET['dt'] : '';
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getChartInfo($conn, $ctx, $ty, $br, $dt, $id);
                break;
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
            case "change-email":
            case "change-password":      
                manageUser($conn, $data, $action);
                break;
            case "add-pdl":
            case "edit-pdl":
            case "archive-pdl":
            case "retrieve-pdl":
            case "delete-pdl":
            case "set-fingerprint":
            case "remove-fingerprint":
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
            case "delete-commodity":
                manageCommodity($conn, $data, $action);
                break;
            case "add-lender":
            case "edit-lender":
            case "delete-lender":
                manageLender($conn, $data, $action);
                break;
            case "purchase-item":
                handlePurchase($conn, $data);
                break;
            case "load-pdl":
                handleLoad($conn, $data);
                break;
            case "change-settings":
                handleSettings($conn, $data);
                break;
            case "generate-report":
                handleReport($conn, $data);
                break;
            default:
                echo 'default';
                break;
            //Note to self: Add a util folder for changelog recording for ease of use.
        }
    }
?>