<?php

namespace mysqladminlite\lib;

/**
 * 用户登录认证类
 * @version 2017-12-15
 */
class JwtHandler
{

    /**
     * TOKEN_EXP 超时时间 (1 hours, 1 minutes, 30 seconds)
     * @var string
     */
    public static $TOKEN_EXP = '1 hours';

    /**
     * TOKEN_AUD 允许运行的服务器列表
     * 适用于分布式环境，如果是负载均衡 + 高防 IP 环境，则填入高防 IP 即可，多个之间以逗号隔开，中间不要有空格
     * @var string
     */
    public static $TOKEN_AUD = '127.0.0.1,47.95.44.177,47.94.100.12,47.93.45.33,47.93.42.85';

    /**
     * TOKEN 签名密钥
     * @var string
     */
    public static $TOKEN_KEY = 'InZ7!';

    /**
     * TOKEN 自动延期时长, 每次页面跳转操作会重新生成一个延期过的 TOKEN，类似 session 的效果，只要一直操作就一直不会过期
     * @var integer
     */
    public static $TOKEN_REFRESH_SECOND = 1800;

    /**
     * 页面访问验证配置
     * @var array
     */
    public static $AUTH_CONFIG = [];

    /**
     * 登录结果
     * @var array compact('loginid', 'token', 'loginexp')
     */
    public static $LOGIN_RESULT = [];

    /**
     * 初始化
     * @param array $config 全局配置
     */
    public static function init($config)
    {
        if (empty($config['auth'])) {
            exit('请配置 AUTH 节点信息!');
        }
        self::$AUTH_CONFIG = $config['auth'];
        // TOKEN_EXP 超时时间 (1 hours, 1 minutes, 30 seconds)
        self::$TOKEN_EXP = self::arrayValue($config, 'auth.token_exp', '1 hours');
        // TOKEN_AUD 允许运行的服务器列表（适用于分布式环境，如果是负载均衡 + 高防 IP 环境，则填入高防 IP 即可）
        // 多个之间以逗号隔开，中间不要有空格，例如：127.0.0.1,110.52.27.168
        self::$TOKEN_AUD = self::arrayValue($config, 'auth.token_auth', '127.0.0.1,110.52.27.168');
        // TOKEN 签名密钥
        self::$TOKEN_KEY = self::arrayValue($config, 'auth.token_key', 'U6rxInZ7!');
        // TOKEN 自动延期时长，每次页面跳转操作会重新生成一个延期过的 TOKEN，类似 session 的效果，只要一直操作就一直不会过期
        self::$TOKEN_REFRESH_SECOND = self::arrayValue($config, 'auth.token_referesh_second', 1800);
    }

    /**
     * 生成签名
     * @param array $data
     * @return array array(exp,sign,...)
     * @version 2017-11-3
     */
    public static function genSign(array $data = [])
    {
        $tokenInfo = array(
            "exp" => strtotime("30 minutes"), // 默认 TOKEN 有效期 30 分钟
            "aud" => self::$TOKEN_AUD,
        );
        $data = array_merge($tokenInfo, $data);
        ksort($data);
        $md5Str = md5(self::$TOKEN_KEY . json_encode($data));
        $data['sign'] = substr($md5Str, 20, 6);
        unset($data['aud']);
        return $data;
    }

    /**
     * 验证签名
     * @param array $data array(exp,sign,...)
     * @return array
     * @version 2017-11-3
     */
    public static function checkSign($data)
    {
        if (empty($data['exp']) || empty($data['sign'])) {
            return array('status' => false, 'info' => '签名内容不规范');
        }
        $exp = $data['exp'];
        if ($exp < time()) {
            return array('status' => false, 'info' => '签名已过期');
        }
        if (self::$TOKEN_AUD) {
            $audArr = explode(',', self::$TOKEN_AUD);
            $hostIp = self::getHostIP();
            if (!in_array($hostIp, $audArr)) {
                return array(
                    'status' => false,
                    'info' => '服务器不在白名单<span style="display:none">' . $hostIp . '</span>'
                );
            }
        }
        $postSign = $data['sign'];
        unset($data['sign']);
        $data['aud'] = self::$TOKEN_AUD;
        ksort($data); // aud,exp,user
        $localSign = substr(md5(self::$TOKEN_KEY . json_encode($data)), 20, 6);
        if ($localSign != $postSign) {
            return array('status' => false, 'info' => '签名非法');
        }
        return array('status' => true, 'info' => '');
    }

