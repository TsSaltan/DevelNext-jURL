<?// :2>nul&chcp 65001&cls&java -jar "%~dp0jphp-exec.jar" "%~0"&pause&exit /b
define('DS', '\\');
define('DIR', __DIR__ . DS);
include DIR . "autoloader.inc.php";

###
use bundle\jurl\jURL;
use bundle\jurl\jURLFile;
new cURL;

echo("\n --- Test 1 : GET --- \n");
$ch = new jURL('http://test.tssaltan.ru/curl/get.php');
$data = $ch->exec();
var_dump($data);

echo("\n --- Test 2 : POST --- \n");

// ООП
$url = 'http://test.tssaltan.ru/curl/post.php';
$ch = new jURL($url);
$ch->setPostData(['a'=>'b', 'c'=>'d', 'array' => ['yes' => 1, 'no' => 'NulL']]);
var_dump($ch->exec());

// Процедурный стиль
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['a'=>'b', 'c'=>'ds', 'array' => ['yes' => 22, 'no' => '123']]);
var_dump(curl_exec($ch));


echo ("\n --- Test 3 : Redirect --- \n");

$url = 'http://test.tssaltan.ru/curl/redirect.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
var_dump(curl_exec($ch));