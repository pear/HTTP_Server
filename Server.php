<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Stephan Schmidt <schst@php-tools.net>                       |
// +----------------------------------------------------------------------+
//
//    $Id$

/**
 * HTTP_Server
 *
 * simple HTTP server class that analyses a request,
 * calls the appropriate request handler (get,post,..)
 * and sends a response
 *
 * Request handler may be implemented by the developer
 * and have to return an array with reponse code, headers
 * and repsonse body
 * 
 * ever wanted your HTTP server to get content from a database
 * or shared memory instead of files? Now it's possible, if you
 * know a bit of PHP
 *
 * To create your own HTTP server, just extend this class
 * and implement the methods GET(), POST(), PUT() and DELETE()
 *
 * @version 0.3
 * @author  Stephan Schmidt <schst@php-tools.de>
 */

/**
 * Can be changed in test environment
 */
if( !defined('HTTP_SERVER_INCLUDE_PATH') ) {
    define( 'HTTP_SERVER_INCLUDE_PATH', 'HTTP/Server' );
}

/**
 * uses Net_Server as driver
 */
require_once 'Net/Server.php';

/**
 * some HTTP utilities
 */
require_once 'HTTP.php';

/**
 * request class
 */
require_once HTTP_SERVER_INCLUDE_PATH . '/Request.php';

/**
 * HTTP_Server
 *
 * simple HTTP server class that analyses a request,
 * calls the appropriate request handler (get,post,..)
 * and sends a response
 *
 * Request handler may be implemented by the developer
 * and have to return an array with reponse code, headers
 * and repsonse body
 * 
 * ever wanted your HTTP server to get content from a database
 * or shared memory instead of files? Now it's possible, if you
 * know a bit of PHP
 *
 * To create your own HTTP server, just extend this class
 * and implement the methods GET(), POST(), PUT() and DELETE()
 *
 * @version 0.3
 * @author  Stephan Schmidt <schst@php-tools.de>
 */
class HTTP_Server
{
   /**
    * driver
    * @var object Net_Server_Driver
    */
    var $_driver;

   /**
    * list of HTTP status codes
    * @var array $_statusCodes
    */
    var $_statusCodes = array(
                                100 => 'Continue',
                                101 => 'Switching Protocols',
                                102 => 'Processing',
                                200 => 'OK', 
                                201 => 'Created', 
                                202 => 'Accepted', 
                                203 => 'Non-Authoriative Information', 
                                204 => 'No Content', 
                                205 => 'Reset Content', 
                                206 => 'Partial Content', 
                                207 => 'Multi-Status',
                                300 => 'Multiple Choices', 
                                301 => 'Moved Permanently', 
                                302 => 'Found', 
                                303 => 'See Other', 
                                304 => 'Not Modified', 
                                305 => 'Use Proxy', 
                                307 => 'Temporary Redirect',
                                400 => 'Bad Request', 
                                401 => 'Unauthorized', 
                                402 => 'Payment Granted', 
                                403 => 'Forbidden', 
                                404 => 'File Not Found', 
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
                                422 => 'Unprocessable Entity',
                                423 => 'Locked', 
                                424 => 'Failed Dependency',
                                500 => 'Internal Server Error',
                                501 => 'Not Implemented',
                                502 => 'Overloaded',
                                503 => 'Gateway Timeout',
                                505 => 'HTTP Version not supported',
                                507 => 'Insufficient Storage'
                            );

   /**
    * default response headers
    * @var array $_defaultResponseHeaders
    */
    var $_defaultResponseHeaders = array(
                                            'Server'     => 'PEAR HTTP_Server/0.3',
                                            'Connection' => 'close',
                                        );

   /**
	* constructor
	*
	* @access   public
    * @param    string      hostname
    * @param    integer     port
    * @param    string      driver, see Net_Server documentation
	*/
    function HTTP_Server($hostname, $port, $driver = 'Fork')
    {
        $this->__construct( $hostname, $port, $driver );
    }

   /**
	* constructor
	*
	* @access   public
    * @param    string      hostname
    * @param    integer     port
    * @param    string      driver, see Net_Server documentation
	*/
    function __construct($hostname, $port, $driver = 'Fork')
    {
        $this->_driver = &Net_Server::create($driver, $hostname, $port);
        $this->_driver->readEndCharacter  = "\r\n\r\n";
        
        $this->_driver->setCallbackObject($this);
    }

   /**
    * start the server
    *
    * @access   public
    */    
    function start()
    {
        return $this->_driver->start();
    }
    