    /**
     * 获取表单令牌
     * @return string
     * @version 2017-11-3 由 session 改为 token
     */
    public static function getToken($data)
    {
        return base64_encode(json_encode(self::genSign($data)));
    }

    /**
     * 解析 token
     * @param string $post_token
     * @return array
     * @version 2017-11-3 added.
     */
    public static function parseToken($post_token = '')
    {
        if (!$post_token) {
            $post_token = htmlspecialchars(trim(self::input('token')));
        }
        if (!$post_token) {
            return ['status' => false, 'info' => 'TOKEN 为空'];
        }
        $json = base64_decode($post_token);
        if (false === $json) {
            return ['status' => false, 'info' => 'TOKEN 内容无效'];
        }
        $post = json_decode($json, true);
        if (!$post) {
            return ['status' => false, 'info' => 'TOKEN 格式错误'];
        }
        $sigResult = self::checkSign($post);
        if (!$sigResult['status']) {
            return $sigResult;
        }
        return ['status' => true, 'info' => $post];
    }

    /**
     * 生成新的延期过的 token
     * @param integer $exp 延期时间
     * @param string $token 旧的 token 字符串
     * @return string
     * @version 2017-11-29
     */
    public static function refreshToken($exp, $token)
    {
        $tokenInfo = self::parseToken($token);
        $data = $tokenInfo['info'];
        unset($data['sign']);
        $data['exp'] = strtotime(self::$TOKEN_EXP) + self::$TOKEN_REFRESH_SECOND;
        return self::getToken($data);
    }

    /**
     * 用户登录操作
     * @param array $admins
     * @param array $urlparams 额外的浏览器参数
     */
    public static function doLogin($admins, $urlparams = [])
    {
        $posttoken = htmlspecialchars(self::input('login_token'));
        if ($posttoken) {
            $tokenInfo = self::parseToken($posttoken);
            $goback_btn = ' <a href="?login" style="text-decoration:none;padding:2px 5px;border:solid 1px #2b6cb0;
                    background-color:#4299e1;color:#FFF;border-radius:.25rem;border-bottom-width:4px;">返回</a>';
            if (!$tokenInfo['status']) {
                exit($tokenInfo['info'] . $goback_btn);
            }
            $loginid = strval(htmlspecialchars(self::input('user')));
            $loginpwd = htmlspecialchars(self::input('pass'));
            if (!$loginid || !$loginpwd) {
                exit('表单填写不完整!' . $goback_btn);
            }
            if (!array_key_exists($loginid, $admins)) {
                exit('帐号不存在!' . $goback_btn);
            }
            if ($loginpwd != $admins[$loginid]) {
                exit('密码错误!' . $goback_btn);
            }
            $url = '?token=' . self::getToken(array('exp' => strtotime(self::$TOKEN_EXP), 'user' => $loginid));
            if ($urlparams) {
                $url .= '&' . http_build_query($urlparams);
            }
            header('location:' . $url);
        } else {
            $bootstrap = 'https://cdn.bootcss.com/twitter-bootstrap/4.4.1/css/bootstrap.min.css';
            echo '<html><head><title></title><meta charset="UTF-8" /><link href="', $bootstrap, '" rel="stylesheet" />',
            '<style>body{display:-ms-flexbox;display:flex;-ms-flex-align:center;align-items:center;',
            'padding-top:40px;padding-bottom:40px;background-color:#f5f5f5;}.form-signin{width:100%;padding:15px;',
            'margin:auto;}@media (min-width:991.98px){.form-signin{max-width:330px;}}</style></head>',
            '<body class="text-center"><form method="post" action="?action=login" class="form-signin">',
            '<h2>Welcome!</h2><div class="form-group"><input class="form-control" type="text" name="user" ',
            'placeholder="请输入用户名 / LoginID" required="required" /> <input class="form-control" ',
            'type="password" name="pass" placeholder="请输入密码 / Password" required="required" /></div>',
            '<button class="btn btn-primary btn-block" type="submit" title="Login">立即登录</button>',
            '<button class="btn btn-secondary btn-block" type="reset" title="Reset">重置</button>',
            '<input type="hidden" name="login_token" value="',
            self::getToken(array('exp' => strtotime('3 minutes'), 'action' => 'login')),
            '" />';
            if ($urlparams) {
                foreach ($urlparams as $key => $val) {
                    echo '<input type="hidden" name="', $key, '" value="', $val, '" />';
                }
            }
            echo '<p class="mt-3 text-muted">', $_SERVER['REMOTE_ADDR'], '</p>';
            echo '</form></body></html>';
        }
    }

