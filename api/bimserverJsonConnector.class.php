<?php
/**
 * @file
 * Bimserver Connector php "Library"
 *
 * BIMserver
 */

 function variable_get($msg){
     return "";
 }

/**
 * @class
 * Class to connect with a BIMserver with.
 *
 * This class contains all the logic to make a JSON connection with a BIMserver.
 * It will store and reuse the login credentials after the login action and it
 * upon destruction it will emit an logout to the server
 * (to make sure the connection is terminated).
 */
class bimserverJsonConnector {
  private static $connections;
  private $user;

    function timer_start($name) {
        global $timers;

        $timers[$name]['start'] = microtime(TRUE);
        $timers[$name]['count'] = isset($timers[$name]['count']) ? ++$timers[$name]['count'] : 1;
    }

    function timer_read($name) {
        global $timers;

        if (isset($timers[$name]['start'])) {
            $stop = microtime(TRUE);
            $diff = round(($stop - $timers[$name]['start']) * 1000, 2);

            if (isset($timers[$name]['time'])) {
                $diff += $timers[$name]['time'];
            }
            return $diff;
        }
        return $timers[$name]['time'];
    }

    function _bimserver_parse_response_status($response) {
        $response_array = explode(' ', trim($response), 3);
        // Set up empty values.
        $result = array(
            'reason_phrase' => '',
        );
        $result['http_version'] = $response_array[0];
        $result['response_code'] = $response_array[1];
        if (isset($response_array[2])) {
            $result['reason_phrase'] = $response_array[2];
        }
        return $result;
    }

  public static function drupal_set_message($msg,$state,$flag = false){
   //echo ("\n[$state] $msg\n");
  }

