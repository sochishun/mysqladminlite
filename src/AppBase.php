<?php

namespace mysqladminlite;

use mysqladminlite\lib\ConfigHandler;
use mysqladminlite\lib\SqlHandler;

/**
 * 应用程序基类
 * 说明：为了支持合并代码到 SPA 文件，由 基类 类型改为 普通类 类型，protected 约束改为 public 约束。
 */
class AppBase
{
    /**
     * 应用程序名称
     */
    const APP_NAME = 'mysqladminlite';

    /**
     * 应用程序版本号
     */
    const APP_VERSION = '5.5.1';

    protected $_env = '';
    protected $_db = '';
    protected $_object = '';
    protected $_objType = '';
    protected $_sql = '';
    protected $_action = '';

    /**
     * 全局配置
     */
    protected $_config = [];
    /**
     * 当前数据库环境配置内容
     */
    protected $_envInfo = [];
    /**
     * 数据库服务器地址
     */
    protected $_host = '';
    /**
     * 数据库服务器端口
     */
    protected $_port = '';
    /**
     * 数据库配置的数据表前缀
     */
    protected $_tablePrefix = '';
    /**
     * 是否严格操作模式，如果是生产环境，建议设值 true。
     * 严格操作模式会在界面上对重要操作进行操作确认，避免人为失误造成的生产事故。
     */
    protected $_strictMode = false;

    protected $_pdoAdapter = null;

    protected $_searchInfo = ['word' => '', 'range' => ''];

    // get or set arrtibutes

    public function getEnv()
    {
        return $this->_env;
    }
    public function setEnv($value)
    {
        $this->_env = $value;
    }

    public function getDb()
    {
        return $this->_db;
    }
    public function setDb($value)
    {
        $this->_db = $value;
    }

    public function getObject()
    {
        return $this->_object;
    }
    public function setObject($value)
    {
        $this->_object = $value;
    }

    public function getObjType()
    {
        return $this->_objType;
    }
    public function setObjType($value)
    {
        $this->_objType = $value;
    }

    public function getSql()
    {
        return $this->_sql;
    }
    public function setSql($value)
    {
        $this->_sql = $value;
    }

    public function getAction()
    {
        return $this->_action;
    }
    public function setAction($value)
    {
        $this->_action = $value;
    }
    public function getConfig()
    {
        return $this->_config;
    }
    public function setConfig($value)
    {
        $this->_config = $value;
    }

    public function getEnvInfo()
    {
        return $this->_envInfo;
    }
    public function setEnvInfo($value)
    {
        $this->_envInfo = $value;
    }

    public function getHost()
    {
        return $this->_host;
    }
    public function setHost($value)
    {
        $this->_host = $value;
    }

    public function getPort()
    {
        return $this->_port;
    }
    public function setPort($value)
    {
        $this->_port = $value;
    }

    public function getTablePrefix()
    {
        return $this->_tablePrefix;
    }
    public function setTablePrefix($value)
    {
        $this->_tablePrefix = $value;
    }

    public function getStrictMode()
    {
        return $this->_strictMode;
    }
    public function setStrictMode($value)
    {
        $this->_strictMode = $value;
    }

    public function getPdoAdapter()
    {
        return $this->_pdoAdapter;
    }
    public function setPdoAdapter($value)
    {
        $this->_pdoAdapter = $value;
    }
    public function getSearchInfo()
    {
        return $this->_searchInfo;
    }
    public function setSearchInfo($searchInfo)
    {
        $this->_searchInfo = $searchInfo;
    }


    // 常用路径定义
    const DS = DIRECTORY_SEPARATOR;
    static $APP_ROOT_DIR = './mysqladminlite/';
    static $APP_RUNTIME_DIR = './runtime/mysalapp/';
    static $APP_CONFIG_ROOT = '';
    static $APP_EXPORTS_ROOT = '';
    static $APP_FUNCTIONS_ROOT = '';
    static $APP_MIGRATIONS_ROOT = '';
    static $APP_SEEDS_ROOT = '';
    static $APP_SQLS_ROOT =  '';
    static $APP_STUBS_ROOT = '';

