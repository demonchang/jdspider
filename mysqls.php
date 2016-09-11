<?php
class Mysqlis{
	private static $conn;

	public function __construct($host='127.0.0.1', $user='root', $password='123456', $dbname='jd'){
		$connection = new mysqli($host, $user, $password, $dbname);
		if(mysqli_connect_errno()){
		 	return mysqli_connect_error();
		}else{
			$connection->set_charset('utf8');
			self::$conn = $connection;
		}
	}


	public function insert($sql){		
			return self::$conn->query($sql);		
	}

	public function querys($sql){
		$res = [];
		$results = self::$conn->query($sql);
		while ($row = mysqli_fetch_array($results, MYSQLI_ASSOC)){
		    $res[] = $row;
		}
		return $res;
	}
	public function update($sql){
		return  self::$conn->query($sql);
		
		 
	}

	public function insertContent($md5url, $tablename, $sql){
		$query = "select * from ".$tablename." where md5url='".$md5url."'";
		$query_res = $this->querys($query);
		if (!$query_res) {
			
			return self::$conn->query($sql);
		}else{
			return false;
		}
	}



	public function  __destruct() {    
        self::$conn->close();  
    }
}



 ?>