    /**
     * Performs an HTTP request.
     *
     * This is a flexible and powerful HTTP client implementation. Correctly
     * handles GET, POST, PUT or any other HTTP requests. Handles redirects.
     *
     * @param $url
     *   A string containing a fully qualified URI.
     * @param array $options
     *   (optional) An array that can have one or more of the following elements:
     *   - headers: An array containing request headers to send as name/value pairs.
     *   - method: A string containing the request method. Defaults to 'GET'.
     *   - data: A string containing the request body, formatted as
     *     'param=value&param=value&...'. Defaults to NULL.
     *   - max_redirects: An integer representing how many times a redirect
     *     may be followed. Defaults to 3.
     *   - timeout: A float representing the maximum number of seconds the function
     *     call may take. The default is 30 seconds. If a timeout occurs, the error
     *     code is set to the HTTP_REQUEST_TIMEOUT constant.
     *   - context: A context resource created with stream_context_create().
     *
     * @return object
     *   An object that can have one or more of the following components:
     *   - request: A string containing the request body that was sent.
     *   - code: An integer containing the response status code, or the error code
     *     if an error occurred.
     *   - protocol: The response protocol (e.g. HTTP/1.1 or HTTP/1.0).
     *   - status_message: The status message from the response, if a response was
     *     received.
     *   - redirect_code: If redirected, an integer containing the initial response
     *     status code.
     *   - redirect_url: If redirected, a string containing the URL of the redirect
     *     target.
     *   - error: If an error occurred, the error message. Otherwise not set.
     *   - headers: An array containing the response headers as name/value pairs.
     *     HTTP header names are case-insensitive (RFC 2616, section 4.2), so for
     *     easy access the array keys are returned in lower case.
     *   - data: A string containing the response body that was received.
     */
    public static function _bim_http_request($url, array $options = array()) {
        $result = new stdClass();

        // Parse the URL and make sure we can handle the schema.
        $uri = @parse_url($url);

        if ($uri == FALSE) {
            $result->error = 'unable to parse URL';
            $result->code = -1001;
            return $result;
        }

        if (!isset($uri['scheme'])) {
            $result->error = 'missing schema';
            $result->code = -1002;
            return $result;
        }

        bimserverJsonConnector::timer_start(__FUNCTION__);

        // Merge the default options.
        $options += array(
            'headers' => array(),
            'method' => 'GET',
            'data' => NULL,
            'max_redirects' => 3,
            'timeout' => 30.0,
            'context' => NULL,
        );

        // Merge the default headers.
        $options['headers'] += array(
            'User-Agent' => 'Drupal (+http://drupal.org/)',
        );

        // stream_socket_client() requires timeout to be a float.
        $options['timeout'] = (float) $options['timeout'];

        // Use a proxy if one is defined and the host is not on the excluded list.
        $proxy_server = variable_get('proxy_server', '');
        if ($proxy_server && _drupal_http_use_proxy($uri['host'])) {
            // Set the scheme so we open a socket to the proxy server.
            $uri['scheme'] = 'proxy';
            // Set the path to be the full URL.
            $uri['path'] = $url;
            // Since the URL is passed as the path, we won't use the parsed query.
            unset($uri['query']);

            // Add in username and password to Proxy-Authorization header if needed.
            if ($proxy_username = variable_get('proxy_username', '')) {
                $proxy_password = variable_get('proxy_password', '');
                $options['headers']['Proxy-Authorization'] = 'Basic ' . base64_encode($proxy_username . (!empty($proxy_password) ? ":" . $proxy_password : ''));
            }
            // Some proxies reject requests with any User-Agent headers, while others
            // require a specific one.
            $proxy_user_agent = variable_get('proxy_user_agent', '');
            // The default value matches neither condition.
            if ($proxy_user_agent === NULL) {
                unset($options['headers']['User-Agent']);
            }
            elseif ($proxy_user_agent) {
                $options['headers']['User-Agent'] = $proxy_user_agent;
            }
        }

        switch ($uri['scheme']) {
            case 'proxy':
                // Make the socket connection to a proxy server.
                $socket = 'tcp://' . $proxy_server . ':' . variable_get('proxy_port', 8080);
                // The Host header still needs to match the real request.
                $options['headers']['Host'] = $uri['host'];
                $options['headers']['Host'] .= isset($uri['port']) && $uri['port'] != 80 ? ':' . $uri['port'] : '';
                break;

            case 'http':
            case 'feed':
                $port = isset($uri['port']) ? $uri['port'] : 80;
                $socket = 'tcp://' . $uri['host'] . ':' . $port;
                // RFC 2616: "non-standard ports MUST, default ports MAY be included".
                // We don't add the standard port to prevent from breaking rewrite rules
                // checking the host that do not take into account the port number.
                $options['headers']['Host'] = $uri['host'] . ($port != 80 ? ':' . $port : '');
                break;

            case 'https':
                // Note: Only works when PHP is compiled with OpenSSL support.
                $port = isset($uri['port']) ? $uri['port'] : 443;
                $socket = 'ssl://' . $uri['host'] . ':' . $port;
                $options['headers']['Host'] = $uri['host'] . ($port != 443 ? ':' . $port : '');
                break;

            default:
                $result->error = 'invalid schema ' . $uri['scheme'];
                $result->code = -1003;
                return $result;
        }

        if (empty($options['context'])) {
            $fp = @stream_socket_client($socket, $errno, $errstr, $options['timeout']);
        }
        else {
            // Create a stream with context. Allows verification of a SSL certificate.
            $fp = @stream_socket_client($socket, $errno, $errstr, $options['timeout'], STREAM_CLIENT_CONNECT, $options['context']);
        }

        // Make sure the socket opened properly.
        if (!$fp) {
            // When a network error occurs, we use a negative number so it does not
            // clash with the HTTP status codes.
            $result->code = -$errno;
            $result->error = trim($errstr) ? trim($errstr) : t('Error opening socket @socket', array('@socket' => $socket));

            // Mark that this request failed. This will trigger a check of the web
            // server's ability to make outgoing HTTP requests the next time that
            // requirements checking is performed.
            // See system_requirements().
            //variable_set('_bim_http_request_fails', TRUE);

            return $result;
        }

        // Construct the path to act on.
        $path = isset($uri['path']) ? $uri['path'] : '/';
        if (isset($uri['query'])) {
            $path .= '?' . $uri['query'];
        }

        // Only add Content-Length if we actually have any content or if it is a POST
        // or PUT request. Some non-standard servers get confused by Content-Length in
        // at least HEAD/GET requests, and Squid always requires Content-Length in
        // POST/PUT requests.
        $content_length = strlen($options['data']);
        if ($content_length > 0 || $options['method'] == 'POST' || $options['method'] == 'PUT') {
            $options['headers']['Content-Length'] = $content_length;
        }

        // If the server URL has a user then attempt to use basic authentication.
        if (isset($uri['user'])) {
            $options['headers']['Authorization'] = 'Basic ' . base64_encode($uri['user'] . (isset($uri['pass']) ? ':' . $uri['pass'] : ':'));
        }

        // If the database prefix is being used by SimpleTest to run the tests in a copied
        // database then set the user-agent header to the database prefix so that any
        // calls to other Drupal pages will run the SimpleTest prefixed database. The
        // user-agent is used to ensure that multiple testing sessions running at the
        // same time won't interfere with each other as they would if the database
        // prefix were stored statically in a file or database variable.
        $test_info = &$GLOBALS['drupal_test_info'];
        if (!empty($test_info['test_run_id'])) {
            $options['headers']['User-Agent'] = drupal_generate_test_ua($test_info['test_run_id']);
        }

        $request = $options['method'] . ' ' . $path . " HTTP/1.0\r\n";
        foreach ($options['headers'] as $name => $value) {
            $request .= $name . ': ' . trim($value) . "\r\n";
        }
        $request .= "\r\n" . $options['data'];
        $result->request = $request;
        // Calculate how much time is left of the original timeout value.
        $timeout = $options['timeout'] - bimserverJsonConnector::timer_read(__FUNCTION__) / 1000;
        if ($timeout > 0) {
            stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
            fwrite($fp, $request);
        }

        // Fetch response. Due to PHP bugs like http://bugs.php.net/bug.php?id=43782
        // and http://bugs.php.net/bug.php?id=46049 we can't rely on feof(), but
        // instead must invoke stream_get_meta_data() each iteration.
        $info = stream_get_meta_data($fp);
        $alive = !$info['eof'] && !$info['timed_out'];
        $response = '';

        while ($alive) {
            // Calculate how much time is left of the original timeout value.
            $timeout = $options['timeout'] - bimserverJsonConnector::timer_read(__FUNCTION__) / 1000;
            if ($timeout <= 0) {
                $info['timed_out'] = TRUE;
                break;
            }
            stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
            $chunk = fread($fp, 1024);
            $response .= $chunk;
            $info = stream_get_meta_data($fp);
            $alive = !$info['eof'] && !$info['timed_out'] && $chunk;
        }
        fclose($fp);
        $HTTP_REQUEST_TIMEOUT = 408;
        if ($info['timed_out']) {
            $result->code = $HTTP_REQUEST_TIMEOUT;
            $result->error = 'request timed out';
            return $result;
        }
        // Parse response headers from the response body.
        // Be tolerant of malformed HTTP responses that separate header and body with
        // \n\n or \r\r instead of \r\n\r\n.
        list($response, $result->data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        $response = preg_split("/\r\n|\n|\r/", $response);

        // Parse the response status line.
        $response_status_array = bimserverJsonConnector::_bimserver_parse_response_status(trim(array_shift($response)));
        $result->protocol = $response_status_array['http_version'];
        $result->status_message = $response_status_array['reason_phrase'];
        $code = $response_status_array['response_code'];

        $result->headers = array();

        // Parse the response headers.
        while ($line = trim(array_shift($response))) {
            list($name, $value) = explode(':', $line, 2);
            $name = strtolower($name);
            if (isset($result->headers[$name]) && $name == 'set-cookie') {
                // RFC 2109: the Set-Cookie response header comprises the token Set-
                // Cookie:, followed by a comma-separated list of one or more cookies.
                $result->headers[$name] .= ',' . trim($value);
            }
            else {
                $result->headers[$name] = trim($value);
            }
        }

        $responses = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested range not satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
        );
        // RFC 2616 states that all unknown HTTP codes must be treated the same as the
        // base code in their class.
        if (!isset($responses[$code])) {
            $code = floor($code / 100) * 100;
        }
        $result->code = $code;

        switch ($code) {
            case 200: // OK
            case 304: // Not modified
                break;
            case 301: // Moved permanently
            case 302: // Moved temporarily
            case 307: // Moved temporarily
                $location = $result->headers['location'];
                $options['timeout'] -= timer_read(__FUNCTION__) / 1000;
                if ($options['timeout'] <= 0) {
                    $result->code = HTTP_REQUEST_TIMEOUT;
                    $result->error = 'request timed out';
                }
                elseif ($options['max_redirects']) {
                    // Redirect to the new location.
                    $options['max_redirects']--;
                    $result = _bim_http_request($location, $options);
                    $result->redirect_code = $code;
                }
                if (!isset($result->redirect_url)) {
                    $result->redirect_url = $location;
                }
                break;
            default:
                $result->error = $result->status_message;
        }

        return $result;
    }

