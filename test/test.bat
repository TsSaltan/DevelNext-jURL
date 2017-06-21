<?// :2>nul&chcp 65001&cls&java -jar "%~dp0jphp-exec.jar" "%~0"&pause&exit /b
define('DS', '\\');
define('DIR', __DIR__ . DS);
include DIR . "autoloader.inc.php";

###
use bundle\jurl\jURL;
use bundle\jurl\jURLFile;
new \cURL;


/*
    $ip = '185.167.162.62:8000'; 
    $ip = 'localhost:3128'; 
$a_poxy = 'SoVQts:J30BNU'; 
$a_poxy = 'admin:123456789'; 


$curl = curl_init(); 
curl_setopt($curl, CURLOPT_URL, 'https://2ip.ru/'); 
curl_setopt ($curl, CURLOPT_HEADER, true); 
curl_setopt ($curl, CURLOPT_PROXY, $ip); 
curl_setopt ($curl, CURLOPT_PROXYUSERPWD, $a_poxy); 
curl_setopt ($curl, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); 
$response = curl_exec($curl); 
var_dump($response); 
var_dump(curl_error($curl));
//*/
use php\util\Regex;

// первый запрос

$Vk = curl_init('http://m.vk.com/');//*
curl_setopt_array($Vk, [
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.3; rv:38.0) Gecko/20100101 Firefox/38.0',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_COOKIEFILE => 'vk.cookie']
);
$res = curl_exec($Vk);
var_dump('--------- Query 1 --------- ');
var_dump($res);
//var_dump('---------');
//var_dump( curl_getinfo($Vk) );

// парсим ссылку
$rg = new Regex('<form method="post" action="([\w\W]+)" novalidate>', 'U',$res); 
$Url = $rg->first();
if(empty($Url[1])) {
    curl_close($Vk);
    return false;
}

var_dump('---------');
var_dump( $Url );

var_dump('--------- Query 2 --------- ');
// второй запрос */
$auth = http_build_query(['email'=> '380633774673', 'pass' => 'авава']);
curl_setopt($Vk, CURLOPT_URL, $Url[1] );
//curl_setopt($Vk, CURLOPT_URL, 'http://localhost/curl/2headers.php');
curl_setopt($Vk, CURLOPT_CUSTOMREQUEST, 'POST');
//curl_setopt($Vk, CURLOPT_HTTPHEADER, ['Host: login.vk.com', 'Content-Length: 10' . strlen($auth), 'Meow: Gau']);

curl_setopt($Vk, CURLOPT_POST, true);
curl_setopt($Vk, CURLOPT_POSTFIELDS, $auth);
$c = curl_exec($Vk);
//$x = curl_getinfo($Vk);
//var_dump($x);
var_dump('--------');
var_dump($c);