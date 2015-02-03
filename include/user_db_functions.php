<?php

require_once("dbconnect.php");
require("password.php");

class User_DB_Functions
{
    private $db;

    // Creates connection to DB
    function __construct()
    {
        $conn = new DB_Connect();
        $this->db = $conn->connect();
    }

    function __destruct()
    {
        $this->db->close();
    }

    public function setCookie($email, $cookie)
    {
        $sql = "UPDATE users SET cookie = ? WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('ss', $cookie, $email);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    public function loggedIn($email, $cookie)
    {
        $sql = "SELECT email FROM users WHERE cookie = ? and email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('ss', $cookie, $email);
            $stmt->execute();
            $stmt->bind_result($email_back);
            if ($stmt->fetch()) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    // Returns true if password successfully updated, false otherwise
    public function updatePassword($email, $password)
    {
        $sql = "UPDATE users SET encrypted_password = ? WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('ss', $hash, $email);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    public function updateEmail($email, $newEmail)
    {
        $sql = "UPDATE users SET email = ? WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('ss', $newEmail, $email);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                return true;
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    public function updateByEmail($email, $column, $newValue)
    {
        $sql = "UPDATE users SET $column = ? WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('ss', $newValue, $email);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    // Returns array containing user details, false otherwise
    public function selectUserByUid($uid)
    {
        $sql = "SELECT name, email, created_at, ieee_id, points, year, major, total_points FROM users WHERE uid = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('s', $uid);
            $stmt->execute();
            $stmt->bind_result($name, $email, $created_at, $ieee_id, $points, $year, $major, $total_points);
            if ($stmt->fetch()) {
                $stmt->close();
                return array('name' => $name, 'points' => $points,
                    'email' => $email, 'created_at' => $created_at, 'ieee_id' => $ieee_id, 'year' => $year, 'major' => $major,
                    'total_points' => $total_points);
            }
            $stmt->close();
        }
        error_log($uid.': '.$this->db->error);
        return false;
    }

    // Returns array containing user details, false otherwise
    public function selectUserByEmail($email)
    {
        $sql = "SELECT name, created_at, ieee_id, points, year, major, total_points FROM users WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($name, $created_at, $ieee_id, $points, $year, $major, $total_points);
            if ($stmt->fetch()) {
                $stmt->close();
                return array('name' => $name, 'points' => $points,
                    'email' => $email, 'created_at' => $created_at, 'ieee_id' => $ieee_id, 'year' => $year, 'major' => $major,
                    'total_points' => $total_points);
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    // Creates user in db, returns the user if successful, false otherwise
    public function createUser($firstname, $lastname, $email, $password, $year, $major)
    {
        $sql = "INSERT INTO
      users(name, email, encrypted_password, created_at, year, major)
      VALUES(?, ?, ?, NOW(), ?, ?)";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $name = $firstname . " " . $lastname;
            $stmt->bind_param('sssss', $name, $email, $hash, $year, $major);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $uid = $stmt->insert_id;
                $stmt->close();
                $this->updateUserPoints($email, 10);
                return $this->selectUserByUid($uid);
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    // Returns user if email and password match, false otherwise
    public function authorizeLogin($email, $password)
    {
        $sql = "SELECT encrypted_password, uid FROM users WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($encrypted_password, $uid);
            if ($stmt->fetch()) {
                $stmt->close();
                if (password_verify($password, $encrypted_password)) {
                    return $this->selectUserByUid($uid);
                }
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    // Returns true if there is already an entry with this email
    public function userExists($email)
    {
        $sql = "SELECT email from users WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($res);
            if ($stmt->fetch()) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    // Adds amount to points
    public function updateUserPoints($email, $amount)
    {
        $sql = "UPDATE users SET points = (points + ?), total_points = (total_points + ?)  WHERE email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('iis', $amount, $amount, $email);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.': '.$this->db->error);
        return false;
    }

    // TODO: DO THIS!
    public function isValidEmail($email)
    {
        return true;
    }

    public function getEmailsCreatedOn($day)
    {
        $sql = 'SELECT email FROM users WHERE created_at = ?';
        $stmt = $this->db->prepare($sql);
        $emails = array();

        if ($stmt != false) {
            $stmt->bind_param('s', $day);
            $stmt->execute();
            $stmt->bind_result($email);
            while ($stmt->fetch()) {
                array_push($emails, $email);
            }
            $stmt->close();
        }
        error_log('Get emails created on: '.$this->db->error);
        return $emails;
    }

    public function getLeaderBoard()
    {
        $sql = 'SELECT name, points FROM users ORDER BY points DESC LIMIT 10';
        $stmt = $this->db->prepare($sql);
        $names = array();

        if ($stmt != false) {
            $stmt->execute();
            $stmt->bind_result($name, $points);
            while($stmt->fetch()) {
                $names[$name] = $points;
            }
            $stmt->close();
        }

        arsort($names);
        return $names;
    }
}

?>