  public static function getConnection($server, $user, $login) {
    if (isset(bimserverJsonConnector::$connections)) {
      if (isset(bimserverJsonConnector::$connections[$server])) {
        if (isset(bimserverJsonConnector::$connections[$server][$user])) {
          return bimserverJsonConnector::$connections[$server][$user];
        }
      }
    }
    return new bimserverJsonConnector($server, $user, $login);
  }


  /**
   *
   */
   function __construct($server = 'SERVER-URL', $user = 'USERNAME', $login = 'USER-KEY') {
    $this->server = $server;
    $this->user = $user;
    bimserverJsonConnector::$connections[$server][$user] = $this;
    //$this->bms_dologin($server,$user,$login);
    $this->login($login);
  }

  /**
   * DESTRUCTOR: when called it will do  a logout and unset the stored values.
   */
  function __destruct() {
    //$this -> logout();
    //unset($this -> login_token);
    //unset($this -> server);
  }

  //META functions for convienience.
  /**
   * All the stuff to do the Bimserver login!!
   * @return string
   *//*
  private function bms_dologin($server = 'http://localhost:8080',$user = 'bimuser@localhost',$secret = 'I_WANNA_PARTY_!!!!')
  {
    $this->setServer($server);
    return $this->login($user,$secret);
  }*/

