<?php
/** 
 * Portable SPK Source - synology package source class
 *
 * $_REQUEST:
 * 	 bool      ?debug        see INFO parser details
 * 	 string    ?arch=        use ?arch=<arch> for a syno-like Response 
 * 	 string    ?language=    [chs|cht|enu|fre|ger|...] 
 * 	 int       ?build=       DSM build 
 *
 * @category   PortableSPK
 * @package    common
 * @author     <passeriform@gmail.com>
 * @license    http://php.net/license/	PHP License
 * @version    2013-03-06 / $Id$
 * @see        http://spk.unzureichende.info/
 * @since      2013-03-06
 *
 * vim:fdm=marker:nowrap:ts=4 
 */
Class PortableSPK implements IteratorAggregate 
{
	/* {{{ member vars, public config store */
	/**
	 * language for desc_ and dname_
	 * possible values: DSM language names
	 */
	public	$language = 'enu';

	/**
	 * example: http://yourhost.example.com/files/packages.php
	 * determined automatically if not set
	 */
	public	$public_url = null;

	/**
	 * example: "Rudis Repo"
	 * possible values: A-Za-z0-9 and spaces
	 */
	public	$public_name = null;

	/**
	 * SPK INFO caching
	 * possible values: (bool) true|false
	 */
	public	$cache_enabled = false;

	/**
	 * path to store package info
	 * directory needs to be writable (atomic mv swap on update)
	 */
	public	$cache_path = '/tmp/portablespk.ser';

	/**
	 * debug mode: no cache + verbose info
	 * possible values: (bool) true|false
	 */
	public	$debug = false;

	/**
	 * internal package info storage
	 */
	private	$_packages = false;

	/**
	 * base directory used for downloads
	 * determinded automatically
	 */
	public	$public_base = null;
	/* }}} */

	/* {{{ function __construct($path = './')						- use relative SPK folder offset
	 * @param	string	$path	optional relative path to SPK files
	 * @return	object		self
	 */
	public function __construct($path = './') {
		$this->public_url = 'http://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT'] != 80 ? ':'.$_SERVER['SERVER_PORT']:'')
				.str_replace('index.php','',strtolower($_SERVER['PHP_SELF']));
		preg_match('%^(https?\://.*/)[^/]*$%i',$this->public_url,$m);
		$this->public_base = $m[1];
		$this->public_name = 'Portable SPK index of :'.dirname($_SERVER['PHP_SELF']).'/';
		$this->language = isset($_REQUEST['language']) ? $_REQUEST['language'] : $this->language;
		$this->path = $path;
		return $this;
	}
	/* }}} */

	/* {{{ function set($config,$value = null)						- set configuration value(s)
	 *
	 * @param	mixed	$config	(string) field or (array) field=>value
	 * @param	mixed	$value	optional value if $config is string
	 * @return	object		self
	 */
	public function set($config,$value = null) {
		if(is_array($config)) {
			foreach($config as $name=>$value) {
				$this->$name = $value;
			}
		} elseif($value != null) {
			$this->$config = $value;
		}
		if(!preg_match('%^(https?\://.*/)[^/]*$%i',$this->public_url,$m)) {
			$this->debugLog('invalid public_url <span>'.$this->public_url.'</span>');
			return false;
		}
		$this->public_base = $m[1];
		return $this;
	}
	/* }}} */

	/* {{{ function debugLog($text)									- collect/output debug messages
	 * @param	string	$text	message (maybe HTML formatted)
	 * @void
	 */
	private function debugLog($text) {
		if($this->debug) {
			//error_log(date('Ymd His').' '.strip_tags($text)."\n",3,'/tmp/portablespk.log');
			printf("<pre>%s</pre>\n",$text);
		}
	}
	/* }}} */

	/* {{{ function getIterator()									- implements IteratorAggregate
	 * @return	object		ArrayIterator($this->_packages)
	 */
	public function getIterator() {
		$this->_packages = $this->_packages ? $this->_packages : $this->get();
		return new ArrayIterator($this->_packages);
	}
	/* }}} */

	/* {{{ function __toString()									- synology package response
	 * @return	string		JSON getPackages response
	 */
	public function __toString() {
		$this->_packages = $this->get();
		$response = array();					// Synology Package Manager Response
		if(isset($_REQUEST['arch'])) {
			$arch = strtolower($_REQUEST['arch']);
			$build = isset($_REQUEST['build']) ? (int) $_REQUEST['build'] : INF;
			foreach($this->_packages as $i=>$p) {
				if( (in_array($arch, $p['arch']) || $p['arch'][0] == 'noarch')		// arch
					&& $p['firmware'] <= $build					// firmware min version
				) {
					$p['desc'] = isset($p['desc_'.$this->language]) ? $p['desc_'.$this->language] : $p['desc'];
					$p['dname'] = isset($p['dname_'.$this->language]) ? $p['dname_'.$this->language] : $p['dname'];
					unset($p['arch'],$p['mtime'],$p['firmware']);
					$response[] = $p;
				}
			}	
		}
		return json_encode($response);
	}
	/* }}} */

	/* {{{ function get()											- fetch PHP array of packages (json-named contents)
	 * @return	array		filename=>package description (ouput indices)
	 */
	public function get() {
		if($this->_packages) {
			return $this->_packages;
		}
		if($this->cache_enabled && file_exists($this->cache_path) &&
			filemtime($this->cache_path) < filemtime($this->path) && !$this->debug) {
			return $this->_packages = unserialize(file_get_contents($this->cache_path));
		}
		return $this->deDuplicate($this->_packages = $this->addFolder());
	}
	/* }}} */

	/* {{{ function deDuplicate($in = false)						- find newer versions in case of conflict
	 * @param	array	$in	optional _packages array
	 * @return	array		filename=>package description (ouput indices) - current versions only
	 */
	public function deDuplicate($in = false) {
		$in = !$in ? $this_>_packages : $in;
		$parch = array();
		foreach($in as $file=>$p) {
			foreach($p['arch'] as $a) {
				if(isset($parch[$p['package']][$a])) {
					if(version_compare($parch[$p['package']][$a][1],$p['version'],'<')) {
						$this->debugLog('discarding old version <span>'.$parch[$p['package']][$a][0].'</span> arch <b>'.$a.'</b>');
						unset($in[$parch[$p['package']][$a][0]], $parch[$p['package']][$a]);
						$parch[$p['package']][$a] = array($file,$p['version']);
					}  else {
						$this->debugLog('discarding old version <span>'.$file.'</span> arch <b>'.$a.'</b>');
					}
				} else {
					$parch[$p['package']][$a] = array($file,$p['version']);
				}
			}
		}
		return $this->_packages = $in;
	}
	/* }}} */

	/* {{{ function tarInfo($file, $gzip = false)					- extract INFO from .spk
	 * @param	string	$file	package filename
	 * @param	bool	$gzip	package is gzipped (default = retry)
	 */
	private function tarInfo($file, $gzip = false) {
		if(!$f = $gzip ? gzopen($file,'r') : fopen($file,'r')) {
			return false;
		}
		$i = 0;
		$blocksize = 512;
		while($h = unpack ('a100name/a8/a8/a8/a12size/a12/a8/a1/a100/a6/a2/a32/a32/a8/a8/a155/a12', fread($f,$blocksize)) ) {
			if(!$h || $h['name'] == '' || $i++ > 99) {
				break;
			}
			$s = octdec($h['size']);
			$pad = $s > 0 ? ($s-$s%$blocksize)+$blocksize : 0;
			$return['list'][$h['name']] = $s;
			if($h['name'] == 'INFO') {
				$return['INFO'] = substr(fread($f,$pad),0,$s);
			} elseif($h['name'] == 'PACKAGE_ICON.PNG') {		// fallback if not included in INFO
				$return['icon'] = base64_encode(substr(fread($f,$pad),0,$s));
			} else {
				fseek($f,$pad,SEEK_CUR);
			}
		}
		if($gzip) {
			gzclose($f);
		} else {
			fclose($f);
		}
		if(!$gzip && !isset($return['INFO'])) {
			$this->debugLog('<span>'.$file.'</span> is no tar archive');
			$return = $this->tarInfo($file,true);
			if(isset($return['INFO'])) {
				$this->debugLog('<span>'.$file.'</span> is a tar.gz archive, using transparent deflate');
			}
		}
		return isset($return['INFO']) ? $return : false;
	}
	/* }}} */

	/* {{{ function addFile($file)									- add spk package
	 * extract INFO and map names for JSON outout
	 *
	 * @param	string	$file	local filename
	 * @param	string	$path	optional relative prefix
	 * @return	mixed		(array) or (bool) false on error
	 */
	private function addFile($file,$path = false) {
		$path = !$path ? $this->path : $path;
		$filename = $path.$file;
		if(!$tarinfo = $this->tarInfo($filename)) {
			$this->debugLog('<br/><span>'.$file.'</span> could not be parsed');
			return false;
		}
		$info = $tarinfo['INFO'];
		$this->debugLog('processing <b>'.$file.'</b>, INFO size: '.strlen($info));
		$mandatory = array(					// INFO fields you want to enforce
			'package'		=>	'package',
			'version'		=>	'version',
			'displayname'		=>	'dname',
			'description'		=>	'desc',
			'package_icon'		=>	'icon',
			'arch'			=>	'arch');	// arch used internally
		$mapped = array(					// INFO fields to keep 1:1
			'maintainer','changelog','distributor',
			'maintainer_url','distributor_url','category');
		$ignored = array('dsmappname','dsmuidir','helpurl','reloadui');	// INFO to ignore
		$json = array(						// fill package defaults
			'package'		=>	basename($filename),
			'version'		=>	'0.1-1',
			'desc'			=>	'',
			'arch'			=>	array('noarch'),
			'firmware'		=>	0,
			'dname'			=>	basename($filename),
			'size'			=>	filesize($filename),
			'md5'			=>	md5_file($filename),
			'mtime'			=>	filemtime($filename),
			'link'			=>	$this->public_base.substr($filename,2),
			'distributor'		=>	strip_tags($this->public_name),
			'distributor_url'	=>	$this->public_url,
			'qinst'			=>	isset($tarinfo['list']['WIZARD_UIFILES/install_uifile']),
			'depsers'		=>	null,
			'deppkgs'		=>	null,
			'thirdparty'		=>	true,
			'start'			=>	false);		// set later
		$this->debugLog('  <b>'.$file.'</b> detected WIZARD_UIFILES, setting qinst:false',$json['qinst']);
		foreach(explode("\n",$info) as $line) {
			@list($field,$content) = explode('=',trim($line),2);
			if($field == 'arch') {				// preserve arch for later filters
				$json['arch'] = strpos($inner = strtolower(trim($content,"\r\n\t\"' ")),' ') === false ? array($inner) : explode(" ",$inner);
				unset($mandatory[$field]);
			} elseif($field == 'report_url'){		// report_url means beta
				$json['beta'] = true;
			} elseif(preg_match('%^description_([a-z]{3})$%',$field,$m)) {	// desc_<lng> versions
				$this->debugLog('  <b>'.$file.'</b> has language description <b>'.$m[1].'</b>');
				$json['desc_'.$m[1]] = trim($content,"\r\n\t\"' ");
			} elseif(preg_match('%^displayname_([a-z]{3})$%',$field,$m)) {	// dname_<lng> versions
				$this->debugLog('  <b>'.$file.'</b> has language displayname <b>'.$m[1].'</b>');
				$json['dname_'.$m[1]] = trim($content,"\r\n\t\"' ");
			} elseif($field == 'firmware' && preg_match('%[0-9\.]+-([0-9]+)"%',$content,$m)) {	// only parse build number
				$this->debugLog('  <b>'.$file.'</b> needs firmware <b>'.$m[1].'</b>');
				$json['firmware'] = $m[1];
			} elseif($field == 'startable'){				// startable yes/no -> (bool) start
				$json['start'] = strtolower(trim($content)) == '"yes"';
			} elseif($field == 'thirdparty'){
				$json['thirdparty'] = strtolower(trim($content)) == '"yes"';
			} elseif(isset($mandatory[$field])) {				// mandatory
				$json[$mandatory[$field]] = trim($content,"\r\n\t\"' ");
				unset($mandatory[$field]);
			} elseif(in_array($field,$mapped)) {				// mapped
				$json[$field] = trim($content,"\r\n\t\"' ");
			} elseif($field && !in_array($field,$ignored)) {		// errors for ?debug users
				$this->debugLog('  <b>'.$file.'</b> unknown INFO: <b>'.$field.'</b>');
			}
		}
		if(!isset($json['icon']) && isset($tarinfo['icon'])) {
			$this->debugLog('  <b>'.$file.'</b> has no "package_icon", using content of PACKAGE_ICON.PNG</b>');
			$json['icon'] = $tarinfo['icon'];
			unset($mandatory['package_icon']);
		}
		if(count($mandatory) > 0) {
			$this->debugLog('  <span>'.$file.'</span> missing mandatory: <b>'.implode(', ',array_keys($mandatory)).'</b>'."\nINFO:\n".$info."\n");
			return false;
		}
		$json['snapshots'] = $this->addSnapshot($path,$file,$json['package']);
		return $json;
	}
	/* }}} */

	/* {{{ function addSnapshot()									- search for /pkg_img/packageid_0.jpg [0..3]
	 * @return	array		snapshot paths / empty
	 */
	private function addSnapshot($path,$file,$spkid) {
		for($i=0;$i<4 && file_exists($path.'pkg_img/'.($i2=($spkid.'_'.$i.'.jpg')));$i++) {
			$return[] = $this->public_url.'pkg_img/'.$i2;
			$this->debugLog(' <b>'.$file.'</b> has snapshot <b>'.$i2.'</b>');
		}
		return $return;
	}
	/* }}} */

	/* {{{ function addFolder($path) 								- search folder for packages && add
	 * regenerate_cache - grab all information from packages
	 *
	 * @param	string	$path		optional relative path
	 * @return	array	$packages
	 */
	function addFolder($path = false) {
		$path = !$path ? $this->path : $path;
		if($dh=opendir($path)) {
			while (($file = readdir($dh)) !== false) {
				if(preg_match('%^.*\.spk$%i',$file) 
					&& (!isset($packages[$file]) || $packages[$file]['mtime'] != filemtime($path.$file) || $this->debug) 
					&& ($parsed = $this->addFile($file,$path))) {
					$packages[$file] = $parsed;
				}
			}
			closedir($dh);
		}
		ksort($packages);
		if($this->cache_enabled && ($this->debug || count($packages) > 0)) {
			$swap = $this->cache_path.'.swp';
			if(file_put_contents($swap,serialize($packages))) {
				rename($swap,$this->cache_path);
			} else {
				$this->debugLog('<span>cache path not writable</span>');
			}
		}
		return $packages;
	}
	/* }}} */

	/* {{{ function stack(PortableSPK $spk)							- merge instances
	 * @param	mixed	$spk	object or array or objects
	 * @return	object		self
	 */
	public function stack(PortableSPK $spk) {
		$spk = !is_array($spk) ? array($spk) : $spk;
		foreach($spk as $source) {
			$p = array_merge($this->get(),$source->get());
			ksort($p);
			$this->deDuplicate($p);
		}
		return $this;
	}
	/* }}} */
} /* class*/

// EOF

