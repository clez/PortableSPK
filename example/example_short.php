<?php
/** 
 * Portable SPK Source - short example
 *
 * @category   PortableSPK
 * @package    example
 * @license    http://php.net/license/	PHP License
 * @link       http://spk.unzureichende.info/
 * @see        https://github.com/clez/PortableSPK
 */
// vim:fdm=marker:nowrap:ts=4 

require('../PortableSPK.class.php');
$PortableSPK = new PortableSPK('./');
//$PortableSPK->set('debug',false);
//$PortableSPK->stack(new PortableSPK('./another/'));

/**
 * synology getPackages request
 */
if(isset($_REQUEST['arch'],$_REQUEST['build']) ) {
	die($PortableSPK);			// PortableSPK()->__toString() = syno response
}

/**
 * website for browsers
 */
echo "<html><head><title>".htmlspecialchars($PortableSPK->public_name)."</title></head>";
echo "<body><h1>".htmlspecialchars($PortableSPK->public_name)."</h1>";
foreach($PortableSPK as $file=>$package) {	// Package Iterator
	echo "<img style=\"float:left\" src=\"data:image/png;base64,{$package['icon']}\"/>";
	echo "<a href=\"{$package['link']}\">{$package['dname']}</a> {$package['version']}<br/>";
	echo implode(' ',$package['arch']).'<br/>'.$package['md5'];
	echo '<hr style="clear:both"/>';
}
echo '</body></html>';

// EOF