  protected $bimqloid = 0;
  // Stores the bimQL Engine OID as used by the server.

  // Server Connection details (e.a. "HTTP://bimserver.localhost:8080/json")
  protected $server = "";

  // Token to validate connection to server with (retrieved at "login".)
  public $login_token;

  public function getLoginToken() {
    return $this->login_token;
  }

  public function setLoginToken($token) {
    $this->login_token = $token;
    return $this;
  }

  /**
   * Searches the list of query engines for the 1ST 'BimQL' Engine & returns it
   * @return object
   *   The bimQLEngine Object.
   */
  public function getBimQLEngine() {
    $engines = $this->getAllQueryEngines();
    $bimQlengine = NULL;
    foreach ($engines['result'] as $engine) {
      if ($engine['description'] == 'BimQL') {
        $this->bimqloid = $engine['oid'];
        $bimQlengine = $engine;
      }
    }
    return $bimQlengine;
  }

  /**
   * Retrieves the bimQL Query Engine OID from memory or retrieves it if not.
   * @return integer
   *   BimQLoid (should be an integer).
   */
  public function getBimQLoid() {
    if ($this->bimqloid == 0 || empty($this->bimqloid)) {
      $this->getBimQLEngine();
    }
    return $this->bimqloid;
  }

  /**
   * Set the Server connect string.
   * should be formatted "[protocol]://[serveraddres]:[serverport]/json"
   * like "HTTP://example.com:8080/json"
   */
  public function setServer($serverconnectstring) {
    $this->server = $serverconnectstring;
  }

