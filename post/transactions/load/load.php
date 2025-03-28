<?php
    include './post/transactions/generate-receipt.php';
    include './post/log/post-log.php';

    function handleLoad($conn, $data){
        $currentDateTime = date('Y-m-d H:i:s');
        if($data){
            $getLastID = $conn->prepare("SELECT MAX(`transaction-id`) as last_id FROM transactions WHERE `transaction-branch-location` = ?");
            $getLastID->bind_param("s", $data['pdl-data']['pdl-branch-location']);
            $getLastID->execute();
            $lastIDResult = $getLastID->get_result();
            $lastIDData = $lastIDResult->fetch_assoc();
            $newID = $lastIDData['last_id'] !== null ? $lastIDData['last_id'] + 1 : 1;

            $timestamp = round(microtime(true) * 1000);
            $fileName = $timestamp . '_' . sprintf("%011d", $newID) .
            str_replace(' ', '', $data['loading-type']) . '_' .
            str_replace(' ', '', $data['pdl-data']['pdl-branch-location']) . '.pdf';
            $pdfFilePath = $_SERVER['DOCUMENT_ROOT'] . '/api/files/docs/receipts/load/' . $fileName;
            if (!file_exists(dirname($pdfFilePath))) {
                $pdfFilePath = __DIR__ . '/../../../files/docs/receipts/load/' . $fileName;
            }
            $transactionData= [
                'id' => sprintf("%011d", $newID),
                'invUser' => $data['active-email'],
                'pdlId'=> $data['pdl-data']['pdl-id'],
                'pk' => $data['pdl-data']['pk'],
                'oldBal' => $data['pdl-data']['pdl-balance'],
                'amtLoad' => $data['load-amount'],
                'cred'=> $data['pdl-creditor'],
                'type' => $data['loading-type'],
                'branch' => $data['pdl-data']['pdl-branch-location'],
            ];
            $loadAmount = floatval($data['load-amount']);
            $load = $conn->prepare("UPDATE `pdls` SET `pdl-balance` = `pdl-balance` + ? WHERE `pdl-id` = ? AND `pdl-branch-location` = ?");
            $load->bind_param("dis", $loadAmount, $data['pdl-data']['pdl-id'], $data['pdl-data']['pdl-branch-location']);
            if ($load->execute()) {
                generateLoadReceipt($data, $currentDateTime, $newID, $pdfFilePath);
                createTransaction($conn, 'Load', $transactionData, $currentDateTime, $fileName);
                $response = [
                    'success' => true,
                    'filepath' => $fileName,
                ];
            } else {
                $response = [
                    'success' => false,
                ];
            }
        }
        echo json_encode($response);
    }

?>