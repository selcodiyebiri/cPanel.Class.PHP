<?php
namespace Selco;

/**
 *  cPanel WHM Api Class
 *
 *  @filename cpanel.class.php
 *
 *  @author Selçuk Çelik
 *  @blog http://selcuk.in
 *  @mail selcuk@msn.com
 *  @date 19.02.2016
 *
 */

# Exception dahil etme
require_once "cpanel.exception.php";

class cPanel {

    # Sunucu protokolü
    static $protocol = 'https';

    # Sunucu IP adresi
    static $host = "127.0.0.1";

    # Sunucu portu
    static $port = "2087";

    # Sunucu yetkili kullanıcı adı
    static $username = "root";

    # Sunucu yetkili şifresi
    static $password = NULL;

    # İşlem çıktı türü
    static $response_type = ['json', 'xml'];

    /*
     *  Genel Ayarların yapıldığı fonk.
     *
     *  @param $settings
     */
    static function settings($settings = [])
    {
        if ( isset($settings) )
        {
            if ( is_array($settings) )
            {
                if ($settings['_protocol']) self::$protocol = $settings['_protocol'];
                if ($settings['_host']) self::$host = $settings['_host'];
                if ($settings['_port']) self::$port = $settings['_port'];
                if ($settings['_username']) self::$username = $settings['_username'];
                self::$password = ($settings['_password']) ? $settings['_password'] : NULL;
                self::$response_type =  (in_array($settings['_result'], self::$response_type)) ? self::$response_type = $settings['_result'] : 'json';
            }
        }
    }

    /*
     *  cPanel WHM hesapları listelenme fonk.
     *
     *  @param $sType
     *  @param $search
     *
     *  @return json||xml
     */

    static function listAccounts($sType = NULL, $search = NULL)
    {
        if ( $search )
        {
           return self::_callRequest('listaccts', [
               'searchtype' => $sType,
               'search' => $search,
           ]);
        }

        return self::_callRequest('listaccts');
    }

    /*
     *  cPanel WHM yeni host açma fonksiyonu
     *
     *  @param $params
     *
     *  @return json||xml
     */

    static function newAccount($params)
    {
        if ( (!$params['username']) && (!$params['password']) && (!$params['domain']) )
            throw new cPanelException("Host açma işlemi için en az kullanıcı adı, şifre ve domain adı belirtmelisiniz.");

        return self::_callRequest('createacct', $params);
    }

    /*
     *  cPanel WHM hesap düzenleme fonksiyonu
     *
     *  @param $username
     *  @param $params
     *
     *  @return json||xml
     */

    static function editAccount($username, $params)
    {
        if ( (!$username) )
            throw new cPanelException("Host düzenleme işlemi için hesap kullanıcı adı belirtmelisiniz.");

        $params['user'] = $username;
        return self::_callRequest('modifyacct', $params);
    }

    /*
     *  cPanel WHM hesap şifresi değiştirme
     *
     *  @param $username
     *  @param $password
     *
     *  @return json||xml
     */

    static function changePassword($username, $password)
    {
        if ( (!$username) && (!$password) )
            throw new cPanelException("Host şifre değiştirme işlemi için hesap kullanıcı adı ve şifre belirtmelisiniz.");

        return self::_callRequest('passwd', [
            'user' => $username,
            'pass' => $password,
            'digestauth' => 1
        ]);
    }

    /*
     *  cPanel WHM hesap silme
     *
     *  @param $username
     *  @param $keepDNS
     *
     *  @return json||xml
     */

    static function deleteAccount($username, $keepDNS = FALSE)
    {
        if ( (!$username) )
            throw new cPanelException("Host silme işlemi için hesap kullanıcı adı belirtmelisiniz.");

        return self::_callRequest('removeacct', [
            'user' => $username,
            'keepdns' => ($keepDNS) ? '1' : '0'
        ]);
    }

