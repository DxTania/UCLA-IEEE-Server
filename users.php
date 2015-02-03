<?php
$debug = false;
date_default_timezone_set('America/Los_Angeles');

if ($debug) {
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
} else {
    ini_set("log_errors", 1);
    ini_set("error_log", "error_log");
}

require("include/user_db_functions.php");
require("include/content_db_functions.php");

$db = new User_DB_Functions();
$contentDB = new Content_DB_Functions($db);

if (isset($_POST['service']) && $_POST['service'] != '') {
    error_log('Got request with service ' . $_POST['service'] . ' from user ' . $_POST['email']);

    $service = $_POST['service'];
    $email = $_POST['email'];

    if ($service == 'login') {
        $password = $_POST['password'];
        login($email, $password);

    } else if ($service == 'register') {
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $password = $_POST['password'];
        $year = $_POST['year'];
        $major = $_POST['major'];
        createUser($firstname, $lastname, $password, $email, $year, $major);

    } else if ($service == 'forgot_password') {
        forgotPassword($email);

    } else if ($service == 'edit_member') {
        editMember($email);

    } else if ($service == 'check_in') {
        checkIn($_POST['eventId'], $email, $_POST['cookie']);

    } else if ($service == 'get_user') {
        getUser($email);

    } else if ($service == 'get_attended_events') {
        getAttendedEvents($email);

    } else if ($service == 'get_leader_board') {
        echo json_encode($db->getLeaderBoard());

    }
} else {
    header("Location: http://ieeebruins.org/ieeebruins.org/public/400.shtml");
}

function checkIn($eventId, $email, $cookie)
{
    global $db, $contentDB;
    $response = array('success' => 0, 'error' => 0, 'user' => array());

    $event = $contentDB->checkInUser($eventId, $email, $cookie);
    $user = $db->selectUserByEmail($email);
    if ($event && !isset($event['error'])) {
        $response['success'] = 1;
        $response['event'] = $event;
        $response['user'] = $user; // TODO: if it fails?
        echo json_encode($response);
    } else if (isset($event['error_message'])) {
        // contains error
        echo json_encode($event);
    } else {
        $response['error'] = 1;
        $response['error_message'] = "Couldn't check in user"; // TODO: Better error message
        echo json_encode($response);
    }
}

function getUser($email)
{
    global $db;
    $response = array('success' => 0, 'error' => 0, 'user' => array());

    $user = $db->selectUserByEmail($email);
    if ($user) {
        $response['user'] = $user;
        $response['success'] = 1;
    } else {
        $response['error'] = 1;
        $response['error_message'] = "Couldn't get user";
    }

    echo json_encode($response);
}

function forgotPassword($email)
{
    global $db;
    $response = array('success' => 0, 'error' => 0, 'user' => array());

    // Gen random 10 char password
    $randpassword = substr(md5(rand()), 0, 10);

    // Update password to random
    if ($db->updatePassword($email, $randpassword)) {
        $subject = 'Forgot password';
        $message = "Hi, you recently forgot your password for your UCLA IEEE membership account." .
            " Here's a temporary password you can use to log in. Please promptly change your password after logging in." .
            "\n\n" . $randpassword . "\n\nCheck your spam if you don't see it soon!";
        $headers = 'From: webmaster@ieee.ucla.edu' . "\r\n" .
            'Reply-To: webmaster@ieee.ucla.edu' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        mail($email, $subject, $message, $headers);
        $response['success'] = 1;
        echo json_encode($response);
    } else {
        $response['error'] = 1;
        $response['error_message'] = "Couldn't generate temporary password";
        echo json_encode($response);
    }
}

function login($email, $password)
{
    global $db;
    $response = array('success' => 0, 'error' => 0, 'user' => array());

    // Check if user exists
    if ($db->userExists($email)) {
        $auth = $db->authorizeLogin($email, $password);
        if ($auth) {
            $cookie = md5(uniqid($email, true));
            // Successfully authenticated
            $response['success'] = 1;
            $response['user'] = $db->selectUserByEmail($email);
            $response['cookie'] = $cookie;
            $db->setCookie($email, $cookie);
            echo json_encode($response);
            return;
        }
    } else {
        $response['error'] = 1;
        $response['error_code'] = 1;
        $response['error_message'] = 'Incorrect email';
        echo json_encode($response);
        return;
    }

    $response['error'] = 1;
    $response['error_code'] = 0;
    $response['error_message'] = 'Incorrect password';
    echo json_encode($response);
}

