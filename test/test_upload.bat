<?// :2>nul&chcp 65001&cls&java -jar "%~dp0jphp-exec.jar" "%~0"&pause&exit /b
define('DS', '\\');
define('DIR', __DIR__ . DS);
include DIR . "autoloader.inc.php";

###
use bundle\jurl\jURL;
use bundle\jurl\jURLFile;
new cURL;

echo ("\n --- Test 1 : Upload files --- \n");

$url = 'http://test.tssaltan.ru/curl/upload_request.php';

// Создаём новый файл, который будет загружен на сервер
$newFile = 'testFile.txt';
file_put_contents($newFile, 'Hello World 123456789!');

$newFile1 = 'testFile1.txt';
file_put_contents($newFile1, str_repeat(md5('Hello World 123456789!'), 100));


$post = [
    "action" => '123', 
    "width" => '321', 
    
    'file[1]' =>  curl_file_create($newFile, 'image/text'),
    'file[2]' =>  new jURLFile($newFile),
    'file[3]' =>  new \cURLFile($newFile1, 'image/jpeg', 'asd.zip'),
    'file[4]' =>   '@' . $newFile1,
];


$ch = new jURL($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_POST, true);

var_dump(curl_exec($ch));


echo ("\n --- Test 2 : Upload files --- \n");

// ООП 
$ch = new jURL($url);
$ch->setRequestMethod('POST');
$ch->setPostData([ "action" => '123', "width" => '321']);
$ch->setPostFiles([
    'file[1]' =>  curl_file_create($newFile, 'image/text'),
    'file[2]' =>  new jURLFile($newFile),
    'file[3]' =>  new \cURLFile($newFile1, 'image/jpeg', 'asd.zip'),
    'file[4]' =>   '@' . $newFile1
]);

var_dump($ch->exec());