    /*
     *  cPanel WHM hesap detaylarını öğrenme
     *
     *  @param $username
     *
     *  @return json||xml
     */

    static function accountDetails($variable)
    {
        if ( (!$variable) )
            throw new cPanelException("Host detaylarına göz atmak için hesap kullanıcı adı veya domain adı belirtmelisiniz.");

        if (self::_validDomain($variable))
            $params['domain'] = $variable;
        else
            $params['user'] = $variable;

        return self::_callRequest('accountsummary', $params);
    }

    /*
     *  cPanel WHM geçici olarak durdurulmuş hesapları listelenme fonk.
     *
     *  @return json||xml
     */

    static function listSuspendAccounts()
    {
        return self::_callRequest('listsuspended');
    }

    /*
     *  cPanel WHM hesap geçiçi olarak durdurma
     *
     *  @param $username
     *  @param $comment
     *
     *  @return json||xml
     */

    static function addSuspend($username, $comment = NULL)
    {
        if ( (!$username) )
            throw new cPanelException("Host hesabını durdurma işlemi için hesap kullanıcı adı belirtmelisiniz.");

        return self::_callRequest('suspendacct', [
            'user' => $username,
            'reason' => $comment
        ]);
    }

    /*
     *  cPanel WHM hesap geçiçi olarak durdurma işlemini iptal etme
     *
     *  @param $params
     *
     *  @return json||xml
     */

    static function deleteSuspend($username)
    {
        if ( (!$username) )
            throw new cPanelException("Host hesabını durdurma işlemini iptal etmek için hesap kullanıcı adı belirtmelisiniz.");

        return self::_callRequest('unsuspendacct', [
            'user' => $username
        ]);
    }

    /*
     *  Domain işlemleri için kullanılacak domain doğrulama fonksiyonu
     *
     *  @param $domain
     *
     *  @return boolean
     */

    protected static function _validDomain($domain)
    {
        return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain)
            && preg_match("/^.{1,253}$/", $domain)
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain));
    }

    /*
     *  cPanel WHM istek fonksiyonu
     *
     *  @param $type
     *  @param $params
     *
     *  @return mixed
     */

    protected static function _callRequest($type, $params = [])
    {
        if ((!self::$username) OR (!self::$password)) throw new cPanelException("İşlem yapmanız için öncelikle yetkili bilgilerini tanımlamanız gerekmektedir.");

        $api_type = '/json-api/';
        if (self::$response_type == 'xml') $api_type = '/xml-api/';

        $params = http_build_query($params, '', '&');
        $URL = self::$protocol . "://" . self::$host . ":" . self::$port . $api_type . $type;
        $authString = "Authorization: Basic " . base64_encode(self::$username .':'. self::$password) . "\r\n";

        $result = self::_cURL($URL, $params, $authString);
        return (self::$response_type == 'xml') ? simplexml_load_string($result, NULL, LIBXML_NOERROR | LIBXML_NOWARNING) : json_decode($result);
    }

    /*
     *  cPanel WHM işlemleri için kullanılacak temel cURL fonksiyonu
     *
     *  @param $URL
     *  @param $postParams
     *  @param $authString
     *
     *  @return mixed
     */
    protected static function _cURL($URL, $postParams, $authString)
    {
        $headers = [
            $authString .
            "Content-Type: application/x-www-form-urlencoded\r\n" .
            "Content-Length: " . strlen($postParams) . "\r\n" . "\r\n" . $postParams
        ];

        $selco = curl_init();
        $options = array(
            CURLOPT_URL => $URL,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_SSL_VERIFYHOST => FALSE,
            CURLOPT_BUFFERSIZE => 131072,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE
        );

        curl_setopt_array($selco, $options);
        $response = curl_exec($selco);

        curl_close($selco);

        if ($response === FALSE) throw new cPanelException("cURL Hata: " . curl_error($selco) . " => " . $URL . "?" . $postParams);
        return $response;
    }
}