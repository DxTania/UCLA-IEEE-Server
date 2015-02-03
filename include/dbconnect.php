<?php

class DB_Connect {
    // Connecting to database
    public function connect() {
        // connecting to mysql
        require_once("config.php");
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($conn->connect_errno) {
            // bad stuff
          echo "failed to connect to database";
        }

        // return database handler
        return $conn;
    }
}

?>