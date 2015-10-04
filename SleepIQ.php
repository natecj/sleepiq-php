<?php

namespace SleepIQ;

class API
{
  private $connection = null;
  private $beds       = null;
  private $sleepers   = null;

  function __construct( $username, $password ) {
    $this->connection = new Connection( $username, $password );
    $this->refresh();
  }

  public function beds( $reload_cache = false ) {
    if ( is_null( $this->beds ) || $reload_cache )
      $this->refresh();
    return $this->beds;
  }

  public function sleepers( $reload_cache = false ) {
    if ( is_null( $this->sleepers ) || $reload_cache )
      $this->refresh();
    return $this->sleepers;
  }

  public function refresh() {

    // Get all beds
    $this->beds = [];
    foreach( $this->connection->requestJSON("/rest/bed/")->beds as $bed_data )
      $this->beds[$bed_data->bedId] = new Bed($this->connection, $bed_data);

    // Get all sleepers
    $this->sleepers = [];
    foreach( $this->connection->requestJSON("/rest/sleeper/")->sleepers as $sleeper_data )
      $this->sleepers[$sleeper_data->sleeperId] = new Sleeper($this->connection, $sleeper_data);

    // Make beds relate to their sleepers
    foreach( $this->beds as $index => $bed ) {
      $bed->setSleeperLeft($this->sleepers[$bed->sleeperLeftId]);
      $bed->setSleeperRight($this->sleepers[$bed->sleeperRightId]);
    }

    // Make sleepers relate to their beds
    foreach( $this->sleepers as $index => $sleeper ) {
      $sleeper->setBed($this->beds[$sleeper->bedId]);
    }
  }

}




class Bed
{

  private $connection   = null;
  private $sleepers     = [];
  private $sleeperLeft  = null;
  private $sleeperRight = null;
  private $statusLeft   = null;
  private $statusRight  = null;

  function __construct( $connection, $json_data ) {
    $this->connection = $connection;
    foreach( $json_data as $field => $value )
      $this->$field = $value;
    //$this->refresh();
  }

  public function __get( $property ) {
    if ( in_array( $property, [ "statusLeft", "statusRight" ] ) )
      $this->refresh();
    if ( property_exists( $this, $property ) )
      return $this->$property;
  }

  public function setSleeperLeft( $sleeper ) {
    $this->sleeperLeft = $sleeper;
    $this->sleepers[0] = $sleeper;
  }

  public function setSleeperRight( $sleeper ) {
    $this->sleeperRight = $sleeper;
    $this->sleepers[1] = $sleeper;
  }
  
  public function refresh() {
    $response = $this->connection->requestJSON("/rest/bed/{$this->bedId}/status");
    $this->statusLeft  = $response->leftSide;
    $this->statusRight = $response->rightSide;
  }
  
/*
  public function getLiveData() {
    return $this->connection->requestJSON("/rest/bed/{$this->bedId}/liveData");
  }
*/
  
  public function getDatabaseData() {
    $properties_to_hide = [
      "connection",
      "sleepers",
      "sleeperLeft",
      "sleeperRight",
      "statusLeft",
      "statusRight",
    ];
    $data = [];
    foreach( get_object_vars( $this ) as $name => $value )
      if ( !in_array( $name, $properties_to_hide ) )
        $data[$name] = $value;
    return $data;
  }

  public function __toString() {
    $string  =  "";
    $string .=  "BED ({$this->bedId})\n";
    $properties_to_hide = [
      "connection",
      "sleepers",
      "sleeperLeft",
      "sleeperRight",
      "statusLeft",
      "statusRight",
    ];
    foreach( get_object_vars( $this ) as $name => $value )
      if ( !in_array( $name, $properties_to_hide ) )
        $string .=  "  {$name}: {$value}\n";
    $string .=  "  Status, Left Side:\n";
    foreach( get_object_vars( $this->statusLeft ) as $name => $value )
      $string .=  "    {$name}: {$value}\n";
    $string .=  "  Status, Right Side:\n";
    foreach( get_object_vars( $this->statusRight ) as $name => $value )
      $string .=  "    {$name}: {$value}\n";
    return $string;
  }

}


class Sleeper
{

  private $connection = null;
  private $bed        = null;

  function __construct( $connection, $json_data ) {
    $this->connection = $connection;
    foreach( $json_data as $field => $value )
      $this->$field = $value;
  }

  public function __get( $property ) {
    if ( property_exists( $this, $property ) )
      return $this->$property;
  }

  public function setBed( $bed ) {
    $this->bed = $bed;
  }
  
  public function getSleepData( $date, $interval ) {
    $interval_options = [ 'D', 'Y', 'M' ];
    $interval = in_array( $interval, $interval_options ) ? $interval : $interval_options[0];
    return $this->connection->requestJSON(
      "/rest/sleepData/?date={$date}&interval={$interval}1&sleeper={$this->sleeperId}"
    );
    return $this->connection->requestJSON(
      "/rest/sleepData/",
      [
        "date"     => $date,
        "interval" => $interval.'1',
        "sleeper"  => $this->sleeperId,
      ]
    );
  }
  
