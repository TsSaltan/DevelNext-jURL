<?// :2>nul&chcp 65001&cls&java -jar "%~dp0jphp-exec.jar" "%~0"&pause&exit /b
define('DS', '\\');
define('DIR', __DIR__ . DS);
include DIR . "autoloader.inc.php";

###
use bundle\jurl\jURL;
use bundle\jurl\jURLFile;
new cURL;

echo("\n --- Test 1 : Get & save cookies --- \n");

$url = 'http://test.tssaltan.ru/curl/cookie_get.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.json');
curl_setopt($ch, CURLOPT_HEADER, true);
var_dump($result = curl_exec($ch));


echo("\n --- Test 2 : Send cookies --- \n");
$url = 'http://test.tssaltan.ru/curl/cookie_send.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.json');
var_dump(curl_exec($ch));
