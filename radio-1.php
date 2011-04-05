<?php

class Twitter {

	protected static $searchUrl;
	protected static $rpp = 100;

	protected function getUrl($params = array()) {

		$default = array(
			'rpp' => self::$rpp,
			'page' => 1,
			'result_type' => 'recent',
			'show_user' => false,

		);

		$params = array_merge($default, $params);

		return self::$searchUrl.'?'.http_build_query($params, null, '&');

	}

	public function search($params = array()) {

		$url = 'http://search.twitter.com/search.json'.$this->getUrl($params);
		//echo '<pre>'.$url.'</pre>';

		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Radio1 play tracker v0.1',
			CURLOPT_TIMEOUT => 5,
			CURLOPT_HEADER => false,
			CURLOPT_URL => $url
		);

		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		curl_close($ch);

		// return the decoded result
		$result = json_decode($result);

		if (count($result->results) > 0) {
			foreach ($result->results as $result) {
				$tweets[] = new Tweet($result->id_str, $result->text, $result->created_at);
			}
			//echo '<pre>'.print_r($tweets, true).'</pre>';
		}

	}

}

/*** TWEET CLASS ***/

class Tweet {

	private $id;
	private $title;
	private $artist;
	private $played;

	public function __construct($id, $text, $played) {
		$this->id = $id;
		$this->played = strtotime($played);
		$this->processText($text);
		$this->store();
	}

	/**
	 * Removes has tags and other crap
	 * @param string $text
	 */
	protected function processText($text) {

		// remove the musical note
		$text = substr($text, 3);

		// remove hash tags
		$text = preg_replace('/#(.*)\s?/i', '', $text);

		$parts = explode(" - by ", $text);

		$this->title = trim($parts[0]);
		$this->artist = ($parts[1]);

	}

	protected function store() {
		$db = Database::getInstance();
		$query = " REPLACE INTO song
					SET id = ".$this->id.",
						title = ".$db->safe($this->title).",
						artist = ".$db->safe($this->artist).",
						played = ".$db->safe(date('c', $this->played))."
				 ";
		$db->query($query);
		//echo '<pre>'.print_r($db, true).'</pre>';
		}

}

/** DB CLASS **/

class Database {

	protected static $host;
	protected static $user;
	protected static $pass;
	protected static $name;

	protected static $obj = null;
	private $db;

	public static function setHost($host) {
		self::$host = $host;
	}

	public static function setUser($user) {
		self::$user = $user;
	}

	public static function setPassword($password) {
		self::$pass = $password;
	}

	public static function setName($name) {
		self::$name = $name;
	}

	private function __construct() {
		$db = new mysqli(self::$host, self::$user, self::$pass, self::$name);
		if ($db->connect_error ) die($db->connect_error);
		$this->db = $db;
	}

	public static function getInstance() {
		if (!isset(self::$obj)) {
			self::$obj = new Database();
		}
		return self::$obj;
	}

	public function query($query, $return = false) {
		$result = $this->db->query($query);

		if (!$result) die('MYSQL ERROR: '.$this->db->error);

		if ($return) {
			$data = array();
			if ($result) {
				while ($data[] = $result->fetch_object()) ;
			}
			return $data;
		} else {
			return ($result) ? true : false;
		}
	}

	public function safe($value) {
		if (is_numeric($value)) return $value;
		return "'".$this->db->real_escape_string($value)."'";
	}

}

// set up the db class
Database::setHost('localhost');
Database::setUser('test_user');
Database::setPassword('fred');
Database::setName('radio1_playlist');

// get the tweets and store them
$Twitter = new Twitter();

$db = Database::getInstance();
$query = " SELECT id FROM song WHERE 1 ORDER BY id DESC LIMIT 1";

$result = $db->query($query, true);
$search = array(
	'from' => 'NowOnRadio1',
	'since_id' => $result[0]->id
);
$Twitter->search($search);