    /**
     * 程序初始化
     */
    public function init($config)
    {

        // 如果外部配置文件存在，则外部配置文件会覆盖内置配置文件
        // 外部配置文件可以通过命令行：php mysqladminlite.php init 自动生成
        // 优先读取私有配置
        $privateConfigPath = self::$APP_CONFIG_ROOT . 'private/config.php';
        if (file_exists($privateConfigPath)) {
            ConfigHandler::loadExtConfig($config, $privateConfigPath);
        } else {
            ConfigHandler::loadExtConfig($config, self::$APP_CONFIG_ROOT . 'config.php');
        }

        // 解析环境中的特殊配置，如第三方app外部环境和端口转发配置，解析后返回规范化的配置内容
        $config['environments'] = ConfigHandler::parseEnvironments($config['environments']);

        $this->_config = $config;
    }
    /**
     * 设置系统目录路径
     */
    public function setSysPaths($appRootDir, $appRuntimeDir)
    {
        if (!is_dir($appRuntimeDir)) {
            @mkdir($appRuntimeDir, 0777, true);
        }
        // 设置数据文件根目录路径
        self::$APP_ROOT_DIR = $appRootDir;
        self::$APP_RUNTIME_DIR = $appRuntimeDir;
        self::$APP_CONFIG_ROOT = self::$APP_RUNTIME_DIR . 'config/';
        self::$APP_EXPORTS_ROOT = self::$APP_RUNTIME_DIR . 'exports/';
        self::$APP_FUNCTIONS_ROOT = self::$APP_RUNTIME_DIR . 'xfns/';
        self::$APP_MIGRATIONS_ROOT = self::$APP_RUNTIME_DIR . 'migrations/';
        self::$APP_SEEDS_ROOT = self::$APP_RUNTIME_DIR . 'seeds/';
        self::$APP_SQLS_ROOT = self::$APP_RUNTIME_DIR . 'sqls/';
        self::$APP_STUBS_ROOT = self::$APP_RUNTIME_DIR . 'stubs/';
    }

    /**
     * 从 SQL 语句解析出 object 对象
     */
    public function parseSql()
    {
        if (!$this->_object && $this->_sql) {
            $objInfo = SqlHandler::getObjectBySql($this->_sql);
            if ($objInfo['object']) {
                $this->_object = $objInfo['object'];
                $this->_objType = $objInfo['type'];
            }
        }
    }

    /**
     * 解析当前环境节点的配置内容
     */
    public function parseEnv($token, $isCli = false)
    {
        $env = $this->_env;
        // 数据库配置解析
        $server = ConfigHandler::getCurrentEnv($this->_config, $env, $token, $isCli);
        // 解析 host 和 port
        $this->_host = $server['host'];
        $this->_port = $server['port'];
        // 解析表名前缀
        $this->_tablePrefix = isset($server['prefix']) ? $server['prefix'] : '';
        $this->_strictMode = isset($server['strict_mode']) && $server['strict_mode'];
        // 解析当前数据库名称
        if (!$this->_db && !empty($server['database'])) {
            $this->_db = $server['database'];
        }
        if ($this->_db) {
            $server['database'] = $this->_db;
        }
        $this->_envInfo = $server;
        return $server;
    }
    /**
     * 获取配置项目的值
     * @param string $path 路径，以英文句号隔开
     * @param mixed $defv 默认值
     * @return mixed
     */
    public function getConfigValue($path, $defv = '')
    {
        $paths = explode('.', $path);
        $value = $this->_config;
        foreach ($paths as $name) {
            if (isset($value[$name])) {
                $value = $value[$name];
            } else {
                $value = null;
                break;
            }
        }
        return is_null($value) ? $defv : $value;
    }
    /**
     * 类自动加载功能
     */
    private function splautoload()
    {
        // 启用自动加载类文件功能 new \app\seed\TestSeeder();
        spl_autoload_register(function ($class) {
            // 自动加载的命名空间的目录映射 ns => dir
            $dirMap = [
                'mysqladminlite' => self::$APP_ROOT_DIR,
                'MySQLAdminLite' => self::$APP_ROOT_DIR . 'src',
            ];
            // 解析类文件路径 mysal\stubs\fastlara\admin
            $class = trim($class, '\\');
            $pos = strpos($class, '\\');
            $vendor = substr($class, 0, $pos); // 顶级命名空间
            $vendorDir = $dirMap[$vendor]; // 文件目录
            $file = $vendorDir . str_replace('\\', '/', substr($class, $pos)) . '.php'; // 文件路径
            /* 加载文件 */
            if (file_exists($file)) {
                include $file;
            }
        }, true, true);
    }
}
