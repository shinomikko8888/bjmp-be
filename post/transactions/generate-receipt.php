<?php
    use Picqer\Barcode\BarcodeGenerator;
    use Picqer\Barcode\BarcodeGeneratorPNG;
    if(!function_exists('generateLoadReceipt')){
        function generateLoadReceipt($data, $datetime, $id, $pdfFilePath){
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
                    $image_file = __DIR__ . '\..\..\files\images\static\bjmp-logo.png';
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
                    $this->Cell(0, 3, "This receipt is valid only for the transaction mentioned above. Any alterations or misuse of this receipt is punishable by law.", 0, 0, 'C');
                }

                public function AddWatermark()
                {
                    $image_file = __DIR__ . '\..\..\files\images\static\bjmp-logo.png';
                    $this->SetAlpha(0.1); // Set transparency
                    $this->Image($image_file, ($this->getPageWidth() - 100) / 2, ($this->getPageHeight() - 100) / 2, 100, 100, '', '', '', false, 300, '', false, false, 0);
                    $this->SetAlpha(1); // Reset transparency to default
                }
            }
            $pdf = new CustomPDF('P', 'mm', array(150, 150), true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetTitle('Receipt');
            $pdf->AddPage();
            $pdf->AddWatermark();

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
                    <td style="text-align: left; font-size: 6px;">Date of Transaction: '. $datetime .'</td>
                    <td style="text-align: right; font-size: 6px;">Transaction No.: Transaction#'. sprintf("%011d", $id) .'</td>
                </tr>
            </table>
            <h3 style="width: 100%">Transaction Details</h3>
            <table>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Conducted by:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">'. $data['active-email'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Type of Loading Transaction:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">'. $data['loading-type'] .'</td>
                </tr>
                <br>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Sent By:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">'. $data['pdl-creditor'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Payment Sent To:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">'. strtoupper($data['pdl-data']['pdl-last-name']) . ', '. $data['pdl-data']['pdl-first-name'] . ' ' . $data['pdl-data']['pdl-middle-name'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">PDL ID:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">PDL#'. $data['pdl-data']['pdl-id'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Branch Location:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">'. $data['pdl-data']['pdl-branch-location'] .'</td>
                </tr>
                <br>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Amount:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">PHP'. number_format((float)$data['load-amount'], 2, '.', '') .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Old Balance:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">PHP' . number_format((float)$data['pdl-data']['pdl-balance'], 2, '.', '') .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;">Updated Balance:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold">PHP' . number_format((float)$data['pdl-data']['pdl-balance'] + (float)$data['load-amount'], 2, '.', '') . '</td>
                </tr>
                
            </table> 
            <br>
            <hr>
            <p style="font-size: 8px; text-align: center; margin: 0; padding: 0;">Transaction#'. sprintf("%011d", $id) .'</p>
            ';

            $pdf->writeHTML($html, true, false, true, false, '');
            $generator = new BarcodeGeneratorPNG();
            $generator->useGd();
            $barcodeForDocu = "
            
            <img style='margin: 0;' src=\"data:image/png;base64," . base64_encode($generator->getBarcode(sprintf("%011d", $id), $generator::TYPE_CODE_128)) . "\">\n
            ";
            $pdf->writeHTML($barcodeForDocu, true, false, true, false, 'C');
            $pdf->Output($pdfFilePath, 'F');
        }
    }
    if(!function_exists('generatePurchaseReceipt')){
        function generatePurchaseReceipt($data, $datetime, $id, $pdfFilePath){
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
                    $image_file = __DIR__ . '\..\..\files\images\static\bjmp-logo.png';
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
                    $this->Cell(0, 3, "This receipt is valid only for the transaction mentioned above. Any alterations or misuse of this receipt is punishable by law.", 0, 0, 'C');
                }

                public function AddWatermark()
                {
                    $image_file = __DIR__ . '\..\..\files\images\static\bjmp-logo.png';
                    $this->SetAlpha(0.1); // Set transparency
                    $this->Image($image_file, ($this->getPageWidth() - 100) / 2, ($this->getPageHeight() - 100) / 2, 100, 100, '', '', '', false, 300, '', false, false, 0);
                    $this->SetAlpha(1); // Reset transparency to default
                }
            }
            $baseHeight = 200; 
            $entryHeight = 10; 
            $numberOfEntries = count($data['commodity-data']);
            $additionalHeight = $numberOfEntries * $entryHeight;
            $totalHeight = $baseHeight + $additionalHeight;

            if ($totalHeight < 200) {
                $totalHeight = 200;
            }

            $totalQuantity = 0;
            foreach ($data['commodity-data'] as $item) {
                $totalQuantity += (int)$item['commodity-quantity'];
            }
            $pdf = new CustomPDF('P', 'mm', array(150, $totalHeight), true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetTitle('Receipt');
            $pdf->AddPage();
            $pdf->AddWatermark();
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
                    border-bottom: 1px solid #ccc; /* Add bottom border for separation */
                }
                .main-table {
                    width: 100%;
                    border-collapse: collapse;
                    border-spacing: 0 5px;
                }
            </style>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="text-align: left; font-size: 6px;">Date of Transaction: ' . $datetime . '</td>
                    <td style="text-align: right; font-size: 6px;">Transaction No.: Transaction#' . sprintf("%011d", $id) . '</td>
                </tr>
            </table>
            <h3 style="width: 100%">Transaction Details</h3>
            <table class="main-table">
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px; font-weight: bold">ID</th>
                    <th style="text-align: left; font-size: 10px; font-weight: bold">Product</th>
                    <th style="text-align: left; font-size: 10px; font-weight: bold">Price</th>
                    <th style="text-align: left; font-size: 10px; font-weight: bold">Qty</th>
                </tr>
                ';

            foreach ($data['commodity-data'] as $item) {
                $html .= '
                <tr class="table-row">
                    <td style="text-align: left; font-size: 10px;">Item#' . htmlspecialchars($item['commodity-item-id']) . '</td>
                    <td style="text-align: left; font-size: 10px;">' . htmlspecialchars($item['commodity-type']) . '-' . htmlspecialchars($item['commodity-name']) . '</td>
                    <td style="text-align: left; font-size: 10px;">PHP' . number_format((float)$item['commodity-price'], 2, '.', '') . '</td>
                    <td style="text-align: left; font-size: 10px;">' . htmlspecialchars($item['commodity-quantity']) . '</td>
                </tr>
                ';
            }

            $html .= '
            <hr>
                <tr>
                <td></td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">Conducted by:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">'. $data['active-email'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">Type of Purchase:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">'. $data['purchase-type'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">Purchased by:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">'. strtoupper($data['pdl-data']['pdl-last-name']) . ', '. $data['pdl-data']['pdl-first-name'] . ' ' . $data['pdl-data']['pdl-middle-name'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">PDL ID:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">PDL#'. $data['pdl-data']['pdl-id'] .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">Branch Location:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">'. $data['pdl-data']['pdl-branch-location'] .'</td>
                </tr>
                <tr>
                <td></td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">Total Price:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">PHP'. number_format((float)$data['total-price'], 2, '.', '') .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">Old Balance:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">PHP' . number_format((float)$data['pdl-data']['pdl-balance'], 2, '.', '') .'</td>
                </tr>
                <tr class="table-row">
                    <th style="text-align: left; font-size: 10px;" colspan="2">Updated Balance:</th>
                    <td style="text-align: left; font-size: 10px; font-weight: bold" colspan="2">PHP' . number_format((float)$data['pdl-data']['pdl-balance'] - (float)$data['total-price'], 2, '.', '') . '</td>
                </tr>
                
            </table>
            <hr>
            <p style="font-size: 8px; text-align: center; margin: 0; padding: 0;">Transaction#'. sprintf("%011d", $id) .'</p>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $generator = new BarcodeGeneratorPNG();
            $generator->useGd();
            $barcodeForDocu = "
            
            <img style='margin: 0;' src=\"data:image/png;base64," . base64_encode($generator->getBarcode(sprintf("%011d", $id), $generator::TYPE_CODE_128)) . "\">\n
            ";
            $pdf->writeHTML($barcodeForDocu, true, false, true, false, 'C');

            $noOfItems = '
            <h3>Number of Items Sold: '. $totalQuantity . ' </h3>
            ';
            $pdf->writeHTML($noOfItems, true, false, true, false, 'C');
            $pdf->Output($pdfFilePath, 'F');
        }
    }
    
?>