  /**
   * Login on the BIMserver and stores the login token.
   * @return string
   *   The Login Token.
   */
  private function login($password) {//$username,
    $login['username'] = $this->user;
    $login['password'] = $password;
    $ret = $this->doPost("Bimsie1AuthInterface", "login", $login);
    unset($login);
    //var_dump($ret);
    if(is_object($ret) && isset($ret->response)) {
        $this->setLoginToken($ret->response->result);
    }
    else {
      bimserverJsonConnector::drupal_set_message("Login Failed: Return object was not an Object",'error',false);
    }
    //var_dump($this->getLoginToken());
    //$this->login_token = $ret['response']['result'];

    if (isset($ret->response->result)) {
      $this->login_token = $ret->response->result;
    }
    else {
      $this->login_token = "LOGIN FAILED!";
    }
    return $this;//$ret['response']['result']; //-> login_token;
  }

  /**
   * Logout from the BIMserver
   * @return object
   *   the status object of the logout action.
   */
  public function logout() {
    $ret = $this->doPost("Bimsie1AuthInterface", "logout", NULL);
    $this->login_token = "";
    return $ret;
  }

  /**
   * Retrieves a list of all service providers form the server.
   * @return object
   *   Object containing the list of services.
   */
  public function metaGetServices() {
    return $this->doPost("MetaInterface", "getServiceInterfaces", NULL);
  }

  /**
   *
   */
  public function metaGetAllAsJson() {
        return $this->doPost("MetaInterface", "getAllAsJson", NULL);
    }

    public function LowLevelAbortTransaction($tid) {
        return $this->doPost("Bimsie1LowLevelInterface", "abortTransaction", array("tid" => $tid));
    }

    public function LowLevelCommitTransaction($tid,$comment = "No Comment Entered") {
        return $this->doPost("Bimsie1LowLevelInterface", "commitTransaction", array("tid" => $tid,"comment"=>$comment));
    }

    /**
     * @param $poid
     * @return object Transaction id to use this transaction with.
     */
    public function LowLevelStartTransaction($poid) {
        return $this->doPost("Bimsie1LowLevelInterface", "startTransaction", array("poid" => $poid));
    }

    public function getDataObjectByGuid($roid,$guid) {
        return $this->doPost("Bimsie1LowLevelInterface", "getDataObjectByGuid", array("roid" => $roid, "guid" => $guid));
    }

    public function setStringAttribute($tid,$oid,$attributeName,$value) {
      return $this->doPost("Bimsie1LowLevelInterface", "setStringAttribute", array("tid" => $tid, "oid" => $oid, "attributeName" => $attributeName, "value" => $value));
    }

    public function revisionCompare($roid1,$roid2,$compareType,$mcid) {
        return $this->doPost("ServiceInterface", "compare", array("roid1" => $roid1, "roid2" => $roid2, "sCompareType" => $compareType, "mcid" => $mcid));
    }

    public function getAllModelCheckers() {
        return $this->doPost("ServiceInterface", "getAllModelCheckers");
    }

    public function getDefaultModelCompare() {
        return $this->doPost("PluginInterface", "getDefaultModelCompare");
    }

    public function getAllModelCompares() {
        $params["onlyEnabled"] = TRUE;
        $ret = $this->doPost("PluginInterface", "getAllModelCompares", $params);
        return $ret->response;
    }

    /**
   * Retrieves all Query Engines form the BIMserver
   * @return object
   *   An object (array) of all Query engines within the bimserver that are enabled.
   */
  public function getAllQueryEngines() {
    $params["onlyEnabled"] = TRUE;
    $ret = $this->doPost("PluginInterface", "getAllQueryEngines", $params);
    return $ret['response'];
  }

  /**
   * retrieves the full list of objects associated with the specified ROID.
   *
   * @param $roid Revision Object Identifier
   */
  public function getProjectDataObjects($roid) {
    return $this->doPost("Bimsie1LowLevelInterface", "getDataObjects", $param["roid"] = $roid);
  }

