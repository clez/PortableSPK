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
//$PortableSPK->set('public_name','My Packages');
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
echo '<html><head><title>'.htmlspecialchars($PortableSPK->public_name).'</title></head>';
echo '<body><h1>'.htmlspecialchars($PortableSPK->public_name).'</h1>';
foreach($PortableSPK as $file=>$package) {	// Package Iterator
	printf('<img style="float:left" src="data:image/png;base64,%s/>
	<a href="%s">%s</a> %s<br/>%s<br/>%s<hr style="clear:both"/>',
	$package['icon'], $package['link'],$package['dname'],$package['version'],
	implode(' ',$package['arch']),$package['md5']);
}
echo '</body></html>';

// EOF
