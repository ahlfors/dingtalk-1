<?php
namespace jasonzhangxian\dingtalk;

use \Yii;
use \yii\base\Component;
use yii\caching\Cache;
use jasonzhangxian\dingtalk\Http;


class DingtalkSns extends Component
{
    public $appid = "";
    public $appsecret = "";
    public $redirect_uri = "";
    public $host = "oapi.dingtalk.com";
    public $protocol = "https";
    public $cache;

    const DINGTALK_SNS_CACHEKEY = "dingtalk_sns_cachekey";

    public function init()
    {
        parent::init();

        define('OAPI_HOST', $this->protocol."://".$this->host);

        $this->cache = Yii::$app->cache;
    }

    /**
     * 缓存扫码accessToken。accessToken有效期为两小时，需要在失效前请求新的accessToken
     */
    public function getSnsAccessToken()
    {
        $accessToken = $this->cache->get(self::DINGTALK_SNS_CACHEKEY);
        if (!$accessToken)
        {
            $response = Http::get('/sns/gettoken', array('appid' => $this->appid, 'appsecret' => $this->appsecret));
            self::check($response);
            $accessToken = $response->access_token;
            $this->cache->set(self::DINGTALK_SNS_CACHEKEY, $accessToken, 7000);
        }
        return $accessToken;
    }

    /**
     *
     * 根据临时授权码，获取用户信息
     * 由于以下流程基本不会单独使用， 就合并到一起了
     * @access  public
     * @return  array
     */
    public function getUserByCode($code)
    {
        //切换access_token
        $accessToken = $this->getSnsAccessToken();

        //获取用户授权的持久授权码
        $response = Http::post('/sns/get_persistent_code', array('access_token'=>$accessToken), json_encode(array('tmp_auth_code'=>$code)));
        $openid = $response->openid;
        $unionid = $response->unionid;
        $persistent_code = $response->persistent_code;

        //获取用户授权的SNS_TOKEN
        $token_response = Http::post('/sns/get_sns_token', array('access_token'=>$accessToken), array('openid'=>$openid, 'persistent_code'=>$persistent_code));
        $sns_token = $token_response->sns_token;

        //获取用户授权的个人信息
        $info_response = Http::get('/sns/getuserinfo', array('sns_token'=>$sns_token));

        return $info_response;
    }

    static function check($res)
    {
        if ($res->errcode != 0)
        {
            exit("Failed: " . json_encode($res));
        }
    }
}
