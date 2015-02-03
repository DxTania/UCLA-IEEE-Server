<?php

require("dbconnect.php");
require("password.php");

class User_DB_Functions {
  private $db;

  // Creates connection to DB
  function __construct() {
    $conn = new DB_Connect();
    $this->db = $conn->connect();
  }

  function __destruct() {
    $this->db->close();
  }

  public function setCookie($email, $cookie) {
    $sql = "UPDATE users SET cookie = ? WHERE email = ?";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->bind_param('ss', $cookie, $email);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $stmt->close();
        return true;
      }
    }

    $stmt->close();
    return false;
  }

  public function loggedIn($email, $cookie) {
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
    }

    $stmt->close();
    return false;
  }

  // Returns true if password successfully updated, false otherwise
  public function updatePassword($email, $password) {
    $sql = "UPDATE users SET encrypted_password = ? WHERE email = ?";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt->bind_param('ss', $hash, $email);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $stmt->close();
        return true;
      } else {
        $stmt->close();
      }
    }
    return false;
  }

  public function updateEmail($email, $newEmail) {
    $sql = "UPDATE users SET email = ? WHERE email = ?";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->bind_param('ss', $newEmail, $email);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $stmt->close();
        return true;
      }
    }
    $stmt->close();
    return false;
  }

  public function updateByEmail($email, $column, $newValue) {
    $sql = "UPDATE users SET $column = ? WHERE email = ?";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->bind_param('ss', $newValue, $email);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $stmt->close();
        return true;
      }
    }
    $stmt->close();
    return false;
  }

  // Returns array containing user details, false otherwise
  public function selectUserByUid($uid) {
    $sql = "SELECT name, email, created_at, ieee_id FROM users WHERE uid = ?";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->bind_param('s', $uid);
      $stmt->execute();
      $stmt->bind_result($name, $email, $created_at, $ieee_id);
      if ($stmt->fetch()) {
        $stmt->close();
        return array('name' => $name,
          'email' => $email, 'created_at' => $created_at, 'ieee_id' => $ieee_id);
      } else {
        $stmt->close();
      }
    }
    return false;
  }

  // Returns array containing user details, false otherwise
  public function selectUserByEmail($email) {
    $sql = "SELECT name, created_at, ieee_id FROM users WHERE email = ?";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $stmt->bind_result($name, $created_at, $ieee_id);
      if ($stmt->fetch()) {
        $stmt->close();
        return array('name' => $name,
          'email' => $email, 'created_at' => $created_at, 'ieee_id' => $ieee_id);
      } else {
        $stmt->close();
      }
    }
    return false;
  }

  // Creates user in db, returns the user if successful, false otherwise
  public function createUser($firstname, $lastname, $email, $password) {
    $sql = "INSERT INTO
      users(name, email, encrypted_password, created_at)
      VALUES(?, ?, ?, NOW())";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $name = $firstname . " " . $lastname;
      $stmt->bind_param('sss', $name, $email, $hash);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $uid = $stmt->insert_id;
        $stmt->close();
        return $this->selectUserByUid($uid);
      } else {
        $stmt->close();
      }
    }
    return false;
  }

  // Returns user if email and password match, false otherwise
  public function authorizeLogin($email, $password) {
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
      } else {
        $stmt->close();
      }
    }
    return false;
  }

  // Returns true if there is already an entry with this email
  public function userExists($email) {
    $sql = "SELECT email from users WHERE email = ?";
    $stmt = $this->db->prepare($sql);

    if($stmt != false) {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $stmt->bind_result($res);
      if ($stmt->fetch()) {
        $stmt->close();
        return true;
      }
    }
    $stmt->close();
    return false;
  }

  // TODO: DO THIS!
  public function isValidEmail($email) {
    return true;
  }

  // TODO: Move this to another file / new class
  public function getAnnouncements() {
    $announcements = array();
    $sql = "SELECT content, datePosted FROM announcements";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->execute();
      $stmt->bind_result($content, $datePosted);
      while ($stmt->fetch()) {
        array_push($announcements, array('content' => $content, 'datePosted' => $datePosted));
      }
    }
    $stmt->close();
    return $announcements;
  }

  public function postAnnouncement($content) {
    $sql = "INSERT INTO
      announcements(content, datePosted)
      VALUES(?, NOW())";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->bind_param('s', $content);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $uid = $stmt->insert_id;
        $stmt->close();
        return $this->selectAnnouncementByUid($uid);
      } else {
        $stmt->close();
      }
    }
  }

  public function selectAnnouncementByUid($uid) {
    $sql = "SELECT content, datePosted FROM announcements WHERE uid = ?";
    $stmt = $this->db->prepare($sql);

    if ($stmt != false) {
      $stmt->bind_param('s', $uid);
      $stmt->execute();
      $stmt->bind_result($content, $datePosted);
      if ($stmt->fetch()) {
        $stmt->close();
        return array('content' => $content,
          'datePosted' => $datePosted);
      } else {
        $stmt->close();
      }
    }
    return false;
  }
}

?>