<?php

require_once("dbconnect.php");

class Content_DB_Functions
{

    private $db;
    private $user_db;

    // Creates connection to DB
    function __construct($user)
    {
        $conn = new DB_Connect();
        $this->db = $conn->connect();
        $this->user_db = $user;
    }

    function __destruct()
    {
        $this->db->close();
    }

    /**
     * Returns list of announcements (content, date)
     * @param $limit: Maximum number of announcements
     * @return array
     */
    public function getAnnouncements($limit)
    {
        $announcements = array();
        $sql = "SELECT content, datePosted, id FROM announcements order by id desc limit ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $stmt->bind_result($content, $datePosted, $id);
            while ($stmt->fetch()) {
                array_push($announcements,
                    array('content' => $content,
                        'datePosted' => $datePosted,
                        'id' => $id));
            }
        }
        $stmt->close();
        return $announcements;
    }

    /**
     * Gets rewards (content and price) from the DB
     * @return array
     */
    public function getRewards()
    {
        $rewards = array();
        $sql = "SELECT content, price FROM rewards order by id asc";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->execute();
            $stmt->bind_result($content, $price);
            while ($stmt->fetch()) {
                array_push($rewards,
                    array('content' => $content,
                        'price' => $price));
            }
        }
        $stmt->close();
        return $rewards;
    }

    /**
     * Inserts an announcement to the announcements DB
     * @param $content: The announcement
     * @return array|bool
     */
    public function postAnnouncement($content)
    {
        $sql = "INSERT INTO
      announcements(content, datePosted)
      VALUES(?, NOW())";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('s', $content);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $id = $stmt->insert_id;
                $stmt->close();
                return $this->selectAnnouncementById($id);
            }
            $stmt->close();
        }
        return false;
    }

    /**
     * Updates a single announcement with id with content
     * @param $id: To update
     * @param $content
     * @return bool
     */
    public function updateAnnouncement($id, $content)
    {
        $sql = "UPDATE announcements SET content = ?, datePosted = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('si', $content, $id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        return false;
    }

    /**
     * Returns announcement with id
     * @param $id
     * @return array|bool
     */
    public function selectAnnouncementById($id)
    {
        $sql = "SELECT content, datePosted FROM announcements WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->bind_result($content, $datePosted);
            if ($stmt->fetch()) {
                $stmt->close();
                return array('content' => $content,
                    'datePosted' => $datePosted);
            }
            $stmt->close();
        }
        return false;
    }

    /**
     * Checks in user with email for eventId: cookie must match
     * @param $eventId
     * @param $email
     * @param $cookie
     * @return array|bool
     */
    public function checkInUser($eventId, $email, $cookie)
    {
        if (!$this->user_db->loggedIn($email, $cookie)) {
            return array('error_message' => 'Old cookie, please log in again', 'error' => 1);
        }
        $event = $this->getEvent($eventId);

        if ($event) {
            $add = $this->addCheckIn($eventId, $email);
            if ($add && !isset($add['error'])) {
                $this->user_db->updateUserPoints($email, 15);
                return $event;
            } else {
                return $add;
            }
        } else {
            $eventResponse = json_decode(http_parse_message(http_get("https://www.googleapis.com/" .
                "calendar/v3/calendars/umh1upatck4qihkji9k6ntpc9k@group.calendar.google.com/" .
                "events/" . $eventId . "?key=AIzaSyAgLz-5vEBqTeJtCv_eiW0zQjKMlJqcztI"))->body);

            if (property_exists($eventResponse, 'id') && $eventResponse->id == $eventId) {
                if ($this->insertEvent($eventResponse)) {
                    $add = $this->addCheckIn($eventId, $email);

                    if ($add && !isset($add['error'])) {
                        $this->user_db->updateUserPoints($email, 15);
                        return $this->getEvent($eventId);
                    } else {
                        return $add;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Adds the check in to the event:users DB
     * @param $eventId
     * @param $email
     * @return bool
     */
    public function addCheckIn($eventId, $email)
    {
        if ($this->checkedIn($eventId, $email)) {
            return array('error' => 1, 'error_message' => 'Already checked in to event');
        }

        $event = $this->getEvent($eventId);
        $start = new DateTime($event['start']);
        $start->sub(new DateInterval('PT20M'));
        $end = new DateTime($event['end']);
        $end->add(new DateInterval('PT20M'));
        $now = new DateTime();

        if (!($start < $now && $end > $now)) {
            return array('error' => 1, 'error_message' => 'Event not currently taking place');
        }

        $sql = "INSERT INTO events_users(event_id, email) VALUES(?, ?)";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('ss', $eventId, $email);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.'(Add check in for statement:'.$sql.'): '.$this->db->error);
        return false;
    }

    /**
     * Returns true if email already checked in to event
     * @param $eventId
     * @param $email
     * @return bool
     */
    public function checkedIn($eventId, $email)
    {
        $sql = "SELECT * FROM events_users WHERE event_id = ? AND email = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('ss', $eventId, $email);
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log($email.':(Checked In) '.$this->db->error);
        return false;
    }

    /**
     * Gets event from event DB
     * @param $eventId
     * @return array|bool
     */
    public function getEvent($eventId)
    {
        $sql = "SELECT summary, contact, location, start, end, all_day FROM events WHERE event_id = ?";
        $stmt = $this->db->prepare($sql);

        if ($stmt != false) {
            $stmt->bind_param('s', $eventId);
            $stmt->execute();
            $stmt->bind_result($summary, $contact, $location, $start, $end, $all_day);
            if ($stmt->fetch()) {
                $stmt->close();
                return array('summary' => $summary, 'contact' => $contact, 'location' => $location,
                    'start' => $start, 'end' => $end, 'event_id' => $eventId, 'all_day' => $all_day);
            }
            $stmt->close();
        }
        error_log('Get event: '.$this->db->error);
        return false;
    }

    /**
     * Inserts event into event DB
     * @param $event
     * @return bool
     */
    public function insertEvent($event)
    {
        $sql = "INSERT INTO events(summary, event_id, contact, location, start, end, all_day)
      VALUES(?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);

        $start = '';
        $end = '';
        $all_day = false;
        if (property_exists($event, 'start')) {
            $start = $event->start;
            if (property_exists($start, 'date')) {
                $start = $start->date;
                $all_day = true;
            } else if (property_exists($start, 'dateTime')) {
                $start = $start->dateTime;
            }
        }
        if (property_exists($event, 'end')) {
            $end = $event->end;
            if (property_exists($end, 'date')) {
                $end = $end->date;
            } else if (property_exists($end, 'dateTime')) {
                $end = $end->dateTime;
            }
        }

        $summary = property_exists($event, 'summary') ? $event->summary : '';
        $creator = property_exists($event, 'creator') ? property_exists($event->creator, 'email') ?
            $event->creator->email : '' : '';
        $location = property_exists($event, 'location') ? $event->location : '';

        if ($stmt != false) {
            $stmt->bind_param('ssssssi', $summary, $event->id, $creator, $location, $start, $end, $all_day);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
        error_log('Insert event: '.$this->db->error);
        return false;
    }

    /**
     * Get all events email has attended
     * @param $email
     * @return array|bool
     */
    public function getAttendedEvents($email)
    {
        $sql = "SELECT event_id FROM events_users WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt != false) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($event_id);
            $event_ids = array();
            while ($stmt->fetch()) {
                array_push($event_ids, $event_id);
            }
            $stmt->close();
            $events = array();
            for ($i = 0; $i < count($event_ids); $i++) {
                $event_id = $event_ids[$i];
                $sql2 = "SELECT summary, location, start, end, all_day FROM events WHERE event_id = ?";
                $stmt2 = $this->db->prepare($sql2);
                if ($stmt2 != false) {
                    $stmt2->bind_param('s', $event_id);
                    $stmt2->execute();
                    $stmt2->bind_result($summary, $location, $start, $end, $all_day);
                    while ($stmt2->fetch()) {
                        array_push($events, array('summary' => $summary, 'location' => $location,
                            'start' => $start, 'end' => $end, 'event_id' => $event_id, 'all_day' => $all_day));
                    }
                    $stmt2->close();
                } else {
                    $stmt2->close();
                    error_log($email.': '.$this->db->error);
                    return false;
                }
            }
            return $events;
        }
        error_log($email.': '.$this->db->error);
        return array();
    }
}

?>