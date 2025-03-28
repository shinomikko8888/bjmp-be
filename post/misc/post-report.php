<?php

    //For TCPDF Live: $tcpdfPath = './TCPDF/tcpdf.php';
    function handleReport($conn, $data){
        $type = $data['method'];
        switch($type){
            case "pdl-profile":
                generatePDLProfileReport($conn, $data);
                break;
            default:
                break;
        }
    }

    function generatePDLProfileReport($conn, $data){
        $currentDateTime = date('Y-m-d H:i:s');
        if($data){
            $findPDL = $conn->prepare("SELECT * FROM `pdls` WHERE `pk` = ?");
            $findPDL->bind_param("i", $data['userId']);
            $findPDL->execute();
            $findPDLCheck = $findPDL->get_result();
            $findPDLData = $findPDLCheck->fetch_assoc();

            $getCashAmt = $conn->prepare("SELECT SUM(`transaction-amount`) AS `total-amount` FROM `transactions` WHERE `transaction-pdl` = ? AND `transaction-type` = 'Load'");
            $getCashAmt->bind_param("i", $data['userId']);
            $getCashAmt->execute();
            $getCashAmtCheck = $getCashAmt->get_result();
            $getCashAmtData = $getCashAmtCheck->fetch_assoc();

            $getSpendAmt = $conn->prepare("SELECT SUM(`transaction-amount`) AS `total-amount` FROM `transactions` WHERE `transaction-pdl` = ? AND `transaction-type` = 'Purchase'");
            $getSpendAmt->bind_param("i", $data['userId']);
            $getSpendAmt->execute();
            $getSpendAmtCheck = $getSpendAmt->get_result();
            $getSpendAmtData = $getSpendAmtCheck->fetch_assoc();

            $getRecTra = $conn->prepare("SELECT * FROM `transactions` WHERE `transaction-pdl-pk` = ?");
            $getRecTra->bind_param("i", $data['userId']);
            $getRecTra->execute();
            $getRecTraCheck = $getRecTra->get_result();
            $getRecTraData = $getRecTraCheck->fetch_all(MYSQLI_ASSOC);

            $timestamp = round(microtime(true) * 1000);
            $fileName = $timestamp . '_' .
            str_replace(' ', '', $data['userId']) . '_' .
            str_replace(' ', '', $findPDLData['pdl-branch-location']) . '.pdf';
            $pdfFilePath = $_SERVER['DOCUMENT_ROOT'] . '/api/files/docs/reports/' . $fileName;

            if (!file_exists(dirname($pdfFilePath))) {
                $pdfFilePath = __DIR__ . '/../../../files/docs/reports/' . $fileName;
            }
            $reportData = [
                'name' => strtoupper($findPDLData['pdl-last-name']) . ", " .trim($findPDLData['pdl-first-name'] . ' ' . ($findPDLData['pdl-middle-name'] ? $findPDLData['pdl-middle-name'] . ' ' : '') ),
                'id' => 'PDL-'. $findPDLData['pdl-id'],
                'age' => $findPDLData['pdl-age'],
                'gender' => $findPDLData['pdl-gender'] ?? $findPDLData['pdl-gender'],
                'image' => basename($findPDLData['pdl-image']),
                'loadAmt' => ($getCashAmtData['total-amount'] ?? null) === null ? 0 : (float)$getCashAmtData['total-amount'],
                'spendAmt' => ($getSpendAmtData['total-amount'] ?? null) === null ? 0 : (float)$getSpendAmtData['total-amount'],
                'bal' => ($findPDLData['pdl-balance'] ?? null) === null ? 0 : (float)$findPDLData['pdl-balance'],
                'recTra' => $getRecTraData,
            ];
            generateReport($reportData, $currentDateTime, $fileName, 'pdls');

        }
    }


    if(!function_exists('generateReport')){
        function generateReport($data, $datetime, $pdfFilePath, $type){
            require_once('tcpdf/tcpdf.php');
            class CustomPDF extends TCPDF
            {
                public function Header()
                {
                    $this->SetTopMargin(5); 
                    $this->SetFont('tahomabd', 'B', 12);
                    $this->Cell(0, 7, 'Bureau of Jail Management and Penology Region III', 0, 1, 'C');
                    $this->SetFont('lcallig', '', 8);
                    $this->Cell(0, 4, '"Changing Lives, Building a Safer Nation"', 0, 1, 'C');
                    $this->Line(10, $this->GetY() + 2, $this->getPageWidth() - 10, $this->GetY() + 2);
                    $image_file =  $_SERVER['DOCUMENT_ROOT'] . '/api/files/images/static/bjmp-logo.png';
                    if (!file_exists(dirname($image_file))) {
                        // Fallback to alternative path if the original path does not exist
                        $image_file = __DIR__ . '\..\..\files\images\static\bjmp-logo.png';
                    }
                    $this->Image($image_file, 8, 6, 10, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                    $this->SetMargins(10, 20, 10);
                    $this->Ln(20);
                }
                public function Footer()
                {
                    $this->SetY(-15); 
                    $this->SetFont('tahoma', 'I', 8);
                    $currentYear = date('Y');
                    $this->Cell(0, 7, "Copyright Bureau of Jail Management and Penology Region III © $currentYear", 0, 1, 'C'); // Use 1 for line break
                    $this->SetFont('tahoma', 'I', 6);
                    $this->Cell(0, 3, "This report is valid only for the individual mentioned above. Any alterations or misuse of this report is punishable by law.", 0, 0, 'C');
                }

                public function AddWatermark()
                {
                    $image_file =  $_SERVER['DOCUMENT_ROOT'] . '/api/files/images/static/bjmp-logo.png';
                    if (!file_exists(dirname($image_file))) {
                        // Fallback to alternative path if the original path does not exist
                        $image_file = __DIR__ . '\..\..\files\images\static\bjmp-logo.png';
                    }
                    $this->SetAlpha(0.1); // Set transparency
                    $this->Image($image_file, ($this->getPageWidth() - 100) / 2, ($this->getPageHeight() - 100) / 2, 100, 100, '', '', '', false, 300, '', false, false, 0);
                    $this->SetAlpha(1); // Reset transparency to default
                }
                
            }
            $pdf = new CustomPDF('P', 'mm', array(216, 279), true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetTitle('Receipt');
            $pdf->AddPage();
            $pdf->AddWatermark();
            

            // Ad Profile Section (Image + Text)
            $profileImage = $_SERVER['DOCUMENT_ROOT'] . '/api/files/images/'. $type .'/'. $data['image'] .''; // Your profile image path
            if (!file_exists(dirname($profileImage))) {
                $profileImage = __DIR__ . '\..\..\files\images/'. $type .'/'. $data['image'] .'';
            }
            $imageData = base64_encode(file_get_contents($profileImage));
            $html = '
            <style>
                body {
                    font-family: "Tahoma";
                    padding: 0;
                }
                th, td {
                    border: none;
                }
                .table-row {
                    padding-bottom: 10px; 
                }
                
            </style>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="text-align: left; font-size: 6px;">Date of Creation: ' . $datetime . '</td>
                    <td style="text-align: right; font-size: 6px;">Report For: ' . $data['id'] . '</td>
                </tr>
            </table>';
            if($type === 'pdls'){
                $html .= '<h3 style="width: 100%">PDL Profile</h3>
                <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="text-align: center; vertical-align: top; padding: 5px; border: 1px solid #000; height: 70px; width: 60%;" rowspan="6;">
                        <table style="margin: 0 auto;">
                            <tr>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                            </tr>
                            <tr>
                                <td>
                                    <img src="data:image/png;base64,' . $imageData . '" style="width: 80px; height: 80px; border-radius: 50%; display: block; margin: 0 auto;">
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="font-size: 22px; font-weight: bold;">' . $data['name'] . '</td> 
                            </tr>
                            <tr>
                                <td style="font-size: 8px; color: #555">' . $data['id'] . ', Age:' . $data['age'] . ', Gender: '. $data['gender'] .'</td> 
                            </tr>
                            <tr>
                                <td></td>
                            </tr>
                        </table>
                    </td>
                    <td style="font-size: 12px; background-color: white; padding: 5px; border: 1px solid #000; border-bottom: none; height: 10px; width: 40%;">Amount Loaded</td>
                        </tr>
                        <tr>
                            <td style="font-size: 26px; font-weight: bold; background-color: #eee; padding: 5px; border: 1px solid #000; border-top: none; height: 50px; width: 40%;">PHP'. number_format((float)$data['loadAmt'], 2).'</td>
                        </tr>
                        <tr>
                            <td style="font-size: 12px; background-color: white; padding: 5px; border: 1px solid #000; border-bottom: none; height: 10px; width: 40%;">Amount Spent</td>
                        </tr>
                        <tr>
                            <td style="font-size: 26px; font-weight: bold;  background-color: #eee; padding: 5px; border: 1px solid #000; border-top: none; height: 50px; width: 40%;">PHP'. number_format((float)$data['spendAmt'], 2) . '</td>
                        </tr>
                        <tr>
                            <td style="font-size: 12px; background-color: white; padding: 5px; border: 1px solid #000; border-bottom: none; height: 10px; width: 40%;">Current Balance</td>
                        </tr>
                        <tr>
                            <td style="font-size: 26px; font-weight: bold; background-color: #eee; padding: 5px; border: 1px solid #000; border-top: none; height: 50px; width: 40%;">PHP' . number_format((float)$data['bal'], 2) . '</td>
                        </tr>
                    </table>
                    <hr>
                    <h3 style="width: 100%">Recent Transactions</h3>
                        ';
                    // Transactions section
                        $html .= '
                        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                            <thead>
                                <tr style="background-color: #eee;">
                                    <th style="border: 1px solid #000; padding: 5px;">Transaction ID</th>
                                    <th style="border: 1px solid #000; padding: 5px;">Date</th>
                                    <th style="border: 1px solid #000; padding: 5px;">Branch</th>
                                    <th style="border: 1px solid #000; padding: 5px;">Type</th>
                                    <th style="border: 1px solid #000; padding: 5px;">Amount (PHP)</th>
                                    <th style="border: 1px solid #000; padding: 5px;">Items</th>
                                </tr>
                            </thead>
                            <tbody>';
    
                                                    // Loop through recTra array and generate table rows
                            foreach ($data['recTra'] as $transaction) {
                                // Check if 'transaction-items' exists and is not empty
                                $items = !empty($transaction['transaction-items']) ? json_decode($transaction['transaction-items'], true) : [];

                                // If json_decode fails, handle the error
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    $items = [];  // If decoding fails, treat as empty array
                                }

                                $itemList = '';

                                // Check if $items is a valid array
                                if (is_array($items)) {
                                    foreach ($items as $item) {
                                        $itemList .= $item['name'] . ' (' . $item['quantity'] . 'x, PHP' . number_format((float)$item['price'], 2) . ')<br>';
                                    }
                                } else {
                                    $itemList = 'No items available';
                                }

                                // Generate the table rows
                                $html .= '
                                    <tr style="font-size: 9px">
                                        <td style="border: 1px solid #000; padding: 5px;">T#' . $transaction['transaction-id'] . '</td>
                                        <td style="border: 1px solid #000; padding: 5px;">' . date('Y-m-d H:i', strtotime($transaction['transaction-created-at'])) . '</td>
                                        <td style="border: 1px solid #000; padding: 5px;">' . $transaction['transaction-branch-location'] . '</td>
                                        <td style="border: 1px solid #000; padding: 5px;">' . $transaction['transaction-type'] . '</td>
                                        <td style="border: 1px solid #000; padding: 5px; text-align: right;">PHP' . number_format((float)$transaction['transaction-amount'], 2) . '</td>
                                        <td style="border: 1px solid #000; padding: 5px;">' . $itemList . '</td>
                                    </tr>';
                            }

    
                        $html .= '
                            </tbody>
                        </table>';  
            }
                    // Continue with your existing code to generate the PDF
                    $pdf->writeHTML($html, true, false, true, false, '');
            ('Content-Type: application/pdf');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            $pdf->Output($pdfFilePath, 'I');


        }
    }

?>