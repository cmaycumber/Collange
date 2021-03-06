<?php
class Image extends DBObject {
    protected static $tableName='image';    // DB Table is `user`
    protected static $tablePKName='id';     // DB Primary Key field is `id`
    protected static $tablePKType='i';      // DB Primary Key is an integer.

    protected $ownerId;
    protected $fileName;
    protected $caption;
    protected $size;
    protected $shared;
    protected $createdDate;
    protected $uuid;
    protected $ext;

    public function __construct($uuid, $ownerId, $fileName, $caption, $size, $shared, $ext, $createdDate=null)
    {
        if($createdDate == null){
            $createdDate = time();
        }
        $this->ownerId = $ownerId;
        $this->uuid = $uuid;
        $this->fileName = $fileName;
        $this->caption = $caption;
        $this->size = $size;
        $this->shared = $shared;
        $this->createdDate = $createdDate;
        $this->ext = $ext;
    }

    public function getKey(){
        return $this->uuid .'.' . $this->ext;
    }

    public function getId(){
        return $this->id;
    }

    public function setId($id){
        $this->id = $id;
    }

    public function getOwnerId(){
        return $this->ownerId;
    }

    public function getFileName(){
        return $this->fileName;
    }

    public function toggleShared(){
        if($this->shared){
            $this->shared = 0;
        }else{
            $this->shared = 1;
        }
    }

    public static function get($x, $y=null){
        return parent::get($x, $y);
    }

    public static function getAll($x, $y=null){
        return parent::getAll($x, $y);
    }
}
?>