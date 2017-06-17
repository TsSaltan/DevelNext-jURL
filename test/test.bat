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
$Vk = curl_init('http://m.vk.com/');
        curl_setopt_array($Vk, [
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.3; rv:38.0) Gecko/20100101 Firefox/38.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEFILE => 'SendVk.cookie']
        );
        $res = curl_exec($Vk);
        var_dump( $res);

       
        $rg = new Regex('<form method="post" action="([\w\W]+)" novalidate>', 'U',$res);
        $Url = $rg->all();
     
        if(empty($Url[0][1])) {
            curl_close($Vk);
            return false;
        }
       // var_dump(['Url' => $Url[0][1]]);

        $Vk = curl_init($Url[0][1]);
        curl_setopt($Vk, CURLOPT_URL, 'http://google.com' );
        /*curl_setopt($Vk, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($Vk, CURLOPT_HTTPHEADER, ['Host' => 'login.vk.com']);
        curl_setopt($Vk, CURLOPT_POST, true);
        curl_setopt($Vk, CURLOPT_POSTFIELDS, http_build_query(['email' => $Login, 'pass' => $Pass]));*/
        $res = curl_exec($Vk);
        var_dump($res);

        //*/