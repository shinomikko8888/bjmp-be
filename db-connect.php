<?php

class dBConnect {
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $dbname = "bjmppos";

    public function connect() {
        $conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        return $conn;
    }
}

?>
