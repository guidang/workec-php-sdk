<?php

class Workec {

    const API_BASE_URL_PREFIX = 'https://open.workec.com'; //以下API接口URL需要使用此前缀
    const OAUTH_TOKEN_URL = '/auth/accesstoken'; //获取token
    
    //组织架构
    const USER_STRUCTURE = '/user/structure'; //获取部门和员工信息
    const USER_FINDUSERINFOBYID = '/user/findUserInfoById'; //获取指定员工信息
    
    //客户
    const CUSTOMER_ADDCUSTOMER = '/customer/addCustomer'; //创建客户
    const CUSTOMER_DELCRMS = '/customer/delcrms'; //获取删除的客户
    const CUSTOMER_ABANDON = '/customer/abandon'; //放弃客户
    const CUSTOMER_CHANGECRMFOLLOWUSER = '/customer/changeCrmFollowUser'; //变更客户跟进人
    
    private $token;
    private $corpid;
    private $appid;
    private $appsecret;
    public $debug = false;

    private $headers = [];
    private $access_token;

    public function __construct($options) {
        $this->token = isset($options['token']) ? $options['token'] : '';
        $this->corpid = isset($options['corpid']) ? $options['corpid'] : '';
        $this->appid = isset($options['appid']) ? $options['appid'] : '';
        $this->appsecret = isset($options['appsecret']) ? $options['appsecret'] : '';
        $this->debug = isset($options['debug']) ? $options['debug'] : false; 
    }

