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
 * HTTP_Server_Request
 *
 * Interface that parses the request
 *
 * @author  Stephan Schmidt <schst@php-tools.de>
 */
class HTTP_Server_Request {

   /**
    * method
    *
    * @access   public
    * @var      string
    */
    var $method;

   /**
    * protocol
    *
    * @access   public
    * @var      string
    */
    var $protocol;

   /**
    * uri
    *
    * @access   public
    * @var      string
    */
    var $uri;

   /**
    * path info
    *
    * @access   public
    * @var      string
    */
    var $path_info;

   /**
    * query string
    *
    * @access   public
    * @var      string
    */
    var $query_string;

   /**
    * headers
    *
    * @access   public
    * @var      array
    */
    var $headers    =   array();
    
    /**
    *   the data (like POST) sent after the headers
    *   @access public
    *   @var    string
    */
    var $content    = '';

   /**
    * parse a http request
    *
    * @access    public
    * @static
    * @param     string    $request    raw request data
    * @return    array     $request    parsed request
    */
    function &parse($request)
    {
        //    split lines
        $lines = explode ("\r\n", $request);

        //    check for method, uri and protocol in line 1
        $regs = array();
        if (!preg_match("'([^ ]+) ([^ ]+) (HTTP/[^ ]+)'", $lines[0], $regs)) {
            return false;
        }

        $request = &new HTTP_Server_Request();
        
        $request->method   = $regs[1];
        $request->uri      = $regs[2];
        $request->protocol = $regs[3];
            
        //    parse the uri    
        if ($tmp = HTTP_Server_Request::_parsePath($regs[2])) {
            $request->path_info    = $tmp['path_info'];
            $request->query_string = $tmp['query_string'];
        }
    
        //    parse and store additional headers (not needed, but nice to have)
        /**
        * FIXME: 
        *   HTTP1.1 allows headers be broken on several lines if the next line begins with
        *   a space or a tab (rfc2616#2.2)
        */
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) == '') {
                //empty line, after this the content should follow
                $i++;
                break;
            }
            $regs    =    array();
            if (preg_match("'([^: ]+): (.+)'", $lines[$i], $regs)) {
                $request->headers[(strtolower($regs[1]))]    =    $regs[2];
            }
        }
        //aggregate the content (POST data or so)
        $request->content = '';
        for ($i = $i; $i < count($lines); $i++) {
            $request->content .= $lines[$i] . "\r\n";
        }
        
        return $request;
    }

   /**
    * get the request method
    *
    * @access   public
    * @return   string
    */
    function getMethod()
    {
        return $this->method;
    }
    
   /**
    * get path info
    *
    * @access   public
    * @return   string
    */
    function getPathInfo()
    {
        return $this->path_info;
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
                      'path_info'    => $regs[1],
                      'query_string' => isset($regs[2]) ? $regs[2] : null
                  );
    }
    
   /**
    *   Exports server variables based on request data
    *   like _GET, _SERVER[HTTP_*] and so
    *   The function can be used to make your own
    *   HTTP server act more than a "real" one (like apache)
    *
    *   @access public
    */
    function export() 
    {
        //_SERVER[HTTP_*] from headers
        foreach ($this->headers as $strId => $strValue) {
            $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($strId))] = $strValue;
        }
        
        $nPos = strpos($this->headers['host'], ':');
        if ($nPos !== false) {
            $_SERVER['HTTP_HOST']   = substr( $this->headers['host'], 0, $nPos);
            $_SERVER['SERVER_PORT'] = substr( $this->headers['host'], $nPos);
        } else {
            $_SERVER['SERVER_PORT'] = 80;
        }
        
        $_SERVER['QUERY_STRING']    = $this->query_string;
        $_SERVER['SERVER_PROTOCOL'] = $this->protocol;
        $_SERVER['REQUEST_METHOD']  = $this->method;
        $_SERVER['REQUEST_URI']     = $this->uri;
        
        //GET variables
        parse_str($this->query_string, $_GET);
        if (count($_GET) == 0) {
            $_SERVER['argc'] = 0;
            $_SERVER['argv'] = array();
        } else {
            $_SERVER['argc'] = 1;
            $_SERVER['argv'] = array($this->query_string);
        }
        
        //@todo: POST, COOKIE, FILES, SESSION,....
    }
}
?>