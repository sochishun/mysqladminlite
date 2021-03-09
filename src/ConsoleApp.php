<?php

namespace mysqladminlite;

use mysqladminlite\lib\ConfigHandler;
use mysqladminlite\lib\ConsoleHandler;
use mysqladminlite\lib\PdoAdapter;
use mysqladminlite\lib\SqlHandler;
use mysqladminlite\lib\ExportHandler;
use mysqladminlite\lib\FileHandler;
use mysqladminlite\lib\XfnHandler;
use mysqladminlite\lib\SeedHandler;
use mysqladminlite\lib\HtmlHandler;
use mysqladminlite\lib\CommonHelper;

/**
 * 命令行应用程序
 * 注意：为了生成 绿色版单页应用程序（SPA），不要使用继承类，也不要使用 Trait。
 * @since 2020-02-13
 */
class ConsoleApp
{
    /**
     * 引用控制台 Trait (use ConsoleHandler;)
     * \MySQLAdminLite\Console\ConsoleHandler
     */
    protected $ConsoleHandler;
    protected $AppBase;
    /**
     * 运行
     * @param int $argc 参数数量
     * @param array $argv 命令行参数数组
     * @param array $config 全局配置
     * @param string $appRootDir 临时数据文件根目录路径
     */
    public function run($argc, $argv, $config, $appRootDir = './mysqladminlite/', $appRuntimeDir = './runtime/mysalapp/')
    {
        $this->AppBase = new AppBase();
        // 设置系统路径
        $this->AppBase->setSysPaths($appRootDir, $appRuntimeDir);

        // __FILE_NAME__ cmdname params...
        if ($argc < 2) {
            $this->execWelcome();
        }
        // 命名名称
        $cmdname = $argv[1];
        // 解析参数数组
        $this->ConsoleHandler = new ConsoleHandler();
        $this->ConsoleHandler->init($argc, $argv);
        // $this->ConsoleHandler->getParams() = $this->parseParams($argc, $argv);
        if ($cmdname == '--help') {
            // 帮助命令 --help --output y
            exit($this->execCmdHelp());
        }
        // 返回指定命令的帮助内容
        if ($this->ConsoleHandler->hasParam('--help')) {
            exit($this->getCmdHelp($cmdname));
        }

        // 尝试读取配置文件
        $cfgFilePath = AppBase::$APP_CONFIG_ROOT . 'config.php';
        // 检测是否初始化
        if (!file_exists($cfgFilePath)) {
            $result = $this->ConsoleHandler->stdin('程序尚未初始化，立即初始化？[y/n]：');
            if ('y' == strtolower($result)) {
                $this->execInit($cfgFilePath);
            } else {
                exit('程序尚未执行初始化，请先执行命令 init 进行初始化!');
            }
        }

        // 应用初始化，加载外部配置文件等
        $this->AppBase->init($config);

        // 当前数据库名称
        $this->AppBase->setDb($this->ConsoleHandler->input('--database'));
        // 当前服务器ID
        $env = $this->ConsoleHandler->input('--env', $this->AppBase->getConfigValue('environments.default_database'));
        $this->AppBase->setEnv($env);

        $unconnectionCmds = ['serv', 'envs', 'env:info', 'pathtrans', 'template:sql', 'audit2html'];
        if (!in_array($cmdname, $unconnectionCmds)) {

            // 判断是否需要连接数据库
            if ($env) {
                // 数据库配置解析
                $server = $this->AppBase->parseEnv('', true);
                // 实例化数据库操作类
                try {
                    $this->AppBase->setPdoAdapter(new PdoAdapter($server));
                } catch (\Exception $ex) {
                    exit($ex->getMessage());
                }
            }
            if (!$this->AppBase->getPdoAdapter()) {
                exit('数据库适配器初始化失败，请检查配置文件的设置！');
            }
        }
        // 执行命令
        switch ($cmdname) {
            case 'status':
                // 查看数据库连接状态 status --env development
                $this->execStatus();
                break;
            case 'envs':
                // 查看所有可用的数据库服务器环境
                $this->execEnvs();
                break;
            case 'env:info':
                // 查看可用的数据库服务器环境配置信息
                $this->execEnvInfo($config);
                break;
            case 'serv':
                // 启动网站服务器
                $this->execServ();
                break;
            case 'mklink':
                // 创建快捷链接
                $this->execMklink();
                break;
                // 路径转换
            case 'pathtrans':
                $this->execPathtrans();
                break;
            case 'db:status':
                $this->execDbStatus();
                break;
            case 'db:variables':
                $this->execDbVariables();
                break;
            case 'db:select':
                $this->execDbSelect();
                break;
            case 'db:exec':
                $this->execDbExec();
                break;
            case 'db:compare':
                $this->execDbCompare();
                break;
            case 'migrate:create':
                $this->execMigrateCreate();
                break;
            case 'import':
            case 'source':
                $this->execSource();
                break;
            case 'import:database':
            case 'source:all':
                $this->execSourceAll();
                break;
            case 'xfn:create':
                $this->execFnxCreate();
                break;
            case 'xfn:run':
                $this->execXfnRun();
                break;
            case 'seed:create':
                $this->execSeedCreate();
                break;
            case 'seed:autocreate':
                $this->execSeedAutoCreate();
                break;
            case 'seed:run':
                $this->execSeedRun();
                break;
            case 'seed:autorun':
                $this->execSeedAutoRun();
                break;
            case 'export:database':
                $this->execExportDatabase();
                break;
            case 'export:data':
            case 'mock':
                $this->execExportData($cmdname);
                break;
            case 'export:compare':
            case 'export:schema':
                if ($cmdname == 'export:compare') {
                }
                $this->execExportSchema();
                break;
            case 'template:sql':
                $this->execTemplateSql();
                break;
            case 'stub:crud':
                if ($cmdname == 'stub:remove') {
                    $this->ConsoleHandler->inputAdd('--remove', 'y');
                }
                $this->execStubCrud();
                break;
            case 'stub:make': // 生成存根模板文件 stub:make --name controller:SampleController --project powersdk/sample --force y
            case 'stub:remove':
                if ($cmdname == 'stub:remove') {
                    $this->ConsoleHandler->inputAdd('--remove', 'y');
                }
                $this->execStubMake();
                break;
            case 'audit2html':
                $this->execAudit2Html();
                break;
            case 'buildspa':
                $this->execBuildSPA();
                break;
            default:
                echo '不支持该命令!';
                break;
        }
    }
    /**
     * 返回命令的帮助内容
     */
    protected function getCmdHelp($cmdname = '')
    {
        $helps = [
            'help' => '--help [--output y]  : 显示帮助文档',
            'init' => 'init [--force y]  : 初始化',
            'status' => 'status [--env development]  : 查看数据库连接状态',
            'envs' => 'envs : 查看所有可用的数据库服务器环境',
            'env:info' => 'env:info [--env development]  : 查看可用的数据库服务器环境配置信息',
            'serv' => 'serv --host 127.0.0.1 --port 8866  : 启动网站服务器',
            'mklink' => 'mklink --file ../mysal  : 创建软链接文件',
            'db:select' => 'db:select [--env development] --sql "select * from tbl_example limit 10;"  : ' .
                '显示数据库查询结果',
            'db:exec' => 'db:exec [--env development] --sql "update tbl_example set flag=1 where id=1;"  : 执行数据库'
                . '命令',
            'db:status' => 'db:status [--env development]  : 显示数据库状态',
            'db:variables' => 'db:variables [--env development]  : 显示数据库全局变量',
            'export:database' => 'export:database [--env development] [--database dbsample]  : 导出数据库，用于数据库'
                . '迁移',
            'export:data' => 'export:data  [--env development] --table tbl_example [--sql "select * from tbl"] '
                . '[--filetype excel|html|markdown|json|sql] : 导出数据，json 格式文件用于 mock 数据',
            'export:schema' => 'export:schema [--env development] [--table tbl_example] [--database dbsample2] '
                . '[--filetype excel|sql|html|php] [--eachfile y] [--prefix tbl_]  : 导出数据库结构数据',
            'export:compare' => 'export:compare [--env development] [--table tbl_example] [--database dbsample2] '
                . '[--eachfile y] [--prefix tbl_]  : 导出数据库结构数据',
            'source' => 'source [--env development] [--database dbsample] --file tbl_example.sql  ： 导入本地 sql 文件',
            'source:all' => 'source:all [--env development] --dir dbexample [--prefix tbl_] ： 批量导入本地 sql 文件',
            'template:sql' => 'template:sql --operate create_table|alter_table|create_view|create_routine '
                . '--file tbl_test.sql  : 创建数据库命令模板文件',
            'stub:crud' => 'stub:crud --table fa_test --project fastlara/admin --menu y --route y --force y  : 快速生成 CRUD 对应'
                . '的 MVC 文件、静态资源文件和路由配置',
            'stub:crud --mapfile' => 'stub:crud --table fa_test --project fastlara/admin --mapfile y --force y  : 生成 '
                . 'CRUD 字段与组件的映射关系的配置文件',
            'stub:crud --remove' => 'stub:crud --table fa_test --project fastlara/admin --menu y --force y --remove '
                . 'y  : 删除 CRUD 对应的 MVC 文件和静态资源文件',
            'stub:make' => 'stub:make --name controller:MyPackage/Sample --project fastlara/admin --force y  : 创建'
                . '存根模板文件',
            'stub:remove' => 'stub:remove --name controller:MyPackage/Sample --project fastlara/admin  : 删除存根模板'
                . '文件',
            'xfn:create' => 'xfn:create --file myfnx/fnsample.php  : 创建外部扩展功能函数模板文件',
            'xfn' => 'xfn --file myfnx/fnsample.php  : 运行外部扩展功能函数',
            'fastsql' => '[TODO]fastsql [--env development] --name tbl_example.simple  : 数据表快捷命令查询',
            'seed:create' => 'seed:create [--env development] [--file ExampleTableSeeder] [--count 100]'
                . '--table tbl_example  : 从数据表创建测试数据文件',
            'seed:autocreate' => 'seed:autocreate [--env development] [--prefix tbl_] [--force y] [--eltcount 10] '
                . '[--count 100] [--database dbsample]  : 自动从数据库查找表名包含 "tbl_" 且记录数量少于 10 的数据表并生成数据种子文件',
            'seed:run' => 'seed:run [--env development] --file ExampleTableSeeder [--table tbl_example] : 运行测试数据生成文件',
            'seed:autorun' => 'seed:autorun [--env development] [--database dbsample2] [--prefix tbl_] '
                . '[--eltcount 0]  : 自动检测数据表，如果数据表是空的且本地有对应的 Seeder 文件，则自动运行测试数据填充',
            'migrate:create' => '[TODO]migrate:create [--env development] [--file ExampleTable --table tbl_example] '
                . '[--prefix tbl_] --version v1b20200212  : 从数据表创建数据迁移文件',
            'migrate:exportsql' => '[TODO]migrate:exportsql [--file ExampleTable] [--prefix Tbl] '
                . '--version v1b20200212  : 从数据表创建数据迁移文件',
            'migrate' => '[TODO]migrate [--env development] --file ExampleTable --version v1b20200213  : 运行数据迁移'
                . '文件',
            'migrate:rollback' => '[TODO]migrate:rollback  [--env development] --file ExampleTable '
                . '--version v1b20200212  : 运行数据迁移的回滚操作',
            'audit2html' => 'audit2html  : Maraidb 数据库审计文件转换到 HTML 文件',
            'buildspa' => 'buildspa --file mysal.php : 创建单页应用程序文件',

        ];
        if ($cmdname) {
            return isset($helps[$cmdname]) ? $helps[$cmdname] : "没有 {$cmdname} 命令的帮助文档！";
        } else {
            return $helps;
        }
    }
    /**
     * 显示帮助文档
     * @param string $cmdname
     */
    protected function execCmdHelp()
    {
        echo '输入命令 [--help --output y] 可以将帮助文档保存到本地文件。', PHP_EOL, PHP_EOL;
        $content = '目录结构：' . PHP_EOL
            . 'sal  : 初始化后自动生成的应用程序根目录' . PHP_EOL
            . '-- exports  : 导出文件目录' . PHP_EOL
            . '-- functions  : 外部功能函数目录' . PHP_EOL
            . '-- migrations  : 数据库迁移文件目录' . PHP_EOL
            . '-- seeds  : 数据种子文件目录' . PHP_EOL
            . '-- sqls  : SQL 脚本文件目录' . PHP_EOL
            . '-- stubs  : Stub 存根模板文件目录' . PHP_EOL
            . '-- config.php  : 外部配置文件' . PHP_EOL . PHP_EOL
            . '命令示例：' . PHP_EOL;
        $cmdHelps = $this->getCmdHelp();
        foreach ($cmdHelps as $cmdstr) {
            $content .= $cmdstr . PHP_EOL;
        }
        echo $content;
        if ('y' == strtolower($this->ConsoleHandler->input('--output'))) {
            try {
                $filename = AppBase::$APP_EXPORTS_ROOT . 'help.txt';
                file_put_contents($filename, $content);
                echo PHP_EOL, '帮助文档保存成功！', PHP_EOL, '路径：', $filename;
            } catch (\Exception $ex) {
                echo $ex->getMessage();
            }
        }
    }
    /**
     * 执行初始化命令
     * @param string $cfgFilePath
     */
    protected function execInit($cfgFilePath)
    {
        // 是否强制执行    
        $isForce = $this->ConsoleHandler->checkIsForce();
        // 排版变量
        $tab = "\t";
        $tab2 = "\t\t";
        $tab3 = "\t\t\t";
        $tab4 = "\t\t\t\t";
        $tab5 = "\t\t\t\t\t";
        $ln = PHP_EOL;
        try {
            if (FileHandler::ensureDir(AppBase::$APP_ROOT_DIR)) {
                FileHandler::ensureDir(AppBase::$APP_CONFIG_ROOT);
                FileHandler::ensureDir(AppBase::$APP_EXPORTS_ROOT);
                FileHandler::ensureDir(AppBase::$APP_MIGRATIONS_ROOT);
                FileHandler::ensureDir(AppBase::$APP_SEEDS_ROOT);
                FileHandler::ensureDir(AppBase::$APP_SQLS_ROOT);
            }
            // 生成配置文件
            if (!file_exists($cfgFilePath) || $isForce) {
                $handler = new ConfigHandler();
                $content = $handler->getTemplateContent('');
                file_put_contents($cfgFilePath, $content);
                echo '初始化成功！您需要修改配置文件信息才能正常使用！', PHP_EOL;
            }
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }
    /**
     * 执行 欢迎页面 命令
     */
    private function execWelcome()
    {
        echo '欢迎使用 ', AppBase::APP_NAME, ' ', AppBase::APP_VERSION, PHP_EOL;
        echo '查看使用帮助，请输入命令：php mysqladminlite.php --help', PHP_EOL;
        // 显示本地时间
        HtmlHandler::showLocaltime(date('Y 年 m 月 d 日 H 时 i 分 s 秒 星期')
            . '{{weekday}} 第 {{yearweek}} / {{yearweeks}} 周 第 {{yearday}} / {{yeardays}} 天' . PHP_EOL . '[{{motto}}]');
        exit();
    }
    /**
     * 执行 查看状态 命令
     */
    private function execStatus()
    {
        try {
            $result = $this->AppBase->getPdoAdapter()->find('select version() as ver');
            echo '数据库连接成功！', PHP_EOL, '数据库环境：', $this->AppBase->getEnv(), PHP_EOL;
            echo '数据库名称：', $this->AppBase->getPdoAdapter()->getEnvInfo('database'), PHP_EOL, '数据库版本：', $result['ver'];
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }
    /**
     * 执行 查看所有可用服务器环境 命令
     */
    private function execEnvs()
    {
        $envs = array_keys($this->AppBase->getConfigValue('environments'));
        if ($envs) {
            echo '以下服务器环境可以正常使用：', PHP_EOL;
            echo implode(', ', $envs);
        } else {
            echo '没有可用的数据库服务器环境。', PHP_EOL;
        }
    }
    /**
     * 执行 查看服务器环境配置信息 命令
     */
    private function execEnvInfo($config)
    {
        $env = $this->ConsoleHandler->input('--env');
        if ($this->AppBase->getConfigValue('environments.' . $env)) {
            $envInfo = $this->AppBase->getConfigValue('environments.' . $env);
            var_export($envInfo);
        } else {
            if (isset($config['environments'][$env])) {
                $envInfo = $config['environments'][$env];
                var_export($envInfo);
            } else {
                echo '数据库服务器环境 ', $env, ' 不存在！。', PHP_EOL;
            }
        }
    }
    /**
     * 执行 启动应用服务器 命令
     */
    private function execServ()
    {
        $host = $this->ConsoleHandler->input('host', '127.0.0.1');
        $port = $this->ConsoleHandler->input('port', '8866');
        $url = sprintf('%s:%d', $host, $port);
        echo '服务器已启动，请在浏览器访问：http://', $url, PHP_EOL;
        passthru('php -S ' . $url);
    }
    /**
     * 执行 创建快捷方式 命令
     */
    private function execMklink()
    {
        $filename = $this->ConsoleHandler->inputRequired('--file');
        $content = <<<tpl
        <?php
        /**
        命令行快捷方式
        使用方法：
        1. 把本文件复制到指定目录
        2. 修改第10行的目录路径
        3. 键入命令：php mysal --help
        */
        chdir('./public');
        require 'mysqladminlite.php';
        tpl;
        file_put_contents($filename, $content);
        echo '创建快捷文件成功，路径：', $filename, PHP_EOL;
    }
    /**
     * 执行 路径转换 命令
     */
    private function execPathtrans()
    {
        $path = $this->ConsoleHandler->input('--path');
        $node = $this->ConsoleHandler->input('--node');
        $test = $this->ConsoleHandler->input('--test');
        $ds = $this->ConsoleHandler->input('--ds', DS);
        if (!$path || !$node) {
            exit('--path 或 --node 参数无效！');
        }
        $pos = strrpos($path, $ds . $node . $ds);
        if ($pos) {
            $count = substr_count(substr($path, $pos + 1), $ds);
            echo '路径转换结果：', PHP_EOL, '节点 ', $node, ' 在全路径：', $path, ' 的相对路径：', PHP_EOL;
            $path = str_repeat('../', $count);
            echo $path, PHP_EOL;
            if ($test) {
                try {
                    $filepath = $path . 'pathtrans-test.txt';
                    file_put_contents($filepath, 'success');
                    echo '创建测试文件成功！路径：', $filepath, PHP_EOL;
                } catch (\Exception $e) {
                    echo '创建测试文件失败：', $e->getMessage(), PHP_EOL, '路径：', $filepath, PHP_EOL;
                }
            }
        } else {
            exit('没有找到节点信息！' . PHP_EOL);
        }
    }
    /**
     * 执行 查看当前数据库状态 命令
     */
    private function execDbStatus()
    {
        // 查询数据库服务器状态 db:[status|variables] --env development
        $result = $this->AppBase->getPdoAdapter()->query('show status;');
        foreach ($result as $row) {
            echo implode(' = ', $row), PHP_EOL;
        }
    }
    /**
     * 执行 查看当前数据库变量 命令
     */
    private function execDbVariables()
    {
        $result = $this->AppBase->getPdoAdapter()->getDbVariables();
        foreach ($result as $row) {
            echo implode(' = ', $row), PHP_EOL;
        }
    }
    /**
     * 执行 数据库查询 命令（注意 SQL 命令要加引号）
     */
    private function execDbSelect()
    {
        // db:select --env development --sql "select * from tbl_example limit 10;"
        $sql = $this->ConsoleHandler->inputRetry('--sql', '请输入 SQL 命令');
        try {
            $result = $this->AppBase->getPdoAdapter()->query($sql);
        } catch (\PDOException $ex) {
            exit($ex->getMessage());
        }
        if (!$result) {
            exit('没有查询到任何数据！' . PHP_EOL);
        }
        $i = 0;
        foreach ($result as $row) {
            $i++;
            echo PHP_EOL, '# [', $i, '] ---------------------------------------', PHP_EOL;
            foreach ($row as $k => $v) {
                echo $k, ' = ', $v, PHP_EOL;
            }
        }
    }
    /**
     * 执行 数据库 exec 命令
     */
    private function execDbExec()
    {
        // db:exec --env development --sql "update tbl_example set flag=1 where id=1;"
        $sql = $this->ConsoleHandler->inputRetry('--sql', '请输入 SQL 命令');
        try {
            $result = $this->AppBase->getPdoAdapter()->exec($sql);
        } catch (\PDOException $ex) {
            exit($ex->getMessage());
        }
        echo '受影响行数：', $result, PHP_EOL;
    }
    /**
     * 执行 对比数据库架构 命令
     */
    private function execDbCompare()
    {
        $db = $this->ConsoleHandler->inputRequired('--db');
        // 要修改的数据库
        $file0 = AppBase::$APP_EXPORTS_ROOT . "{$db}/{$db}0.php";
        // 参照的数据库
        $file1 = AppBase::$APP_EXPORTS_ROOT . "{$db}/{$db}1.php";
        if (!file_exists($file0)) {
            exit('目标数据库文件不存在！路径：' . $file0);
        }
        if (!file_exists($file1)) {
            exit('参照数据库文件不存在！路径：' . $file1);
        }
        $map0 = require $file0;
        $map1 = require $file1;
        // 要新增的数据库
        $addTables = array_diff_key($map1, $map0);
        // 要删除的数据库
        $delTables = array_diff_key($map0, $map1);
        $sqls = [];
        if ($delTables) {
            foreach ($delTables as $table => $col) {
                $sql = "drop table if exists `{$table}`;";
                $sqls[] = $sql . PHP_EOL;
            }
        }
        if ($addTables) {
            foreach ($addTables as $table => $cols) {
                $sql = '';
                foreach ($cols as $field => $col) {
                    $sql .= SQLHandler::makeColumnSql($col) . ',';
                }
                $sql = "create table `{$table}` (" . substr($sql, 0, -1) . ') ENGINE=InnoDB default CHARSET=utf8;';
                $sqls[] = $sql . PHP_EOL;
            }
        }
        // 字段比对
        foreach ($map0 as $table => $cols0) {
            if (!isset($map1[$table])) {
                continue;
            }
            $cols1 = $map1[$table];
            // 要新增的字段
            $colsAdd = array_diff_key($cols0, $cols1);
            if ($colsAdd) {
                $sql = '';
                foreach ($colsAdd as $colAdd) {
                    $sql .= 'add ' . SQLHandler::makeColumnSql($colAdd) . ',';
                }
                $sql = "alter table {$table} " . substr($sql, 0, -1) . ';';
                $sqls[] = $sql . PHP_EOL;
            }
            // 要删除的字段
            $colsDel = array_diff_key($cols1, $cols0);
            if ($colsDel) {
                $colsDel = array_keys($colsDel);
                $sql = "alter table {$table} drop column `" . implode('`,`', $colsDel) . '`;';
                $sqls[] = $sql . PHP_EOL;
                foreach ($colsDel as $fieldDel) {
                    unset($cols0[$fieldDel]);
                }
            }
            // 要修改的字段
            foreach ($cols0 as $field0 => $col0) {
                if (!isset($cols1[$field0])) {
                    continue;
                }
                $col1 = $cols1[$field0];
                if (md5(serialize($col0)) != md5(serialize($col1))) {
                    $sql = "alter table {$table} modify " . SQLHandler::makeColumnSql($col1) . ';';
                    $sqls[] = $sql . PHP_EOL;
                }
            }
        }
        $filename = AppBase::$APP_EXPORTS_ROOT . "{$db}/{$db}-compare.sql";
        file_put_contents($filename, implode(PHP_EOL, $sqls));
        echo '数据库对比文件生成成功，路径：', $filename, PHP_EOL;
    }
    /**
     * 执行 生成迁移文件 命令
     */
    private function execMigrateCreate()
    {
        //  migrate:create --file ExampleTable --env development --table tbl_example
        $file = $this->ConsoleHandler->input(['-f', '--file']);
        $table = $this->ConsoleHandler->input(['-t', '--table']);

        // 如果没有设置文件名参数，则自动从数据表生成文件名
        if (!$file && $table) {
            $file = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
        }
        if (!$file) {
            exit('--file 参数或 --table 参数至少输入一个！');
        }
        if (strpos($file, '.')) {
            $classname = substr($file, 0, strpos($file, '.'));
        } else {
            $classname = $file;
            $file .= '.php'; // 如果文件名没有后缀则自动加文件名后缀
        }
        $filepath = AppBase::$APP_MIGRATIONS_ROOT . date('YmdHis') . '_' . $file;
        if (file_exists($filepath) && !$this->ConsoleHandler->checkIsForce()) {
            exit('数据种子文件已存在，如需覆盖，请使用参数 --force y。' . PHP_EOL . '路径：' . $filepath . PHP_EOL);
        }
        $marginfw = $this->AppBase->getConfigValue('migration_framework');
        $exportHandler = new ExportHandler($this->AppBase->getPdoAdapter());
        $exportHandler->exportTableSchemaAsMigrateForCli($this->AppBase->getDb(), $marginfw, $table, $filepath);
        echo PHP_EOL, 'success!';
    }
    /**
     * 执行 运行 SQL 脚本文件 命令
     */
    private function execSource()
    {
        // source --env development --file tbl_example.sql
        $file = $this->ConsoleHandler->inputRequired('--file');
        if (!strpos($file, '.sql')) {
            $file .= '.sql';
        }
        $file = AppBase::$APP_SQLS_ROOT . $file;
        if (!file_exists($file)) {
            exit('文件不存在! 文件路径：' . $file);
        }
        $sqlstmt = file_get_contents($file); // 普通命令解析
        $sqlstmts = SqlHandler::parseSqlStatement(explode("\n", $sqlstmt)); // textarea回车符号是\r,和OS平台无关
        $handler = new ExportHandler($this->AppBase->getPdoAdapter());
        $handler->showQueryResultForCli($sqlstmts, $this->AppBase->getEnv(), $this->AppBase->getDb());
    }
    /**
     * 执行 批量导入本地 SQL 文件 命令
     */
    private function execSourceAll()
    {
        // source:all [--env development] --dir dbexample
        $prefix = $this->ConsoleHandler->input('--prefix');
        $dir = $this->ConsoleHandler->input('--dir');
        $database = $this->ConsoleHandler->input('--database');
        if ($dir && !$database) {
            $database = strrpos($dir, '/') ? substr($dir, strrpos($dir, '/')) : $dir;
        }
        if (!$dir && $database) {
            $dir = 'database/' . $database;
        }
        $dir = $dir ? (AppBase::$APP_SQLS_ROOT . FileHandler::ensureSuffix($dir, '/')) : AppBase::$APP_SQLS_ROOT;
        if (!is_dir($dir)) {
            exit('目录不存在! 目录路径：' . $dir);
        }
        $files = FileHandler::scandir($dir, false, ['sql'], $prefix);
        if (!$files) {
            exit('操作取消，原因是：没有任何文件！');
        }
        $hasSchemaFile = false;
        $schemafile = $database . '.sql';
        $i = 1;
        foreach ($files as $file => $filepath) {
            if ($file == $schemafile) {
                $hasSchemaFile = true;
                continue;
            }
            echo $i, '. ', $file, PHP_EOL;
            $i++;
        }
        if ($hasSchemaFile) {
            $i++;
            echo '0. ', $schemafile, PHP_EOL;
        }
        echo '即将导入以上 ', ($i - 1), ' 个 SQL 文件, ';
        if ('yes' != $this->ConsoleHandler->stdin('确定导入请输入 [yes]：')) {
            exit('用户取消操作！');
        }
        $handler = new ExportHandler($this->AppBase->getPdoAdapter());
        if ($hasSchemaFile) {
            $sqlstmt = file_get_contents($dir . $schemafile); // 普通命令解析
            $sqlstmts = SqlHandler::parseSqlStatement(explode("\n", $sqlstmt)); // textarea回车符号是\r,和OS平台无关
            $handler->showQueryResultForCli($sqlstmts, $this->AppBase->getEnv(), $this->AppBase->getDb());
            echo '数据库架构文件导入成功。', PHP_EOL;
        }
        foreach ($files as $file => $filepath) {
            if ($file == $schemafile) {
                continue;
            }
            $sqlstmt = file_get_contents($filepath); // 普通命令解析
            $sqlstmts = SqlHandler::parseSqlStatement(explode("\n", $sqlstmt)); // textarea回车符号是\r,和OS平台无关
            $handler->showQueryResultForCli($sqlstmts, $this->AppBase->getEnv(), $this->AppBase->getDb());
        }
        echo '批量导入成功！';
    }
    /**
     * 执行 创建外部扩展功能函数模板文件 命令
     */
    private function execFnxCreate()
    {
        // xfn:create --file vendor/fnsample.php
        $file = $this->ConsoleHandler->inputRequired('--file');
        $filename = AppBase::$APP_FUNCTIONS_ROOT . FileHandler::ensureSuffix($file, '.php');
        if (file_exists($filename) && !$this->ConsoleHandler->checkIsForce()) {
            exit('外部扩展功能函数模板文件已存在，如需覆盖，请使用参数 --force y。' . PHP_EOL . '路径：' . $filename . PHP_EOL);
        }
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }
        $handler = new XfnHandler();
        $content = $handler->getTemplateContent($file);
        file_put_contents($filename, $content);
        echo '外部扩展功能函数模板文件创建成功！' . PHP_EOL . '路径：' . $filename;
    }
    /**
     * 执行 运行外部扩展功能函数 命令
     */
    private function execXfnRun()
    {
        // xfn --file vendor/fnsample.php
        $file = $this->ConsoleHandler->inputRequired('--file');
        $fn = $this->ConsoleHandler->input('--function', 'run');
        $filename = AppBase::$APP_FUNCTIONS_ROOT . FileHandler::ensureSuffix($file, '.php');
        try {
            $handler = new XfnHandler();
            $handler->execFile($filename, $this->AppBase->getPdoAdapter(), $this->ConsoleHandler->getParams(), $fn);
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }
    /**
     * 执行 创建测试数据文件 命令
     */
    private function execSeedCreate()
    {
        // seed:create --env development --file ExampleTableSeeder --table tbl_example
        $table = $this->ConsoleHandler->inputRequired('--table');
        $db = $this->AppBase->getDb();
        $file = $this->ConsoleHandler->input('--file');
        $count = $this->ConsoleHandler->input('--count', 1000);
        if (!$this->AppBase->getPdoAdapter()->hasTable($table)) {
            exit('数据表 ' . $table . ' 不存在!');
        }
        if (!$file) {
            $file = CommonHelper::table2classname($table);
        }
        $filepath = AppBase::$APP_SEEDS_ROOT;
        if ($db) {
            $filepath .= $db . '/';
        }
        $filepath .= FileHandler::ensureSuffix($file, ['Seeder', '.php']);
        if (file_exists($filepath) && !$this->ConsoleHandler->checkIsForce()) {
            exit('数据种子文件已存在，如需覆盖，请使用参数 --force y。' . PHP_EOL . '路径：' . $filepath . PHP_EOL);
        }
        FileHandler::ensureDir(dirname($filepath));
        $handler = new ExportHandler($this->AppBase->getPdoAdapter());
        $handler->exportTableSchemaAsSeedForCli($db, $table, $this->AppBase->getConfigValue('migration_framework'), $filepath, true, $count);
        echo '数据种子文件创建成功! ', PHP_EOL, '路径：', $filepath;
    }

    private function execSeedAutoCreate()
    {
        // seed:autocreate [--env development] [--prefix tbl_] [--force y] [--eltcount 10] [--dir dbsample]
        $prefix = $this->ConsoleHandler->input('--prefix');
        $eltcount = intval($this->ConsoleHandler->input('--eltcount'));
        $count = $this->ConsoleHandler->input('--count', 1000);
        $db = $this->AppBase->getDb();
        $dir = AppBase::$APP_SEEDS_ROOT . $db . '/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $migrationfw = $this->AppBase->getConfigValue('migration_framework');
        $handler = new ExportHandler($this->AppBase->getPdoAdapter());
        $result = $handler->exportDbSchemaAsSeed($db, $migrationfw, $dir, $this->ConsoleHandler->checkIsForce(), $eltcount, $prefix, $count);
        echo '数据种子文件创建成功! ', PHP_EOL, '文件数量：', $result[0], '，实际创建文件数量：', $result[1];
        echo '，跳过文件数量：', ($result[2]), PHP_EOL, '路径：', $dir;
    }
    /**
     * 执行 运行测试数据生成 命令
     */
    private function execSeedRun()
    {
        // seed:run [--env development] --file ExampleTableSeeder.php
        $table = $this->ConsoleHandler->input('--table');
        $file = $this->ConsoleHandler->input('--file');
        $isDebug = $this->ConsoleHandler->input('--debug') == 'n';
        $db = $this->AppBase->getDb();
        if (!$file) {
            if ($table) {
                $file = CommonHelper::table2classname($table) . 'Seeder.php';
            } else {
                exit('必须输入文件名');
            }
        }

        $file = FileHandler::ensureSuffix($file, '.php');
        $dir = AppBase::$APP_SEEDS_ROOT . $db . '/';
        $filename = $dir . $file;
        if (!file_exists($filename)) {
            exit('种子文件不存在! 路径：' . $filename);
        }
        $msg = "即将向数据库 {$db} 中数据表填充数据，确认执行请输入 [yes]，按任意键取消操作：";
        if (!$this->ConsoleHandler->inputConfirm('yes', $msg)) {
            exit('用户取消操作！');
        }
        if (strpos($file, '.')) {
            $file = substr($file, 0, strpos($file, '.'));
        }
        // $classname = str_replace('/', '\\', ltrim($dir, './')) . $file;
        $classname = 'mysalapp\\seeds\\' . $db . '\\' . $file;
        try {
            $handler = new SeedHandler();
            echo $handler->execFile($classname, $this->AppBase->getPdoAdapter());
        } catch (\Exception $ex) {
            echo '出错啦：', $ex->getMessage(), PHP_EOL;
        }
    }
    /**
     * 执行 自动运行测试数据生成 命令
     */
    private function execSeedAutoRun()
    {
        // seed:autorun [--env development] [--database dbsample2] [--prefix tbl_] [--eltcount 0]
        $prefix = $this->ConsoleHandler->input('--prefix');
        $eltcount = $this->ConsoleHandler->input('--eltcount', 0);
        $db = $this->AppBase->getDb();
        $tables = $this->AppBase->getPdoAdapter()->getEmptyTables($db, $eltcount, $prefix);
        $classes = [];
        $dir = AppBase::$APP_SEEDS_ROOT . $db . '/';
        foreach ($tables as $table => $data) {
            $classname = CommonHelper::table2classname($table);
            $filename = $dir . $classname . 'Seeder.php';
            if (!file_exists($filename)) {
                continue;
            }
            $classes[$table] = str_replace('/', '\\', ltrim($dir, './')) . $classname . 'Seeder';
        }
        if (!$classes) {
            echo '操作取消！原因是：', PHP_EOL;
            echo '1.没有空的数据表需要写入测试数据。（输入参数 --eltcount 包含非空记录数据库） ', PHP_EOL;
            echo '2.没有数据表对应的数据种子文件!', PHP_EOL;
            echo '记录为空的数据表数量：', count($tables);
            exit();
        }
        $msg = '即将向数据库 ' . $db . ' 中的以下 ' . count($classes) . ' 个数据表填充数据' . PHP_EOL;
        $msg .= implode(', ', array_keys($classes)) . PHP_EOL;
        $msg .= '确认执行请输入 [yes]，按任意键取消操作：';
        if (!$this->ConsoleHandler->inputConfirm('yes', $msg)) {
            exit('用户取消操作！');
        }
        try {
            $handler = new SeedHandler();
            foreach ($classes as $table => $classname) {
                echo $handler->execFile($classname, $this->AppBase->getPdoAdapter()), PHP_EOL;
            }
        } catch (\Exception $ex) {
            exit('出错啦：' . $ex->getMessage());
        }
    }
    /**
     * 执行 导出整个数据库结构和表数据 命令
     */
    private function execExportDatabase()
    {
        $prefix = $this->ConsoleHandler->input('--prefix'); // 数据表前缀
        $isEachFile = $this->ConsoleHandler->inputEqual('--eachfile');
        $db = $this->ConsoleHandler->input('--database', $this->AppBase->getPdoAdapter()->getEnvInfo('database'));
        $file = AppBase::$APP_EXPORTS_ROOT . 'database/' . $db . '/';
        FileHandler::mkdir($file);
        // 导出数据库架构
        $handler = new ExportHandler($this->AppBase->getPdoAdapter());
        $handler->exportDbSchemaAsSqlForCli($db, '', false, $file . $db . '.sql', $prefix);
        // 导出所有表的数据
        $tables = $this->AppBase->getPdoAdapter()->getTables($db);
        $sqls = [];
        foreach ($tables as $row) {
            $table = $row['TABLE_NAME'];
            if ($prefix && 0 !== strpos($table, $prefix)) {
                continue;
            }
            $sql = "select * from `$table`";
            if ($isEachFile) {
                $handler->exportQueryDataAsSqlForCli($db, $table, $sql, $file . 'data_' . $table . '.sql');
            } else {
                $sqls[$table] = $sql;
            }
        }
        if (!$isEachFile) {
            $handler->exportQueryDataAsSqlForCli($db, $table, $sqls, $file . 'data_' . $db . '.sql');
        }
        echo '数据导出成功！一共 ', count($tables), ' 个数据表 [', $file, ']';
    }
    /**
     * 执行 导出数据表数据 命令
     */
    private function execExportData($cmdname)
    {
        //  export:data --table tbl_example [--sql "select * from tbl_example"]
        $table = $this->ConsoleHandler->input('--table'); // 数据表名称,没有指定名称则导出所有表
        $sql = $this->ConsoleHandler->input('--sql'); // 未指定则查询全部数据
        $filetype = $this->ConsoleHandler->input('--filetype'); // 文件类型，excel,html,markdown,sql,json
        $isTree = $this->ConsoleHandler->inputEqual('--tree', 'y');
        if ($cmdname == 'mock') {
            $filetype = 'json';
        }
        if (!$table && !$sql) {
            exit('必需输入 --table 参数和 --sql 参数的其中一个！');
        }
        if (!$sql) {
            $table = addslashes($table);
            $sql = "select * from `$table`";
        }
        $file = AppBase::$APP_EXPORTS_ROOT . 'data/';
        FileHandler::mkdir($file);
        $file .= $table ? $table : ('query-' . date('YmdHis'));
        $exportHandler = new ExportHandler($this->AppBase->getPdoAdapter());
        switch ($filetype) {
            case 'excel':
                $file .= '.xls';
                $exportHandler->exportQueryDataAsExcelForCli($this->AppBase->getDb(), $table, $sql, $file);
                break;
            case 'html':
                $file .= '.html';
                $exportHandler->exportQueryDataAsHtmlForCli($this->AppBase->getDb(), $table, $sql, $file);
                break;
            case 'json':
                $file .= '.json';
                $exportHandler->exportQueryDataAsJsonForCli($this->AppBase->getDb(), $table, $sql, $file);
                break;
            case 'markdown':
                $file .= '.md';
                $exportHandler->exportQueryDataAsMarkdownForCli($this->AppBase->getDb(), $table, $sql, $file);
                break;
            default:
                $file .= '.sql';
                $exportHandler->exportQueryDataAsSqlForCli($this->AppBase->getDb(), $table, $sql, $file, $isTree);
                break;
        }
        echo '数据导出成功! [', $file, ']';
    }
    /**
     * 执行 导出数据库结构 命令
     */
    private function execExportSchema()
    {
        //  export:schema [--database dbsample2] [--table tbl_example]
        // [--filetype excel|sql|html] [--eachfile y] [--prefix tbl_]
        $table = $this->ConsoleHandler->input('--table'); // 数据表名称，没有指定名称则导出所有表
        $filetype = $this->ConsoleHandler->input('--filetype', 'sql'); // 文件类型，excel,html,sql,php
        $prefix = $this->ConsoleHandler->input('--prefix'); // 数据表前缀
        $db = $this->ConsoleHandler->input('--database', $this->AppBase->getPdoAdapter()->getEnvInfo('database'));
        $file = AppBase::$APP_EXPORTS_ROOT . 'schema/';
        FileHandler::mkdir($file);
        $exportHandler = new ExportHandler($this->AppBase->getPdoAdapter());
        switch ($filetype) {
            case 'excel':
                $file .= ($table ? $table : $db) . '.xls';
                if ($table) {
                    $exportHandler->exportTableSchemaAsExcelForCli($db, $table, $file);
                } else {
                    $exportHandler->exportDbSchemaAsExcelForCli($db, $file, $prefix);
                }
                break;
            case 'html':
                $file .= ($table ? $table : $db) . '.html';
                if ($table) {
                    $exportHandler->exportTableSchemaAsHtmlForCli($db, $table, $file);
                } else {
                    $exportHandler->exportDbSchemaAsHtmlForCli($db, $file, $prefix);
                }
                break;
            default:
                // php or sql
                $isEachFile = $this->ConsoleHandler->inputEqual('--eachfile');
                $exportHandler = new ExportHandler($this->AppBase->getPdoAdapter());
                if ($isEachFile) {
                    $file .= $db . '/';
                    FileHandler::mkdir($file);
                } else {
                    $file .= ($table ? $table : $db) . '.' . $filetype;
                }
                if ($filetype == 'php') {
                    $exportHandler->exportDbSchemaAsPhpForCli($db, $table, $isEachFile, $file, $prefix);
                } else {
                    $exportHandler->exportDbSchemaAsSqlForCli($db, $table, $isEachFile, $file, $prefix);
                }
                break;
        }
        echo '数据表结构导出成功! [', $file, ']';
    }
    /**
     * 执行 创建数据库命令模板文件 命令
     */
    private function execTemplateSql()
    {
        // template:sql --operate create_table|alter_table|create_view|create_routine --file tbl_test.sql
        $operate = $this->ConsoleHandler->input('--operate');
        $file = $this->ConsoleHandler->inputRequired('--file');
        $filename = AppBase::$APP_SQLS_ROOT . 'template/';
        FileHandler::mkdir($filename);
        $filename .= FileHandler::ensureSuffix($file, '.php');
        file_put_contents($filename, SqlHandler::getDefaultExampleSql('eg' . $operate, false));
        echo '创建SQL模板文件成功! 路径：', $filename;
    }
    /**
     * 执行 生成数据库增删改查对应的存根模板文件 命令
     */
    private function execStubCrud()
    {
        // stub:crud --table fa_test --project fastlara/admin --force y --init y
        $project = $this->ConsoleHandler->inputRequired('--project');
        $stubProjectRootPath = ltrim(AppBase::$APP_STUBS_ROOT, './') . $project . '/';
        $stubsCrudFile = $stubProjectRootPath . 'CrudHandler.php';
        if (!file_exists($stubsCrudFile)) {
            exit('CrudHandler 文件不存在！路径：' . $stubsCrudFile);
        }

        // 启用自动加载类文件功能 new \thinkxsui\admin\CrudHandler();
        spl_autoload_register(function ($class) {
            // 解析类文件路径 mysqladminlite\stubs\fastlara\admin
            $class = trim($class, '\\');
            $pos = strpos($class, '\\');
            $vendor = substr($class, 0, $pos); // 顶级命名空间
            // 自动加载的命名空间的目录映射 ns => dir
            $dirMap = [
                $vendor => AppBase::$APP_ROOT_DIR . 'stubs/' . $vendor,
            ];
            $vendorDir = trim($dirMap[$vendor], '/'); // 文件目录
            $file = $vendorDir . str_replace('\\', '/', substr($class, $pos)) . '.php'; // 文件路径
            /* 加载文件 */
            if (file_exists($file)) {
                include $file;
            }
        }, true, true);

        // 调用处理程序文件
        try {
            $class = str_replace('/', '\\', $project . '/CrudHandler');
            $handler = new $class($this->ConsoleHandler->getParams(), $stubProjectRootPath);
            $handler->run($this->AppBase->getPdoAdapter());
        } catch (\Exception $ex) {
            exit('出错啦：' . $ex->getMessage());
        }
    }
    /**
     * 执行 生成存根模板文件 命令
     */
    private function execStubMake()
    {
        $project = $this->ConsoleHandler->inputRequired('--project');
        $stubProjectRootPath = ltrim(AppBase::$APP_STUBS_ROOT, './') . $project . '/';
        $stubsCfgFile = $stubProjectRootPath . 'config.php';
        if (!file_exists($stubsCfgFile)) {
            exit('配置文件不存在！请重新运行命令 stub:init 进行初始化。路径：' . $stubsCfgFile);
        }
        $stubsConfig = require $stubsCfgFile;
        // 调用处理程序文件
        try {
            // $handler = new MakeHandler();
            // $handler->run($stubsConfig, $this->ConsoleHandler->getParams(), $stubProjectRootPath);
        } catch (\Exception $ex) {
            exit('出错啦：' . $ex->getMessage());
        }
    }
    /**
     * 执行 MariaDB 审计报表转换 命令
     */
    private function execAudit2Html()
    {
        $database = $this->ConsoleHandler->input('--database');
        $operation = $this->ConsoleHandler->input('--operation');
        $timestamp = $this->ConsoleHandler->input('--timestamp');
        $file = AppBase::$APP_EXPORTS_ROOT . 'server_audit.log';
        $lines = file($file);
        $htmlFile = AppBase::$APP_EXPORTS_ROOT . 'audit.html';
        $content = '<html><head><title>MariaDB 审计报表</title><style>body,th,td{font-size:12px;color:#333;}'
            . 'table{border-top: solid #ccc 1px;border-right:solid 1px #CCC;border-spacing: 0;}th,td{padding:3px;'
            . 'border-left:solid 1px #CCC; border-bottom:solid 1px #CCC;}</style></head><body>'
            . '<h1>MariaDB 审计报表</h1><p>参考资料：<br />https://mariadb.com/kb/en/mariadb-audit-plugin/<br />'
            . 'https://mariadb.com/kb/en/mariadb-audit-plugin-log-format/</p>';
        $title = '时间[timestamp],节点[serverhost],用户[username],来源[host],连接标识[connectionid],查询标识[queryid],操作[operation],数据库[database],对象[object],返回代码[retcode]';
        $content .= '<table><tr><th>#</th><th>' . implode('</th><th>', explode(',', $title)) . '</th></tr>';
        file_put_contents($htmlFile, $content);
        $i = 1;
        foreach ($lines as $line) {
            $data = explode(',', $line, 9);
            $time = strtotime($data[0]);
            if ($operation && false === stripos($operation, $data[6])) {
                continue;
            }
            if ($database && $database != $data[7]) {
                continue;
            }
            if ($timestamp && false === strpos($data[0], $timestamp)) {
                continue;
            }
            $data[9] = substr($data[8], strrpos($data[8], ',') + 1);
            $data[8] = substr($data[8], 0, strrpos($data[8], ','));
            file_put_contents($htmlFile, '<tr><td>' . $i . '</td><td>' . implode('</td><td>', $data) . '</td></tr>', FILE_APPEND);
            $i++;
        }
        file_put_contents($htmlFile, '</table>', FILE_APPEND);
        echo '生成成功。路径：', $htmlFile;
    }
    /**
     * 执行 创建单页应用程序文件 命令
     */
    private function execBuildSPA()
    {
        $filename = $this->ConsoleHandler->inputRequired('--file', '必须输入 %s 参数，例如：--file mysalspa.php');
        $tplfile = implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'assets', 'spa.php.example']);
        if (!file_exists($tplfile)) {
            exit('SPA模板文件不存在，路径：' . $tplfile . PHP_EOL);
        }
        file_put_contents($filename, file_get_contents($tplfile) . PHP_EOL);
        $dir = './mysqladminlite/src';
        FileHandler::scandir($dir, true, ['php'], [], function ($file, $filepath, $level, $isfile) use ($filename) {
            if ($isfile) {
                $content = file_get_contents($filepath);
                $pos = strpos($content, 'class ');
                if (false === $pos) {
                    $pos = strpos($content, 'trait ');
                }
                $content = substr($content, $pos);

                if ($content) {
                    file_put_contents($filename, $content . PHP_EOL . PHP_EOL, FILE_APPEND);
                    echo '合并文件：', $filepath, PHP_EOL;
                }
            }
        }, 0);
        echo '生成单页应用程序成功，路径：', $filename, PHP_EOL;
    }
}