  public function getDatabaseData() {
    $properties_to_hide = [
      "connection",
      "bed",
      "avatar",
    ];
    $data = [];
    foreach( get_object_vars( $this ) as $name => $value )
      if ( !in_array( $name, $properties_to_hide ) )
        $data[$name] = $value;
    return $data;
  }

  public function __toString() {
    $string  =  "";
    $string .=  "SLEEPER ({$this->sleeperId})\n";
    $properties_to_hide = [
      "connection",
      "bed",
      "avatar",
    ];
    foreach( get_object_vars( $this ) as $name => $value )
      if ( !in_array( $name, $properties_to_hide ) )
        $string .=  "  {$name}: {$value}\n";
    return $string;
  }

}


class Connection
{

  private $site_url    = "https://sleepiq.sleepnumber.com";
  private $user_agent  = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)";
  private $logon_user  = null;
  private $logon_pass  = null;
  private $cookie_file = null;

  function __construct( $username, $password ) {
    // Set private variables
    $this->logon_user  = $username;
    $this->logon_pass  = $password;

    // Setup cookie file
    $this->cookie_file = "cookie/".$this->logon_user.".txt";
    if ( !is_dir( dirname( $this->cookie_file ) ) )
      mkdir( dirname( $this->cookie_file ), 0777, true );
    chmod( dirname( $this->cookie_file ), 0777 );
    touch( $this->cookie_file );
    chmod( $this->cookie_file, 0777 );
  }

   /////////////////////
  // Public Methods

  public function requestJSON($path, $data = null, $method = "GET", $called_by_self = false) {
    $url = $this->site_url.$path;
    
/*
    // Add timestamp to all request
    if ( !is_array( $data ) )
      $data = array();
    $data["_"] = time();
*/

    // Debugging
    echo "[[JSON]] {$method} {$url} ? (".print_r( $data, true ).")\n";

    // Create CURL request object
    $request = curl_init( $url );

    // Setup cookie file
    if ( $this->cookie_file ) {
      if ( !is_dir( dirname( $this->cookie_file ) ) )
        mkdir( dirname( $this->cookie_file ), 0777, true );
      curl_setopt( $request, CURLOPT_COOKIEFILE, $this->cookie_file ); // Store cookies in the specified file
      curl_setopt( $request, CURLOPT_COOKIEJAR, $this->cookie_file ); // Store cookies in the specified file
    }

    // Setup other options
    curl_setopt( $request, CURLOPT_ENCODING, "gzip" ); // Turn on GZIP compression
    curl_setopt( $request, CURLOPT_USERAGENT, $this->user_agent ); // Set user agent
  	curl_setopt( $request, CURLOPT_HEADER, 0 ); // set to 0 to eliminate header info from response
  	curl_setopt( $request, CURLOPT_RETURNTRANSFER, 1 ); // Returns response data instead of TRUE(1)
    curl_setopt( $request, CURLOPT_FOLLOWLOCATION, TRUE );

    // Setup request for JSON data
    if ( is_array( $data ) )
      curl_setopt( $request, CURLOPT_POSTFIELDS, json_encode( $data ) );
    curl_setopt( $request, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json' ) );
    curl_setopt( $request, CURLOPT_CUSTOMREQUEST, $method );

  	// Execute the request
  	$response = curl_exec( $request );

  	// Check for curl errors
  	if ( curl_errno( $request ) )
      throw new Exception( "Response Error: ".curl_error( $request ) );

    // Close CURL request object
    curl_close( $request );

    // Convert the CURL response to json
    $json_response = json_decode($response);

    // Check json for errors
    if ( !$json_response )
      throw new Exception( "requestJSON(): Missing/Invalid Response" );

    // Check for login errors errors
    if ( !$called_by_self && $this->requestJSONHasLoginErrors( $json_response ) ) {
      $this->performLogin();
      $this->requestJSON($path, $data, $method, true);
      return;
    }
    if ( property_exists( $json_response, 'Error' ) )
      throw new Exception( "requestJSON(): [".$json_response->Error->Code."] ".$json_response->Error->Message."" );

    // Return JSON
    return $json_response;
  }

   /////////////////////
  // Private Methods

  private function performLogin() {
    // Perform request
    $response = $this->requestJSON(
      "/rest/login/",
      [
        "login"    => $this->logon_user,
        "password" => $this->logon_pass,
      ],
      "PUT",
      true
    );

    // Check for errors
    if ( property_exists( $response, 'Error' ) )
      throw new Exception( "performLogin(): [".$response->Error->Code."] ".$response->Error->Message."" );
  }

  private function requestJSONHasLoginErrors($response) {
    // Are there any errors at all?
    if ( property_exists( $response, 'Error' ) ) {
      // Get error code and message
      $error_code    = property_exists( $response->Error, 'Code' )    ? $response->Error->Code    : null;
      $error_message = property_exists( $response->Error, 'Message' ) ? $response->Error->Message : null;

      // Known errors codes and messages
      $login_error_codes = [
        50002,
        401,
      ];
      $login_error_messages = [
        "Session is invalid",
        "HTTP 401 Unauthorized",
      ];

      // Return error if a code or message matches
      if ( in_array( $error_code, $login_error_codes ) )
        return true;
      if ( in_array( $error_message, $login_error_messages ) )
        return true;
    }

    // No login errors found
    return false;
  }

}

class Exception extends \Exception
{

}