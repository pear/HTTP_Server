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
 * This is a basic example of how to use HTTP_Server to build
 * a custom webserver.
 * 
 * @category    HTTP
 * @package     HTTP_Server
 * @subpackage  Examples
 * @author      Stephan Schmidt <schst@php-tools.net>
 */

require_once 'HTTP/Server.php';

/**
 * This is a basic example of how to use HTTP_Server to build
 * a custom webserver.
 * 
 * @category    HTTP
 * @package     HTTP_Server
 * @subpackage  Examples
 * @author      Stephan Schmidt <schst@php-tools.net>
 */
class HTTP_Server_File extends HTTP_Server {

   /**
    * document root
    * @var string $documentRoot
    */
    var $documentRoot   =   ".";

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
    function GET($clientId, &$request)
    {
        $path_info       = $request->getPathInfo();
        $path_translated = $this->documentRoot . $path_info;

        $headers = array();
        
        //  path does not exist
        if (!file_exists($path_translated)) {
            return array(
                            "code" => 404
                        );
        }

        // path is a directory
        if (is_dir($path_translated)) {
            $body =  '<html>'.
                     ' <head><title>Directory listing of ' . $path_info .'</title></head>'.
                     ' <body>'.
                     '  <h1>Directory listing of '.$path_info.'</h1>'.
                     '  <hr />';
            $files = $this->_getFilesInDir($path_translated);
            if ($path_info != "/") {
                $path_info = $path_info."/";
            }
            foreach ($files as $file) {
                $body .= sprintf("<a href=\"%s%s\">%s</a><br/>",$path_info,$file,$file);
            }
            $body .= ' </body>'.
                     '</html>';
        }
        // path is a file
        elseif (is_readable($path_translated) ) {
            $body = fopen($path_translated, "rb");
        }
        else
        {
            return array(
                            "code" => 403
                        );
        }

        return array(
                        "code"    => 200,
                        "headers" => $headers,
                        "body"    => $body
                    );
    }

   /**
    * read all files in a directory
    *
    * @param string $dir directory
    * @return array $files
    */
    function _getFilesInDir($dir)
    {
        $files = array();
        if (!is_dir($dir)) {
            return $files;
        }
        
        $dh = dir( $dir );
        while ($entry = $dh->read()) {
            if ($entry == "." || $entry == "..") {
                continue;
            }
            if (is_readable($dir."/".$entry)) {
                array_push($files,$entry);
            }
        }
        return $files;
    }
}
    //  instantiate the server
    $myServer = &new HTTP_Server_File('localhost',80);

    /**
     * use this on machines that do not have ext/pcntl compiled
     */
//    $myServer = &new HTTP_Server_File('localhost',80,'Sequential');

    $myServer->_driver->setDebugMode(true);
    $myServer->documentRoot = dirname( __FILE__ ) . '/www';
    $myServer->start();
?>