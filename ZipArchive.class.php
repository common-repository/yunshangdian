<?php
require_once ABSPATH.'wp-admin/includes/class-pclzip.php';
class ZipArchive{
	const CREATE=1;
	private $path;
	private $options=array();
	public function open($path,$const){
		$this->path=$path;
		return true;
	}
	public function addFromString($path,$content){
		$this->options[]=array(
			PCLZIP_ATT_FILE_NAME=>$path,
			PCLZIP_ATT_FILE_CONTENT=>$content
		);
	}
	public function addFile($from,$to){
		$this->options[]=array(
			PCLZIP_ATT_FILE_NAME=>$from,
			PCLZIP_ATT_FILE_NEW_FULL_NAME=>$to 
		);
	}
	public function close(){
		$archive = new PclZip($this->path);
		$archive->create($this->options);
	}
}
