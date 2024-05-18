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
?>
