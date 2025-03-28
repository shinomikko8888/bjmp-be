<?php
    if (!function_exists('isValidJson')) {
        function isValidJson($string) {
            if ($string === null) {
                return false;
            }
            json_decode($string);
            return (json_last_error() == JSON_ERROR_NONE);
        }
    }

    if(!function_exists('generateMatrixChart')){
        function generateMatrixChart($month, $year, $transactions, $ctx, &$data, $ate) {
            $matrix = array();
            if($ate){
                $daysInYear = date('z', mktime(0, 0, 0, 12, 31, $year)) + 1; // Get total days in the year
                // Loop through each day of the year
                for ($day = 0; $day < $daysInYear; $day++) {
                    $date = date('Y-m-d', strtotime("$year-01-01 +$day days"));
                    $dayOfWeek = date('N', strtotime($date)); // 1 (Monday) through 7 (Sunday)
                    $matrix[] = array(
                        'x' => $date,
                        'y' => $dayOfWeek,
                        'd' => $date,
                        'v' => isset($transactions[$date]) ? $transactions[$date] : 0
                    );
                }
                
            } else {
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                
            
                // Loop through each day of the month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = "$year-$month-" . sprintf('%02d', $day);
                    $dayOfWeek = date('N', strtotime($date)); // 1 (Monday) through 7 (Sunday)
            
                    $matrix[] = array(
                        'x' => $dayOfWeek,
                        'y' => $date,
                        'd' => $date,
                        'v' => isset($transactions[$date]) ? $transactions[$date] : 0
                    );
                }
            }
           

            $data[] = $matrix;
            return $matrix;
        }
    }

    if(!function_exists('generateLineChart')){
        function generateLineChart($year, $branch, $transactions, $ctx, &$data, $allTimeEnabled) {
            
            $line = [];
            
            switch ($ctx) {
                case 'profit':
                case 'profitModal':
                    for ($month = 1; $month <= 12; $month++) {
                        // Set the current month
                        $currentMonth = DateTime::createFromFormat('Y-m-d', "$year-$month-01");
                        
                        // First half of the month (1st to 14th)
                        $periodStart = clone $currentMonth;
                        $periodEnd = clone $currentMonth;
                        $periodEnd->modify('+13 days');
                
                        // Ensure the end of the period does not exceed the end of the month
                        if ($periodEnd > $currentMonth->modify('last day of this month')) {
                            $periodEnd = clone $currentMonth;
                        }
                
                        // Initialize totals for the bi-weekly period
                        $revenue = 0;
                        $costs = 0;
                        $profit = 0;
                
                        // Sum transactions for the first half of the month
                        foreach ($transactions as $date => $transaction) {
                            $transactionDate = new DateTime($date);
                            if ($transactionDate >= $periodStart && $transactionDate <= $periodEnd) {
                                $revenue += $transaction['revenue'];
                                $costs += $transaction['costs'];
                            }
                        }
                        $profit = $revenue - $costs;
                
                        // Add the bi-weekly data to the current array
                        $line[] = [
                            'start' => $periodStart->format('Y-m-d'),
                            'end' => $periodEnd->format('Y-m-d'),
                            'revenue' => $revenue,
                            'costs' => $costs,
                            'profit' => $profit
                        ];
                
                        // Second half of the month (15th to the last day of the month)
                        $periodStart = clone $periodEnd;
                        $periodStart->modify('+1 day'); // Start from the day after the last one
                
                        $periodEnd = clone $currentMonth;
                        $periodEnd->modify('last day of this month');
                
                        // Initialize totals for the bi-weekly period
                        $revenue = 0;
                        $costs = 0;
                        $profit = 0;
                
                        // Sum transactions for the second half of the month
                        foreach ($transactions as $date => $transaction) {
                            $transactionDate = new DateTime($date);
                            if ($transactionDate >= $periodStart && $transactionDate <= $periodEnd) {
                                $revenue += $transaction['revenue'];
                                $costs += $transaction['costs'];
                            }
                        }
                        $profit = $revenue - $costs;
                
                        // Add the bi-weekly data to the current array
                        $line[] = [
                            'start' => $periodStart->format('Y-m-d'),
                            'end' => $periodEnd->format('Y-m-d'),
                            'revenue' => $revenue,
                            'costs' => $costs,
                            'profit' => $profit
                        ];
                    }
                    break;
                default:
                    # code...
                    break;
            }
           
            
            $data[] = $line;
            return $line;
        }
        
        
    }
?>
