<?php
namespace IO;
use \TymFrontiers\MySQLDatabase,
    \TymFrontiers\Generic,
    \TymFrontiers\InstanceError,
    \TymFrontiers\Data;

class File{
  use \TymFrontiers\Helper\MySQLDatabaseObject,
      \TymFrontiers\Helper\Pagination,
      File\Data,
      File\Uploader,
      File\Downloader,
      File\PhotoEditor,
      File\Writer,
      File\Reader{
    		File\Uploader::save insteadof \TymFrontiers\Helper\MySQLDatabaseObject;
    	}

  protected static $_primary_key = 'id';
  protected static $_db_name;
  protected static $_table_name = "file_meta";
  protected static $_prop_type = [];
  protected static $_prop_size = [];
  protected static $_db_fields=[
    'id',
    '_locked',
    '_checksum',
    '_watermarked',
    'owner',
    'privacy',
    'caption',
    'nice_name',
    'type_group',
    '_path',
    '_name',
    '_size',
    '_type',
    '_creator',
    '_updated',
    '_created'
  ];

  public $id;
  private $_locked = false;
  private $_checksum = NULL;
  private $_watermarked = false;
	public $owner;
  public $privacy = 'PUBLIC';
	public $caption;
	public $nice_name;
	public $type_group;

  protected $_path;
	protected $_name;
	protected $_size;
	protected $_type;

	private $_creator;
	private $_updated;
	private $_created;

	public $errors = []; # follows Tym Error system

  function __construct($conn = false){
    if (!self::$_db_name = get_database(get_constant("PRJ_SERVER_NAME"), "file")) throw new \Exception("No database-settings found for [file].", 1);
    global $database;
    $conn = ($conn && $conn instanceof MySQLDatabase) ? $conn : ( $database && $database instanceof MySQLDatabase ? $database : false);
    $conn = query_conn(get_constant("PRJ_SERVER_NAME"), $conn);
    self::_setConn($conn);
  }
  public function load($filename, bool $mkdir = false, $save_path = ""){
    if ($mkdir && !\is_int($filename)) {
      if( !\file_exists($filename) ){
        \mkdir($filename,0777,true);
      }
    }
    if( \is_dir($filename) ){
      // init directory
      $this->_path = $filename;
    }elseif( \is_file($filename) ){
      // init file
      $file = \pathinfo($filename);
      if( !\array_key_exists($file['extension'],$this->mime_types) && !\in_array( \mime_content_type($filename),$this->mime_types ) ){
        throw new \Exception("Unknown file type given", 1);
      }
      $this->_path = empty($save_path) ? $file['dirname'] : $save_path;
      $this->_name = $file['basename'];
      $this->_size = filesize($filename);
      $this->_type =  \array_key_exists($file['extension'],$this->mime_types) ?  $this->mime_types[$file['extension']] : \mime_content_type($filename);
      $this->type_group = $this->groupName();
      $this->nice_name = $file['filename'];
    }elseif( \is_int($filename) ){
      $file = self::findById( (int)$filename );
      if( !$file ){
        throw new \Exception("No file was found with given ID: [{$filename}]", 1);
      }
      foreach ($file as $key => $value) {
        $this->$key = $value;
      }
    }elseif( \filter_var($filename,FILTER_VALIDATE_URL) ){
      throw new \Exception("Loading file via URL is not yet supported", 1);
      // download file
      //
    }else{
      throw new \Exception("File/path: '{$filename}' is invalid or does not exist", 1);
    }
  }
  public function setPath(string $path) {
    $this->_path = $path;
  }
  public function type($ext=''){
    return !empty($ext) && \array_key_exists(strtolower($ext),$this->mime_types) ? $this->mime_types[strtolower($ext)] : (
      !empty($this->_type) ? $this->_type : 'unknown/unknown'
      );
  }
  public function groupName( string $mime = "") {
    $mime = !empty($mime) ? $mime : $this->_type;
    return !\array_key_exists($mime, $this->mime_group)
      ? NULL
      : $this->mime_group[$mime];
  }
	public function mimeType(){ return $this->_type; }
	public function name(string $name=''){
    if( $name ) $this->_name = $name;
    return $this->_name;
  }
	public function path(string $path=''){
    return $this->_path;
  }
	public function size(int $size=0){
    return $this->_size;
  }
	public function url(){
    global $_SERVER;
    return "/app/file/{$this->_name}";
  }
	public function thumbUrl(){
    global $_SERVER;
    return "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}/file/thumb-{$this->_name}";
  }
	public function fullPath(){ 
    return get_constant("PRJ_DEVROOT") . "{$this->_path}/{$this->_name}"; 
  }
	public function create(){ return $this->_create();}
  public function destroy() {
    try {
      setting_unset_file_default($this->id);
      \unlink( $this->fullPath() );
      $this->delete();
    } catch ( \Exception $e) {
      $this->errors['destroy'][] = [0,256,"Failed to delete/destroy file, due to error: {$e->getMessage()}",__FILE__,__LINE__];
      return false;
    }
    return true;
  }
  // public function delete(){ return $this->destroy(); }

  public function sizeAsText() {
    if($this->size < 1024) {
      return "{$this->size} bytes";
    } elseif($this->size < 1048576) {
      $size_kb = \round($this->size/1024);
      return "{$size_kb} KB";
    } else {
      $size_mb = \round($this->size/1048576, 1);
      return "{$size_mb} MB";
    }
  }
  public function dbname(){ return static::$_db_name; }
  public function tblname(){ return static::$_table_name; }
  public function update(){
    if ((bool)$this->_locked) {
      $this->errors['update'][] = [0,256, "File is locked and can not be updated",__FILE__, __LINE__];
      return false;
    }
    return !empty($this->id) ? $this->_update() : false;
  }
  public function checksum() { return $this->_checksum; }
  public function locked() { return $this->_locked; }
  public function watermarked() { return $this->_watermarked; }
  public function creator() { return $this->_creator; }
  public function lock() {
    // calculate and save checksum
    $this->_checksum = \hash_file("sha512", $this->fullPath(), false);
    $this->_locked = true;
    return $this->_update();
  }

}