  public function getRawObject($oid, $roid) {
    $interface = "Bimsie1LowLevelInterface";
    $method = "getDataObjectByOid";
    $parameters["roid"] = $roid;
    $parameters['oid'] = $oid;
    //$parameters =  '"roid": "'.$roid.'", "oid": "'.$oid.'"';//,array("".$roid,"".$oid));
    //$parameters = '"roid": "' . $roid .', "oid": "' . $oid ;
    $ret = $this->doPost($interface, $method, $parameters);
    $dump['interface'] = $interface;
    $dump['method'] = $method;
    $dump['parameters'] = $parameters;
    global $DEBUG;
    if($DEBUG) dpr($dump);
    return $ret;
  }

  public function getAllProjects($onlyTopLevel, $onlyActive) {
    $params["onlyTopLevel"] = $onlyTopLevel;
    $params['onlyActive'] = $onlyActive;
    $ret = $this->doPost("Bimsie1ServiceInterface", "getAllProjects", $params);
    if (isset($ret->response->result)) {
      return $ret->response->result;
    }
    return NULL;
  }

  public function getRevision($roid) { //gets Revision Info / metadata.
    $ret = $this->doPost("Bimsie1ServiceInterface", "getRevision", $params["roid"] = $roid);
    return $ret;
  }

  public function getProject($poid) { //gets Project Info / metadata.
    $ret = $this->doPost("Bimsie1ServiceInterface", "getProjectByPoid", $params["poid"] = $poid);
    return $ret;
  }