    /**
     * 访问权限验证
     * @param array $urlparams
     */
    public static function checkLogin($urlparams = [])
    {
        $curAuthtypeId = self::$AUTH_CONFIG['current_authtype'];
        $curAuthStatus = true; // 权限验证状态
        if ($curAuthtypeId == 2 || $curAuthtypeId == 3) { // IP 验证
            $http_host = $_SERVER['REMOTE_ADDR'];
            if (!in_array($http_host, self::$AUTH_CONFIG['ips'])) {
                $curAuthStatus = false; // IP 验证失败
                if ($curAuthtypeId == 2) {
                    exit('IP [' . $http_host . '] 禁止访问!');
                }
            }
        }

        $loginid = false; // 用户登录 ID
        $loginexp = ''; // token 过期时间提示
        $token = htmlspecialchars(trim(self::input('token'))); // 用户登录会话 Token
        if ($curAuthtypeId == 1 || ($curAuthtypeId == 3 && !$curAuthStatus)) { // 帐号密码验证
            if ($token) { // 解析登录会话 token 信息
                $tokenInfo = self::parseToken($token);
                if ($tokenInfo['status']) {
                    $loginid = $tokenInfo['info']['user'];
                    $loginexp = date('Y-m-d H:i:s', $tokenInfo['info']['exp']);
                    // 自动续期功能 2017-11-29
                    if (self::$TOKEN_REFRESH_SECOND > 0) {
                        $refreshToken = self::refreshToken(self::$TOKEN_REFRESH_SECOND, $token);
                        $token = $refreshToken;
                    }
                } else {
                    header('location:?msg=' . $tokenInfo['info']);
                    exit();
                }
            }
            // 注销登录
            if (self::input('action') == 'logout') {
                header('location:?msg=logout-success');
                exit;
            }
            if (!$loginid) {
                self::doLogin(self::$AUTH_CONFIG['admins'], $urlparams);
                exit;
            }
        }
        self::$LOGIN_RESULT = compact('loginid', 'token', 'loginexp');
    }

    /**
     * 返回服务器 IP
     * 参考：SERVER_NAME 和 HTTP_HOST 的区别 (http://blog.sina.com.cn/s/blog_6d96d3160100q39x.html)
     * @version 1.0 2017-10-13 Added.
     */
    public static function getHostIP()
    {
        //return $_SERVER['HTTP_HOST']; // 有服务器域名则优先返回域名，否则返回服务器 ip
        return gethostbyname($_SERVER['SERVER_NAME']); // 返回服务器IP
    }
    /**
     * 获取数组中的值
     * @param array $array 数组
     * @param string $path 节点路径，例如：a.b.c
     * @param mixed $defv 默认值
     * @return mixed 返回数组中的值
     */
    public static function arrayValue($array, $path, $defv = null)
    {
        $paths = explode('.', $path);
        foreach ($paths as $node) {
            if (isset($array[$node])) {
                $array = $array[$node];
            } else {
                $array = $defv;
            }
        }
        return $array;
    }
    /**
     * 获取用户输入
     * @param string $name
     * @param mixed $defv
     * @param integer $filter
     * @param boolean $istrim
     * @return mixed
     */
    public static function input($name, $defv = '', $filter = FILTER_SANITIZE_STRING, $trim = true)
    {
        if (isset($_REQUEST[$name])) {
            $defv = $filter ? filter_var($_REQUEST[$name], FILTER_SANITIZE_STRING) : $_REQUEST[$name];
            if ($trim) {
                $defv = trim($defv);
            }
        }
        return $defv;
    }
}
