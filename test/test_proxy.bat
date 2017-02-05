<?// :2>nul&chcp 65001&cls&java -jar "%~dp0jphp-exec.jar" "%~0"&pause&exit /b
define('DS', '\\');
define('DIR', __DIR__ . DS);
include DIR . "autoloader.inc.php";

###
use bundle\jurl\jURL;
use bundle\jurl\jURLFile;
new cURL;


echo ("\n --- Test N : Proxy With Auth - Error --- \n");

// Error password
$ch = new jURL('http://ipinfo.io/json');
$ch->setProxyType('HTTP');
$ch->setProxy('localhost:3128');
$ch->setProxyAuth('admin:12345678910');
var_Dump($ch->exec());
var_dump($ch->getError());

echo ("\n\n --- Test N : Proxy With Auth - Success --- \n");
$ch = curl_init('http://ipinfo.io/json');
curl_setopt($ch, CURLOPT_PROXY, 'localhost:3128');
curl_setopt($ch, CURLOPT_PROXYUSERPWD, 'admin:123456789');
var_dump($ch->exec());


echo ("\n\n --- Test N : POST via Proxy With Auth --- \n");

$url = 'http://test.tssaltan.ru/curl/upload_request.php';

// Создаём новый файл, который будет загружен на сервер
$newFile = 'testFile.txt';
file_put_contents($newFile, 'Hello World 123456789!');

$newFile1 = 'testFile1.txt';
file_put_contents($newFile1, str_repeat(md5('Hello World 123456789!'), 100));

$ch = new jURL($url);


$ch->setProxyType('HTTP');
$ch->setProxy('localhost:3128');
$ch->setProxyAuth('admin:123456789');

$ch->setRequestMethod('POST');

$ch->setPostData([ "action" => '123', "width" => '321']);
$ch->setPostFiles([
    'file[1]' =>  curl_file_create($newFile, 'image/text'),
    'file[2]' =>  new jURLFile($newFile),
    'file[3]' =>  new \cURLFile($newFile1, 'image/jpeg', 'asd.zip'),
    'file[4]' =>   '@' . $newFile1
]);

$data = $ch->exec();

var_dump($data);
//var_dump($ch->getConnectionInfo());
var_dump(['getError' => $ch->getError()]);