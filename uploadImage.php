<?php
namespace sql;
include_once $_SERVER["DOCUMENT_ROOT"]."/functions/writeLog.php";
include_once "sql/DataBase.php";
// include_once "sql/Query.php";

// задание максимальной ширины и высоты
function res_up_img($width, $height){
	global $uploadfile;
	global $tumbsfile;	
	global $width_orig;
	global $height_orig;
	if (($width_orig < $width) || ($height_orig<$height)) return false;
	$ratio_orig = $width_orig/$height_orig;

	if ($width_orig<$height_orig){
		$height = $width;
		$width = $height*$ratio_orig;
	}
	else {
	if ($width/$height > $ratio_orig) {
	   $width = $height*$ratio_orig;
	} else {
	   $height = $width/$ratio_orig;
	}	}	
	// ресэмплирование
	$image_p = imagecreatetruecolor($width, $height);
	
	switch (exif_imagetype($uploadfile)) {
		case IMAGETYPE_GIF:
			$image = imagecreatefromgif($uploadfile);
			break;
		case IMAGETYPE_JPEG:
			$image = imagecreatefromjpeg($uploadfile);
			break;
		case IMAGETYPE_PNG:
			$image = imagecreatefrompng($uploadfile);
			break;
	}
	imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
	imagejpeg($image_p, $tumbsfile , 80);
	return true;
}

if($_FILES["imgupload"]["size"] > 1024*10*1024)
   {
     echo "Размер файла превышает 10 мегабайт";
     return;
   }
 //пользователь  
 
 
$db = new DataBase;
if ($conn = $db->getConnect()){
}else{
  echo "NOT connect";
}
$param['headers'] = getallheaders();
$local_time_zone = "+04:00"; //временная зона по умолчанию для Самары
if (isset($param['headers']["V-Token"])){
  $token = $param['headers']["V-Token"];
  $ip = $_SERVER['REMOTE_ADDR'];
  writeLog("Используется токен: $token");
  $stm = $conn->prepare("SELECT ID
	FROM bd_users u
	WHERE u.SESSEION_HASH = ? 
	AND u.IP = ?");
  $stm->execute(array($token, $ip));
  if ($row = $stm->fetch()){
		$ID = $row["ID"]; 	
    writeLog("Авторизация активна: $token");
  }else{
    echo "Нет авторизации";
    return;
  }
}else{
  echo "Нет авторизации";
  return;
}

$stm = $conn->prepare("SET time_zone=?");
$stm->execute(array($local_time_zone));


//директория загрузки оригинального файла
$uploaddir = $_SERVER['DOCUMENT_ROOT'].'/images/'; 
// получение оригинальных размеров
list($width_orig, $height_orig) = getimagesize($_FILES['imgupload']['tmp_name']);  
//оригинальное имя файла
$path_parts = pathinfo(basename($_FILES['imgupload']['name']));
// файл
$filename = $_FILES['imgupload']['tmp_name'];
//новое имя файла
$file_hash_orig = hash_file('md5',$filename).".".$path_parts['extension'];
//место загрузки оригинального файла 
$uploadfile = $uploaddir.$file_hash_orig;
//место загрузки уменьшенного файла 
$tumbsfile = $uploaddir."tumbs/".$file_hash_orig;
$hash180 ="";
$hash720 ="";
$hash1080 ="";
if (move_uploaded_file($_FILES['imgupload']['tmp_name'], $uploadfile)) {
	
    res_up_img(240,180);	
	$tumbshash= hash_file('md5',$tumbsfile).".".$path_parts['extension'];
	if (rename($tumbsfile,$uploaddir."tumbs/".$tumbshash))
		$hash180= $tumbshash;
	
	if (res_up_img(1280,720)) {	
	$tumbshash= hash_file('md5',$tumbsfile).".".$path_parts['extension'];
	if (rename($tumbsfile,$uploaddir."720p/".$tumbshash))
		$hash720= $tumbshash;
	}
	
	if (res_up_img(1920,1080))	{
	$tumbshash= hash_file('md5',$tumbsfile).".".$path_parts['extension'];
	if (rename($tumbsfile,$uploaddir."1080p/".$tumbshash))
		$hash1080 = $tumbshash;
	}
	
	if (isset($_POST['userpic']) && ($_POST['userpic'] == 2)) {
            $stm = $conn->prepare("UPDATE bd_users SET image=? WHERE ID=?");
						$result = $stm->execute(array($hash180,$ID));
            if ($result) {
                echo ("Фото установлено для профиля ");
            } else {
                echo ($sql);
            }
    }
    $stm = $conn->prepare("SELECT count(*) cnt, min(tumbs) tumbs  
	      FROM bd_gallery 
		  WHERE ORIG_IMAGE=?");
	$stm->execute(array($file_hash_orig));
	$row = $stm->fetch();
	if ($row['cnt'] == 0) {
		$stm = $conn->prepare("INSERT INTO bd_gallery (kod_user, ORIG_IMAGE, tumbs, original_name, 720p, 1080p, width, height) 
	          VALUES ($ID, '$file_hash_orig', '$hash180', '".$path_parts['basename']."', '$hash720', '$hash1080', $width_orig, $height_orig)");
						$stm->execute();
            echo("Фото записанно в галерею под именем $hash180");
    }
	else		{
		$stm = $conn->prepare("UPDATE bd_gallery 
				SET 720p='$hash720', 
					1080p='$hash1080' 
		  WHERE ORIG_IMAGE=?");	
			$stm->execute(array($file_hash_orig));	
		}
	$stm = $conn->prepare("SELECT id  
	      FROM bd_gallery 
		  WHERE ORIG_IMAGE=?");
	$stm->execute(array($file_hash_orig));
	$row = $stm->fetch();
	$gallery_id = $row['id'];
	//$stm = $conn->prepare(("INSERT INTO bd_gallery_access (kod_user, kod_gallery, name_pic)  
	//      VALUES (1, $gallery_id, '".$path_parts['basename']."')");
	$stm = $conn->prepare("INSERT INTO bd_gallery_access (kod_user, kod_gallery, name_pic, kod_folder)  
	      VALUES ($ID, $gallery_id, '".$path_parts['basename']."', 1)");
	$stm->execute();
	echo ("Права доступа на фото присвоены");
} else {
	echo ("Загрузка файла не удалась...");
}