    /**
     * GET 请求
     * @param string $url
     */
    private function http_get($url) {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $this->headers);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }    

    /**
     * POST 请求
     * @param string $url
     * @param array $param
     * @param boolean $post_file 是否文件上传
     * @return string content
     */
    private function http_post($url, $param, $post_file = false, $use_cert = false, $second = 30) {
        $oCurl = curl_init();

        //设置超时
        curl_setopt($oCurl, CURLOPT_TIMEOUT, $second);
        
        //设置头
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $this->headers);

        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            //curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
            curl_setopt($oCurl,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        }
        if (PHP_VERSION_ID >= 50500 && class_exists('\CURLFile')) {
            $is_curlFile = true;
        } else {
            $is_curlFile = false;
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($oCurl, CURLOPT_SAFE_UPLOAD, false);
            }
        }
        if (empty($param)) {
            $strPOST = [];
        } elseif (is_string($param)) {
            $strPOST = $param;
        } elseif ($post_file) {
            if ($is_curlFile) {
                foreach ($param as $key => $val) {
                    if (substr($val, 0, 1) == '@') {
                        $param[$key] = new \CURLFile(realpath(substr($val, 1)));
                    }
                }
            }
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }

        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);

        //设置header
        curl_setopt($oCurl, CURLOPT_HEADER, false);

        //设置证书
        if($use_cert == true){
            //第一种方法，cert 与 key 分别属于两个.pem文件
            //第二种方式，两个文件合成一个.pem文件
            curl_setopt($oCurl,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($oCurl,CURLOPT_SSLCERT, $this->sslcert);

            //第一种方式
            if ($this->sslkey !== '') {
                curl_setopt($oCurl,CURLOPT_SSLKEYTYPE,'PEM');
                curl_setopt($oCurl,CURLOPT_SSLKEY, $this->sslkey);
            }
        }

        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    } 

    
    /**
     * api不支持中文转义的json结构
     * @param array $arr
     */
    private static function json_encode($arr) {
        if (count($arr) == 0) return "[]";
        $parts = array();
        $is_list = false;
        //Find out if the given array is a numerical array
        $keys = array_keys($arr);
        $max_length = count($arr) - 1;
        if (($keys [0] === 0) && ($keys [$max_length] === $max_length)) { //See if the first key is 0 and last key is length - 1
            $is_list = true;
            for ($i = 0; $i < count($keys); $i++) { //See if each key correspondes to its position
                if ($i != $keys [$i]) { //A key fails at position check.
                    $is_list = false; //It is an associative array.
                    break;
                }
            }
        }
        foreach ($arr as $key => $value) {
            if (is_array($value)) { //Custom handling for arrays
                if ($is_list)
                    $parts [] = self::json_encode($value); /* :RECURSION: */
                else
                    $parts [] = '"' . $key . '":' . self::json_encode($value); /* :RECURSION: */
            } else {
                $str = '';
                if (!$is_list)
                    $str = '"' . $key . '":';
                //Custom handling for multiple data types
                if (!is_string($value) && is_numeric($value) && $value < 2000000000)
                    $str .= $value; //Numbers
                elseif ($value === false)
                    $str .= 'false'; //The booleans
                elseif ($value === true)
                    $str .= 'true';
                else
                    $str .= '"' . addslashes($value) . '"'; //All other things
                // :TODO: Is there any more datatype we should be in the lookout for? (Object?)
                $parts [] = $str;
            }
        }
        $json = implode(',', $parts);
        if ($is_list)
            return '[' . $json . ']'; //Return numerical JSON
        return '{' . $json . '}'; //Return associative JSON
    }       

    /**
     * 设置缓存，按需重载
     * @param string $cachename
     * @param mixed $value
     * @param int $expired
     * @return boolean
     */
    protected function setCache($cachename, $value, $expired) {
        //TODO: set cache implementation
        return false;
    }

    /**
     * 获取缓存，按需重载
     * @param string $cachename
     * @return mixed
     */
    protected function getCache($cachename) {
        //TODO: get cache implementation
        return false;
    }

    /**
     * 清除缓存，按需重载
     * @param string $cachename
     * @return boolean
     */
    protected function removeCache($cachename) {
        //TODO: remove cache implementation
        return false;
    }
   
    /**
     * 通过code获取Access Token
     * @return array {access_token,expires_in,refresh_token,openid,scope}
     */
    public function getOauthAccessToken() {
        $data = [
            'appId' => $this->appid,
            'appSecret' => $this->appsecret,
        ];

        $result = $this->http_post(self::API_BASE_URL_PREFIX . self::OAUTH_TOKEN_URL, self::json_encode($data));
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || ($json['errCode'] != 200)) {
                $this->errCode = $json['errCode'];
                $this->errMsg = $json['errMsg'];
                return false;
            }
            $this->access_token = $json['data']['accessToken'];
            return $json;
        }
        return false;
    }

    /**
     * 获取access_token
     * @param string $appid 如在类初始化时已提供，则可为空
     * @param string $appsecret 如在类初始化时已提供，则可为空
     * @param string $token 手动指定access_token，非必要情况不建议用
     */
    public function checkAuth($appid = '', $appsecret = '', $token = '') {
        if (!$appid || !$appsecret) {
            $appid = $this->appid;
            $appsecret = $this->appsecret;
        }
        if ($token) { //手动指定token，优先使用
            $this->access_token = $token;
            return $this->access_token;
        }

        $authname = 'workec_access_token' . $appid;
        if ($rs = $this->getCache($authname)) {
            $this->access_token = $rs;
            return $rs;
        }

        $data = [
            'appId' => $this->appid,
            'appSecret' => $this->appsecret,
        ];

        $result = $this->http_post(self::API_BASE_URL_PREFIX . self::OAUTH_TOKEN_URL, self::json_encode($data));

        if ($result) {
            $json = json_decode($result, true);
            if (!$json || ($json['errCode'] != 200)) {
                $this->errCode = $json['errCode'];
                $this->errMsg = $json['errMsg'];
                return false;
            }
            $this->access_token = $json['data']['accessToken'];
            $expire = $json['data']['expiresIn'] ? intval($json['data']['expiresIn']) - 100 : 3600;
            $this->setCache($authname, $this->access_token, $expire);
            return $this->access_token;
        }
        return false;
    } 

    /**
     * 设置 Header 信息
     */
    public function setHeader() {
        $this->headers =  [
            'corp_id: ' . $this->corpid,
            'authorization: ' . $this->access_token,
        ];
    }

    /**
     * 获取部门和员工信息
     */
    public function getUserStructure() {
        if (!$this->access_token && !$this->checkAuth()) return false;

        $this->setHeader();

        $result = $this->http_get(self::API_BASE_URL_PREFIX . self::USER_STRUCTURE);
        if ($result) {
            if (is_string($result)) {
                $json = json_decode($result, true);
                if (!$json || ($json['errCode'] != 200)) {
                    $this->errCode = $json['errCode'];
                    $this->errMsg = $json['errMsg'];
                    return false;
                }
            }
            return $result;
        }
        return false;
    }

    /**
     * 获取指定员工信息
     */
    public function findUserInfoById($data) {
        if (!$this->access_token && !$this->checkAuth()) return false;

        $this->setHeader();

        $result = $this->http_post(self::API_BASE_URL_PREFIX . self::USER_FINDUSERINFOBYID, self::json_encode($data));
        if ($result) {
            if (is_string($result)) {
                $json = json_decode($result, true);
                if (!$json || ($json['errCode'] != 200)) {
                    $this->errCode = $json['errCode'];
                    $this->errMsg = $json['errMsg'];
                    return false;
                }
            }
            return $result;
        }
        return false;
    }    

    /**
     * 添加客户
     * @param array $data
     * @return boolean
     */
    public function addCustomer($data) {
        if (!$this->access_token && !$this->checkAuth()) return false;

        $this->setHeader();
        
        $result = $this->http_post(self::API_BASE_URL_PREFIX . self::CUSTOMER_ADDCUSTOMER, self::json_encode($data));
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || ($json['errCode'] != 200)) {
                $this->errCode = $json['errCode'];
                $this->errMsg = $json['errMsg'];
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * 变更客户跟进人
     * @param array $data
     * @return boolean
     */
    public function customerChangeCrmFollowUser($data) {
        if (!$this->access_token && !$this->checkAuth()) return false;

        $this->setHeader();

        $result = $this->http_post(self::API_BASE_URL_PREFIX . self::CUSTOMER_CHANGECRMFOLLOWUSER, self::json_encode($data));
        if ($result) {
            if (is_string($result)) {
                $json = json_decode($result, true);
                if (!$json || ($json['errCode'] != 200)) {
                    $this->errCode = $json['errCode'];
                    $this->errMsg = $json['errMsg'];
                    return false;
                }
            }
            return $result;
        }
        return false;
    }    
 
    /**
     * 放弃客户
     * @param array $data
     * @return boolean
     */
    public function customerAbandon($data) {
        if (!$this->access_token && !$this->checkAuth()) return false;

        $this->setHeader();

        $result = $this->http_post(self::API_BASE_URL_PREFIX . self::CUSTOMER_ABANDON, self::json_encode($data));
        if ($result) {
            if (is_string($result)) {
                $json = json_decode($result, true);
                if (!$json || ($json['errCode'] != 200)) {
                    $this->errCode = $json['errCode'];
                    $this->errMsg = $json['errMsg'];
                    return false;
                }
            }
            return $result;
        }
        return false;
    }     
    
    /**
     * 获取删除的客户
     * @param array $data
     * @return boolean
     */
    public function customerDelcrms($data) {
        if (!$this->access_token && !$this->checkAuth()) return false;

        $this->setHeader();

        $result = $this->http_post(self::API_BASE_URL_PREFIX . self::CUSTOMER_DELCRMS, self::json_encode($data));
        if ($result) {
            if (is_string($result)) {
                $json = json_decode($result, true);
                if (!$json || ($json['errCode'] != 200)) {
                    $this->errCode = $json['errCode'];
                    $this->errMsg = $json['errMsg'];
                    return false;
                }
            }
            return $result;
        }
        return false;
    }    
}