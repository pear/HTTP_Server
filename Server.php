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

require_once 'PEAR.php';
require_once 'Net/Server.php';
require_once 'HTTP.php';

/**
 * HTTP_Server
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
 * @version 0.2b
 * @author  Stephan Schmidt <schst@php-tools.de>
 */
class HTTP_Server extends Net_Server {

   /**
    * end character for socket_read
    * @var    integer    $readEndCharacter
    */
    var $readEndCharacter = "\r\n\r\n";

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
                                            'Server'     => 'PEAR HTTP_Server/0.2',
                                            'Connection' => 'Close'
                                        );

    
   /**
    * data was received, i.e. HTTP request sent
    * 
    * @param    integer   $clientId    socket that sent the request
    * @param    string    $data        raw request data
    */
    function onReceiveData($clientId, $data)
    {
        //    parse request headers
        $request = $this->_parseRequest($data);

        $this->_serveRequest($clientId, $request);
        
        //    close the connection
        $this->closeConnection($clientId);
    }

   /**
    * serve a request
    *
    * @access private
    * @param  array $headers  request headers
    */
    function _serveRequest($clientId, $headers)
    {
        if (method_exists($this, $headers["method"])) {
            $response = $this->$headers["method"]($clientId, $headers);
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
            $repsonse = array();
            $response["code"] = 500;
        }


        //  no status code => assume 200
        if (!isset($response["code"])) {
            $response["code"] = 200;
        }
        
        // resolve the status code
        $response["code_translated"] = $this->_resolveStatusCode($response["code"]);

        //  send the response code
        $this->sendData($clientId, sprintf("HTTP/1.x %s %s\r\n", $response["code"], $response["code_translated"]));

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
            if (!isset($respose["headers"]["Content-Length"])) {
                $response["headers"]["Content-Length"] = strlen($response["body"]);
            }
        }

        //  send the headers            
        foreach($response["headers"] as $header => $value) {
                $this->sendData($clientId, sprintf("%s: %s\r\n", $header, $value));
        }

        //  send the response body
        if (isset($response["body"])) {
            $this->sendData($clientId, "\r\n\r\n");
            if (is_string($response["body"])) {
                $this->sendData($clientId, $response["body"]);
            }
            elseif (is_resource($response["body"])) {
                while (!feof($response["body"])) {
                    $this->sendData($clientId, fread($response["body"], 4096));
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
    * parse a http request
    *
    * @access    public
    * @param    string    $request    raw request data
    * @return    array    $request    parsed request
    */
    function _parseRequest($request)
    {
        //    split lines
        $request = explode ("\r\n", $request);

        //    check for method, uri and protocol in line 1
        $regs = array();
        if (!preg_match("'([^ ]+) ([^ ]+) (HTTP/[^ ]+)'", $request[0], $regs))
            return false;

        $parsed = array(
                         "method"   => $regs[1],
                         "uri"      => $regs[2],
                         "protocol" => $regs[3]
                      );

        //    parse the uri    
        if ($tmp = $this->_parsePath($regs[2])) {
            $parsed["path_info"]       = $tmp["path_info"];
            $parsed["query_string"]    = $tmp["query_string"];
        }
    
        //    parse and store additional headers (not needed, but nice to have)
        for ($i = 1; $i < count($request); $i++) {
            $regs    =    array();
            if (preg_match("'([^: ]+): (.+)'", $request[$i], $regs)) {
                $parsed[(strtolower($regs[1]))]    =    $regs[2];
            }
        }
        return    $parsed;
    }

   /**
    * parse a request uri
    *
    * @access    public
    * @param    string    $path    uri to parse
    * @return    array    $path    path data
    */
    function _parsePath($path)
    {
        $regs = array();
        if (!preg_match("'([^?]*)(?:\?([^#]*))?(?:#.*)? *'", $path, $regs)) {
            return false;
        }

        return array(
                      "path_info"    => $regs[1],
                      "query_string" => $regs[2]
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