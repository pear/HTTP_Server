<?php
/**
 * script to automate the generation of the
 * package.xml file.
 *
 * $Id$
 *
 * @author      Stephan Schmidt <schst@php-tools.net>
 * @package     HTTP_Server
 * @subpackage  Tools
 */

/**
 * uses PackageFileManager
 */ 
require_once 'PEAR/PackageFileManager.php';

/**
 * current version
 */
$version = '0.4.0';

/**
 * current state
 */
$state = 'alpha';

/**
 * release notes
 */
$notes = <<<EOT
- fixed Bug #2531 (server shutdown on bad request) (schst)
- fixed Bug #2762 Request.php::_parsePath() E_ALL warning (patch by cox)
- fixed some small bugs in _serveRequest() (schst)
- Added support for POST data (cweiske)
- Added an export() function to export _SERVER variables (cweiske)
- start() now returns the PEAR_Error object returned from Net_Server (schst)
EOT;

/**
 * package description
 */
$description = <<<EOT
HTTP server class that allows you to easily implement HTTP servers by supplying callbacks.
The base class will parse the request, call the appropriate callback and build a repsonse
based on an array that the callbacks have to return.
EOT;

$package = new PEAR_PackageFileManager();

$result = $package->setOptions(array(
    'package'           => 'HTTP_Server',
    'summary'           => 'HTTP server class.',
    'description'       => $description,
    'version'           => $version,
    'state'             => $state,
    'license'           => 'PHP License',
    'filelistgenerator' => 'cvs',
    'ignore'            => array('package.php', 'package.xml'),
    'notes'             => $notes,
    'simpleoutput'      => true,
    'baseinstalldir'    => 'HTTP',
    'packagedirectory'  => './',
    'dir_roles'         => array('docs' => 'doc',
                                 'examples' => 'doc',
                                 'tests' => 'test',
                                 )
    ));

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}

$package->addMaintainer('schst', 'lead', 'Stephan Schmidt', 'schst@php.net');
$package->addMaintainer('cweiske', 'developer', 'Christian Weiske', 'cweiske@php.net');

$package->addDependency('PEAR', '', 'has', 'pkg', false);
$package->addDependency('HTTP', '', 'has', 'pkg', false);
$package->addDependency('Net_Server', '0.12.0', 'ge', 'pkg', false);
$package->addDependency('php', '4.2.0', 'ge', 'php', false);

if (isset($_GET['make']) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}
?>