   /**
    * data was received, i.e. HTTP request sent
    * 
    * @param    integer   $clientId    socket that sent the request
    * @param    string    $data        raw request data
    */
    function onReceiveData($clientId, $data)
    {
        // parse request headers
        $request = &HTTP_Server_Request::parse($data);
        
        if ($request === false) {
        	
        }

        $this->_serveRequest($clientId, $request);
        
        // close the connection
        $this->_driver->closeConnection($clientId);
    }

   /**
    * serve a request
    *
    * @access private
    * @param  array $headers  request headers
    */
    function _serveRequest($clientId, $request)
    {
        $method = $request->getMethod();
    
        if (method_exists($this, $method)) {
            $response = $this->$method($clientId, $request);
        } else {
            // no method for the request defined => Server error
            $response = array(
                               "code" => 501
                             );
        }
        
        //  response is NULL, the callback already sent the response
        if ($response===NULL) {
            return true;
        }

        //  response is false, an error occured => send internal server error
        if ($response===false) {
            $response = array();
            $response["code"] = 500;
        }

        //  no status code => assume 200
        if (!isset($response["code"])) {
            $response["code"] = 200;
        }
        
        // resolve the status code
        $response["code_translated"] = $this->_resolveStatusCode($response["code"]);

        //  send the response code
        $this->_driver->sendData($clientId, sprintf("HTTP/1.0 %s %s\n", $response["code"], $response["code_translated"]));

        //  check for headers
        if (!isset($response["headers"]) || (!is_array($response["headers"])) ) {
            $response["headers"] = array();
        }
        
        //  merge with default headers
        $response["headers"] = array_merge($this->_defaultResponseHeaders, $response["headers"]);

        if (!isset($response["headers"]["Date"])) {
            $response["headers"]["Date"] = HTTP::Date(time());
        }
        
        //  add the Content-Length Header
        if (isset($response["body"]) && is_string($response["body"])) {
            if (!isset($response["headers"]["Content-Length"])) {
                $response["headers"]["Content-Length"] = strlen($response["body"]);
            }
        }

        //  send the headers            
        foreach($response["headers"] as $header => $value) {
                $this->_driver->sendData($clientId, sprintf("%s: %s\n", $header, $value));
        }

        
        //  send the response body
        if (isset($response["body"])) {
            $this->_driver->sendData($clientId, "\n");
            if (is_string($response["body"])) {
                $this->_driver->sendData($clientId, $response["body"]);
            }
            elseif (is_resource($response["body"])) {
                while (!feof($response["body"])) {
                    $data = fread($response["body"], 4096);
                    $this->_driver->sendData($clientId, $data);
                }
                fclose($response["body"]);
            }
        }    
        return true;
    }
    
   /**
    * handle a GET request
    * this method should return an array of the following format:
    *
    * array(
    *        "code"    => 200,
    *        "headers" => array(
    *                            "Content-Type" => "text/plain",
    *                            "X-Powered-By" => "PEAR"
    *                          ),
    *        "body"    => "This is the actual response"
    *      )
    *
    * @access public
    * @param  integer $clientId   id of the client that send the headers
    * @param  array   $headers    request headers
    * @return mixed   $response   array containing the response or false if the method handles the response
    * @see    POST()
    */
    function GET($clientId, $headers)
    {
    }

   /**
    * handle a POST request
    * this method should return an array of the following format:
    *
    * array(
    *        "code"    => 200,
    *        "headers" => array(
    *                            "Content-Type" => "text/plain",
    *                            "X-Powered-By" => "PEAR"
    *                          ),
    *        "body"    => "This is the actual response"
    *      )
    *
    * @access public
    * @param  integer $clientId   id of the client that send the headers
    * @param  array   $headers    request headers
    * @return mixed   $response   array containing the response or false if the method handles the response
    * @see    GET()
    */
    function POST($clientId, $headers)
    {
    }

   /**
    * Handler for invalid requests
    *
    * Implement this in your class.
    *
    * this method should return an array of the following format:
    *
    * array(
    *        "code"    => 200,
    *        "headers" => array(
    *                            "Content-Type" => "text/plain",
    *                            "X-Powered-By" => "PEAR"
    *                          ),
    *        "body"    => "This is the actual response"
    *      )
    *
    * @access   public
    * @param    integer     client id
    * @param    string      request data
    */
    function handleBadRequest($clientId, $data)
    {
        return array(
                        'code' => 405
                    );
    }

   /**
    * resolve a status code
    *
    * @access private
    * @param integer $code status code
    * @return string $status http status
    */
    function _resolveStatusCode($code)
    {
        if (!isset($this->_statusCodes[$code])) {
            return false;
        }
        return $this->_statusCodes[$code];
    }
}
?>