  public function getServerSettings() {
    $settings = $this->doPost("SettingsInterface", "getServerSettings", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getEmailSenderAddress() {
    $settings = $this->doPost("SettingsInterface", "getEmailSenderAddress", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getServiceRepositoryUrl() {
    $settings = $this->doPost("SettingsInterface", "getServiceRepositoryUrl", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getSiteAddress() {
    $settings = $this->doPost("SettingsInterface", "getSiteAddress", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getSmtpServer() {
    $settings = $this->doPost("SettingsInterface", "getSmtpServer", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function upgradePossible() {
    $settings = $this->doPost("AdminInterface", "upgradePossible", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getVersion() {
    $settings = $this->doPost("AdminInterface", "getVersion", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getSystemInfo() {
    $settings = $this->doPost("AdminInterface", "getSystemInfo", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getServerStartTime() {
    $settings = $this->doPost("AdminInterface", "getServerStartTime", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getServerLog() {
    $settings = $this->doPost("AdminInterface", "getServerLog", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getServerInfo() {
    $settings = $this->doPost("AdminInterface", "getServerInfo", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getMigrations() {
    $settings = $this->doPost("AdminInterface", "getMigrations", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getLogs() {
    $settings = $this->doPost("AdminInterface", "getLogs", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getLatestVersion() {
    $settings = $this->doPost("AdminInterface", "getLatestVersion", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getLastDatabaseReset() {
    $settings = $this->doPost("AdminInterface", "getLastDatabaseReset", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getJavaInfo() {
    $settings = $this->doPost("AdminInterface", "getJavaInfo", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getDatabaseInformation() {
    $settings = $this->doPost("AdminInterface", "getDatabaseInformation", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getBimServerInfo() {
    $settings = $this->doPost("AdminInterface", "getBimServerInfo", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function getAllPlugins() {
    $settings = $this->doPost("AdminInterface", "getAllPlugins", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  public function clearOutputFileCache() {
    $settings = $this->doPost("AdminInterface", "clearOutputFileCache", NULL);
    if (isset($settings->response->result)) {
      return $settings->response->result;
    }
    return NULL;
  }

  /**
   * Pass the request on as a RPC to bimserver, use able as proxy.
   * @param $class string What class is the methode your calling from
   * @param $method string Methode to call.
   * @param $options string Options to use when calling this methode (a.k.a. parameters)
   * @return object the returned awnser from the RPC.
   */
  public function proxyPass($class, $method, $options) {
    if (TRUE) {
      /*
      if(is_object($options) | is_array($options)) {
        $options2 = json_encode($options);
        $options3 = $this->parseOptions($options);
        //$options=$options2;
      }*/
      return $this->doPost($class, $method, $options, FALSE);
    }
  }

  private function parseOptions($opt) {
    if (is_object($opt)) {
      throw new ServicesArgumentException("Options can not be an Object", "options", 500, $opt);
    }
    $out = "";

    foreach ($opt as $key => $val) {
      if (is_array($val)) {
        drupal_set_message("Warning, untested method used Ref= #4562354A, lawri", 'WARNING', FALSE);
        $out .= ",\"$key\":[" . bimserverJsonConnector::_parseArray($val) . "]"; //"parameters":{"endPointId":130}
      }
      else {
        $out .= ",\"$key\":\"$val\"";
      }
    }
    $out = substr($out, 1);
    return $out;
  }

  private static function _parseArray($arr) {
    $out = "";
    foreach ($arr as $key => $val) {
      if (is_array($val)) {
        $out .= bimserverJsonConnector::_parseArray($val);
      }
      if (!is_numeric($val)) {
        $val = "\"$val\"";
      }
      $out .= "$val,";
    }
    $out = rtrim($out, ", \0");

    return "$out";
  }

  /**
   * Does the correct Drupal POST JSON action requested.
   * this methode is cusing the proper drupla methods t ocreate and parse the
   * JSON messages (send and received).
   * @param string $interface
   *   The Interface of wich the method is part of.
   * @param string $method
   *   The method you want to run on the BIMserver.
   * @param string $parameters
   *   A list of named parameters wich are comma-seperated.
   * @return object
   *   the parsed JSON return object. or null if it contained no data.
   */
  protected function doPost($interface, $method, $parameters, $shorten = TRUE, $addJson = TRUE) {
    /*$data = "";
    $data = $data . "{";
    if (isset($this -> login_token)) {
      $data = $data . "\"token\":\"" . $this -> login_token . "\",";
    }
    $data = $data . "\"request\": {\"interface\":\"" . $interface . 
      "\",\"method\":\"" . $method . "\",\"parameters\": {" . $parameters . "}}}";
    */
    global $DEBUG;
    if (isset($this->login_token)) {
      $send['token'] = $this->login_token;
      if($DEBUG) {
        $f = fopen("/tmp/proxyToken.log", "a");
        if ($f != false) {
          fwrite($f, date("c") . "\tToken: '" . var_export($this->login_token, TRUE) . "'\n");
        }
        fclose($f);
      }
    }
    $send['request']['interface'] = $interface;
    $send['request']['method'] = $method;
    if (empty($parameters)) {
      $send['request']['parameters'] = "{}";
    }
    else {
      $send['request']['parameters'] = $parameters;
    }

    //$data = json_encode($send);


    $options['headers']['Content-Type'] = 'application/json';
    $options['method'] = 'post';
    $options['data'] = json_encode($send);

    $fixed = 0;
    $options['data'] = str_replace("\"{}\"", "{}", $options['data'], $fixed);
    if (!empty($fixed)) {
      bimserverJsonConnector::drupal_set_message('BMS-Bimserver Proxy', "Swapped  '\"{}\"' for '{} [$fixed*]\nProxy Called:=$interface.$method($parameters)'.");
      //drupal_set_message("Fixed $fixed \"{}\" to {}.",'warning');
    }
    if($DEBUG) {
      $f = fopen("/tmp/proxyPost.log", "a");
      if ($f != false) {
        fwrite($f, "CALL: '" . var_export($options, TRUE) . "'\n");
      }
    }
    $postServer = $this->server;
    if ($addJson) {
      $postServer .= '/json';
    }

    $result = bimserverJsonConnector::_bim_http_request($postServer, $options);
    if($DEBUG) {
      if ($f != false) {
        fwrite($f, "AWNS: \t'" . var_export($result, TRUE) . "'\n");
        fclose($f);
      }
    }
    //dsm($result);
    if ($shorten) {
      if (empty($result->data)) {
        $result = $result; //->data;
      }
      else {
        $result = json_decode($result->data);
      }
    }
    else {
      if (!empty($result->data)) {
        $result->data = json_decode($result->data);
      }
    }
    return $result;
  } //test


  public function doGet($parameters,$addDownload = true) {
    //drupal_http_build_query($parameters);

    $options['headers']['Content-Type'] = 'application/json';
    $options['headers']['Accept-Encoding'] = 'gzip, deflate';
    $options['method'] = 'get';//'post';
    //$options['data'] = drupal_http_build_query($parameters);//json_encode($send);
    $options['timeout'] = 3000000000;
    global $DEBUG;
    if($DEBUG) {
      $f = fopen("/tmp/proxyPost.log", "a");
      fwrite($f, "CALL: GET='" . var_export($options, TRUE) . "'\n");
    }
    $postServer = $this->server;
    if ($addDownload) {
      $postServer .= '/download';
    }

    //Hack to  test if get works like this.
    $postServer.="?".drupal_http_build_query($parameters);

    $result = _bim_http_request($postServer,$options);//bimserverJsonConnector::_bim_http_request($postServer, $options);

    if (($result->headers['Content-Encoding'] == 'gzip')||($result->headers['content-encoding'] == 'gzip')) {
      $result->data2 = substr($result->data,10);
      $result->data3 = gzinflate(substr($result->data,10));
      $result->data =  drupal_json_decode(gzinflate(substr($result->data,10)));
      if(isset($result->headers['Content-Encoding'])) {
        unset($result->headers['Content-Encoding']);
        $result->headers['Content-Encoding'] = "text/plain";
      }
      if(isset($result->headers['content-encoding'])) {
        unset($result->headers['content-encoding']);
        $result->headers['content-encoding'] = "text/plain";
      }
      //$result->headers['Content-Encoding'] = "text/plain";
    }
    if ($DEBUG) {
      fwrite($f, "AWNS: \t'" . var_export($result, TRUE) . "'\n");
      fclose($f);
    }
    //dsm($result);

    /*
    if ($shorten) {
      if (empty($result->data)) {
        $result = $result; //->data;
      }
      else {
        $result = drupal_json_decode($result->data);
      }
    }
    else {
      if (!empty($result->data)) {
        $result->data = drupal_json_decode($result->data);
      }
    }*/
    return $result;
  }

  public function doUpload($poid,$comment,$filename,$deserializerOid,$addDownload = true) {
      $postServer = $this->server;
      if ($addDownload) {
        $postServer .= '/upload';
      }
    $url = $postServer;
    $ch = curl_init($url);
    if (isset($this->login_token)) {
      $fields['token'] = $this->login_token;
    }
    $fields["poid"] = $poid;
    $fields["comment"] = $comment;
    $fields["merge"] = false;
    $fields["deserializerOid"] = $deserializerOid;
    $fields["file"] = "@" . $filename;
    $fields["sync"] = "true";//"false";//

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response == FALSE) {
      throw new Exception(curl_error($ch));
    }
    curl_close($ch);


    /* from https://github.com/opensourceBIM/phpClientLib/blob/master/api/bimserverapi.php
      public function checkin($poid, $comment, $filename, $deserializerOid, $filename) {
      $url = $this->baseUrl . "/upload";
      $ch = curl_init($url);
      $fields = array(
        "token" => $this->token,
        "poid" => $poid,
        "comment" => $comment,
        "merge" => false,
        "deserializerOid" => $deserializerOid,
        "file" => "@" . $filename,
        "sync" => "true"
      );
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);
      if ($response == FALSE) {
        throw new Exception(curl_error($ch));
      }
      curl_close($ch);
    }*/
    return $response;
  }
   //test





}

//
//curl 'http://bimserver:8080/bimserver-1.3.4-FINAL-2014-10-17/json'  -H 'Content-Type: application/json' --data-binary '{"token":"TOKEN","request":{"interface":"Bimsie1LowLevelInterface","method":"getDataObjectByOid","parameters":{"roid":"65539","oid":"65814"}}}'
//{
// "token":47c24506b35eb71ab19a80c6faa31d0d867e1223b6542bb72dce22537549fc0399f2da43881c025da3cab971794b5ada,
// "request": {
//    "interface":"Bimsie1LowLevelInterface",
//    "method":"getDataObjectByOid",
//    "parameters": {
//      roid": "65539",
//     "oid": "65814"}}
//}