function changePassword($email, $oldpassword, $newpassword)
{
    global $db;
    $response = array('success' => 0, 'error' => 0, 'user' => array());

    // Authenticate then update password
    $auth = $db->authorizeLogin($email, $oldpassword);
    if ($auth) {
        if ($db->updatePassword($email, $newpassword)) {
            $response['success'] = 1;
            echo json_encode($response);
        } else {
            $response['error'] = 1;
            $response['error_message'] = "Couldn't update password";
            echo json_encode($response);
        }
    }
}

function createUser($firstname, $lastname, $password, $email, $year, $major)
{
    global $db;
    $response = array('success' => 0, 'error' => 0, 'user' => array());

    // Does user already exist?
    if ($db->userExists($email)) {
        $response['error'] = 1;
        $response['error_message'] = 'User already exists';
        echo json_encode($response);

    } else if (!$db->isValidEmail($email)) {
        $response['error'] = 1;
        $response['error_message'] = 'Invalid email';
        echo json_encode($response);

    } else {
        $user = $db->createUser($firstname, $lastname, $email, $password, $year, $major);
        if ($user) {
            $cookie = md5(uniqid($email, true));
            $response['success'] = 1;
            $response['user'] = $user;
            $response['cookie'] = $cookie;
            $db->setCookie($email, $cookie);
            echo json_encode($response);
        } else {
            $response['error'] = 1;
            $response['error_message'] = "Couldn't create user";
            echo json_encode($response);
        }
    }
}

function editMember($email)
{
    global $db;
    $response = array('success' => 0, 'error' => 0, 'user' => array());
    // TODO: Handle errors for individual updates
    if ($db->loggedIn($email, $_POST['cookie'])) {

        if (isset($_POST['newEmail'])) {
            if ($db->updateByEmail($email, 'email', $_POST['newEmail'])) {
                $email = $_POST['newEmail'];
            } else {
                echo json_encode(array('error' => 1, 'error_message' => 'Could not update email'));
                return;
            }
        }

        if (isset($_POST['newName'])) {
            if (!$db->updateByEmail($email, 'name', $_POST['newName'])) {
                echo json_encode(array('error' => 1, 'error_message' => 'Could not update name'));
                return;
            }
        }

        if (isset($_POST['newId'])) {
            if (!$db->updateByEmail($email, 'ieee_id', $_POST['newId'])) {
                echo json_encode(array('error' => 1, 'error_message' => 'Could not update IEEE id'));
                return;
            }
        }

        if (isset($_POST['newPassword'])) {
            $newpassword = $_POST['newPassword'];
            $password = $_POST['password'];
            if ($db->authorizeLogin($email, $password)) {
                $hash = password_hash($newpassword, PASSWORD_DEFAULT);
                if (!$db->updateByEmail($email, 'encrypted_password', $hash)) {
                    echo json_encode(array('error' => 1, 'error_message' => 'Could not update password'));
                    return;
                }
            } else {
                echo json_encode(array('error' => 1, 'error_code' => 0, 'error_message' => 'Incorrect password'));
                return;
            }
        }

        if (isset($_POST['year'])) {
            if (!$db->updateByEmail($email, 'year', $_POST['year'])) {
                echo json_encode(array('error' => 1, 'error_message' => 'Could not update year'));
                return;
            }
        }

        if (isset($_POST['major'])) {
            if (!$db->updateByEmail($email, 'major', $_POST['major'])) {
                echo json_encode(array('error' => 1, 'error_message' => 'Could not update major'));
                return;
            }
        }

        $response['user'] = $db->selectUserByEmail($email);
        $response['success'] = 1;
        echo json_encode($response);
    } else {
        echo json_encode(array('error' => 1, 'error_message' => 'User not logged in'));
    }
}

function getAttendedEvents($email)
{
    global $contentDB;
    $response = array('success' => 0, 'error' => 0, 'user' => array());

    $events = $contentDB->getAttendedEvents($email);
    if ($events || is_array($events)) {
        $response['success'] = 1;
        $response['events'] = $events;
    } else {
        $response['error'] = 1;
        $response['error_message'] = "Couldn't retrieve attended events";
    }

    echo json_encode($response);
}

function give_points($emails, $all)
{
    global $contentDB, $db;
    if ($all) {
        $emails = $db->getEmailsCreatedOn('2014-10-08');
    }
    for ($i = 0; $i < count($emails); $i++) {
        echo $emails[$i];
        echo $i;
        $add = $contentDB->addCheckIn('6gtpheoe54qots3lppo4f2r24g', $emails[$i]);
        echo json_encode($add) . '\n';
    }
}

?>