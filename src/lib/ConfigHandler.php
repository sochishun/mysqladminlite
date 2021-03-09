<?php

namespace mysqladminlite\lib;

/**
 * 服务器配置通用类
 * @version 2017-12-19 Added.
 */
class ConfigHandler
{
    public function getTemplateContent($file)
    {
        $filename = implode(DIRECTORY_SEPARATOR, [dirname(dirname(__DIR__)), 'assets', 'config_main.php.example']);
        if (file_exists($filename)) {
            return str_replace('{file}', $file, file_get_contents($filename));
        }
        return '';
    }
    /**
     * 加载外部配置文件
     * 如果外部配置文件存在，则外部配置文件会覆盖内置配置文件
     * @param array &$config
     * @param string $file 外部配置文件路径
     */
    public static function loadExtConfig(&$config, $file = './runtime/mysalapp/config/config.php')
    {
        if (!file_exists($file)) {
            return;
        }
        $exconfig = require $file;
        if (!is_array($exconfig)) {
            return;
        }
        // 数组合并只是简单粗暴地合并第一维键值，所以需要额外处理第二维和第三维的数据
        self::arrayMigrate($config, $exconfig);
        $config = array_merge($config, $exconfig);
    }
    /**
     * 获取配置内容中的当前数据库服务器配置
     * @param array $config
     * @param string $curenv 当前服务器名称
     * @param boolean $isCli 是否Cli环境
     * @return array
     * @version 2017-12-15
     */
    public static function getCurrentEnv($config, $curenv, $token, $isCli = false)
    {
        if (!$curenv || empty($config['environments'][$curenv])) {
            echo '数据库服务器环境 [' . $curenv . '] 无效！';
            if (isset($config['environments']['default_database'])) {
                unset($config['environments']['default_database']);
            }
            $envs = array_keys($config['environments']);
            if ($envs) {
                if ($isCli) {
                    echo PHP_EOL, '以下服务器环境可以正常使用：', PHP_EOL;
                    echo implode(', ', $envs);
                } else {
                    echo '<br />以下服务器环境可以正常使用：';
                    foreach ($envs as $envName) {
                        echo '<a href="?token=', $token, '&env=', $envName, '">', $envName, '</a> ';
                    }
                }
            } else {
                echo '（未找到可用的数据库环境配置信息，请检查配置文件）';
            }
            exit();
        }
        return $config['environments'][$curenv]; // 当前数据库服务器配置
    }

    /**
     * 解析端口转发配置文件内容并融合到当前环境节点
     * @param array $config
     * @return array
     */
    private static function mergeRinetd($config)
    {
        if (!isset($config['rinetd'])) {
            return $config;
        }
        $rinetd = $config['rinetd'];
        // 去除扩展标签，规范配置内容
        unset($config['rinetd']);

        if (!is_array($rinetd) || !empty($rinetd)) {
            return $config;
        }
        if (!empty($rinetd['host'])) {
            // 返回服务器 IP，参考：SERVER_NAME 和HTTP_HOST的区别 (http://blog.sina.com.cn/s/blog_6d96d3160100q39x.html)
            $host_ip = gethostbyname($_SERVER['SERVER_NAME']);
            // 如果服务器 IP 和端口映射服务器一样，则自动忽略端口映射配置，提高性能 2017-10-13
            if ($host_ip == $rinetd['host']) {
            } else {
                if (!empty($rinetd['port'])) {
                    $config['port'] = $rinetd['port'];
                }
            }
        }
        return $config;
    }

    /**
     * 解析外部配置文件内容并融合到当前环境节点
     * @param array $config
     * @return array
     * @version 2017-12-6
     */
    public static function mergeExtFile(array $config)
    {
        if (!isset($config['extfile']['path']) || !file_exists($config['extfile']['path'])) {
            return $config;
        }
        $extconf = $config['extfile'];
        // 去除扩展标签，规范配置内容
        unset($config['extfile']);

        // 字段映射配置 官方名称=>外部配置名称
        $map = isset($extconf['map']) ? $extconf['map'] : [];
        // 外部配置文件规范标准
        $standard = isset($map['standard']) ? $map['standard'] : '';
        if ($standard) {
            $stdmaps = [
                'thinkphp5.0' => [
                    'startline' => '[database]',
                    'host' => 'hostname', 'user' => 'username', 'password' => 'password', 'port' => 'hostport',
                    'database' => 'database', 'prefix' => 'prefix'
                ],
                'thinkphp6.0' => [
                    'startline' => '[DATABASE]',
                    'host' => 'HOSTNAME', 'user' => 'USERNAME', 'password' => 'PASSWORD', 'port' => 'HOSTPORT',
                    'database' => 'DATABASE', 'prefix' => 'prefix', 'charset' => 'CHARSET'
                ],
                'laravel' => [
                    'startline' => 'DB_CONNECTION=mysql',
                    'host' => 'DB_HOST', 'user' => 'DB_USERNAME', 'password' => 'DB_PASSWORD', 'port' => 'DB_PORT',
                    'database' => 'DB_DATABASE',
                ],
            ];
            if (isset($stdmaps[$standard])) {
                $map = $stdmaps[$standard];
            }
        }
        $filepath = $extconf['path'];
        // 判断外部配置文件的文件格式
        if ('php' == pathinfo($filepath, PATHINFO_EXTENSION)) {
            $content = include $filepath;
        } else {
            // txt类型的文件，比如 .env,.conf
            $content = [];
            $lines = file($filepath);
            // 配置内容读取的起始行
            $linestart = '';
            $isSkip = false;
            if (!empty($map['startline'])) {
                $linestart = trim($map['startline']);
                if ($linestart) {
                    $isSkip = true;
                }
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($isSkip) {
                    if ($line = $linestart) {
                        $isSkip = false;
                    }
                    continue;
                }
                if (!strpos($line, '=') || $isSkip) {
                    continue;
                }
                list($name, $value) = array_map('trim', explode('=', $line, 2));
                $content[$name] = $value;
            }
        }
        // 判断外部配置内容是否为空
        if (!$content) {
            return $config;
        }
        if ($map) {
            $outdata = [];
            foreach ($map as $orginame => $extname) {
                if (array_key_exists($extname, $content)) {
                    $outdata[$orginame] = $content[$extname];
                }
            }
        } else {
            $outdata = $content;
        }
        return array_merge($config, $outdata);
    }

    /**
     * 规范化环境配置内容
     */
    public static function parseEnvironments($environments)
    {
        $outdata = [];
        foreach ($environments as $name => $data) {
            if (!$data) {
                continue;
            }
            // 只有 default_database 节点的值是字符串类型，其余都是环境节点的数组配置内容
            if (!is_array($data)) {
                $outdata[$name] = $data;
                continue;
            }
            // 读取外部配置内容
            if (isset($data['extfile']['path']) && (!isset($data['extfile']['enable']) || $data['extfile']['enable'])) {
                $data = self::mergeExtFile($data);
            }
            // 如果节点配置没有设置密码内容则视为无效配置
            if (empty($data['password'])) {
                continue;
            }
            $outdata[$name] = self::mergeRinetd($data);
        }
        return $outdata;
    }
    /**
     * 迁移数组，把多维数组复制到另一个数组
     * 用于多维数组合并前的预处理
     * @param array $fromData
     * @param array &$toData
     */
    public static function arrayMigrate($fromData, &$toData)
    {
        foreach ($fromData as $key => $data) {
            if (!isset($toData[$key])) {
                $toData[$key] = $data;
                continue;
            }
            if (!$data || !is_array($data)) {
                continue;
            }
            self::arrayMigrate($data, $toData[$key]);
        }
    }
}
