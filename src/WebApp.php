<?php

namespace mysqladminlite;

use mysqladminlite\lib\PdoAdapter;
use mysqladminlite\lib\SqlHandler;
use mysqladminlite\lib\ExportHandler;
use mysqladminlite\lib\FileHandler;
use mysqladminlite\lib\HtmlHandler;
use mysqladminlite\lib\HtmlUIResource;
use mysqladminlite\lib\JwtHandler;
use mysqladminlite\lib\WebHandler;
use mysqladminlite\lib\CommonHelper;

class WebApp
{

    /**
     * 用户登录会话 Token 值
     */
    protected $_token;

    /**
     * 用户登录账号
     */
    protected $_loginId;

    /**
     * 用户登录会话过期时间
     */
    protected $_loginExp;

    /**
     * 引用扩展功能
     * use WebHandler;
     */
    protected $WebHandler;
    protected $AppBase;

    /**
     * 运行
     * @param array $config 全局配置
     * @param string $appRootDir 临时数据文件根目录路径
     */
    public function run($config, $appRootDir = './mysqladminlite/', $appRuntimeDir = './runtime/mysalapp/')
    {
        $this->AppBase = new AppBase();
        // 设置系统路径
        $this->AppBase->setSysPaths($appRootDir, $appRuntimeDir);

        // 应用初始化，加载外部配置文件等
        $this->AppBase->init($config);
        $config = $this->AppBase->getConfig();

        // 当前服务器ID
        $this->WebHandler = new WebHandler();
        $env = $this->input('env', $config['environments']['default_database']);
        $this->AppBase->setEnv($env);
        // 当前数据库名称
        $this->AppBase->setDb($this->input('db'));
        // 当前数据库对象名称（数据表名称、视图名称、存储过程名称、函数名称等）
        $this->AppBase->setObject($this->input('object'));
        // 当前数据库对象类型（table, view, procedure, function）
        $this->AppBase->setObjType($this->input('objtype'));
        // 当前输入的 SQL 语句
        $this->AppBase->setSql($this->input('sql', '', false));
        // 当前操作名称
        $this->AppBase->setAction($this->input('action'));
        // 当前登录会话 Token 值
        $this->_token = $this->input('token');
        // 特殊情况，需要从 SQL 语句解析出 object 名称
        $this->AppBase->parseSql();
        // 设置搜索信息
        $searchInfo = ['word' => $this->input('search_word'), 'range' => $this->input('search_range')];
        $this->AppBase->setSearchInfo($searchInfo);

        // UI 设置
        HtmlUIResource::setUiResource($config['ui_resource']);

        // 页面访问验证配置
        $loginUrlParams = [];
        JwtHandler::init($config);
        JwtHandler::checkLogin($loginUrlParams);
        list($loginId, $token, $loginExp) = array_values(JwtHandler::$LOGIN_RESULT);
        $this->_loginId = $loginId;
        $this->_token = $token;
        $this->_loginExp = $loginExp;

        // 精简版模式，用于 iframe 引用。示例：mysqladminlite.php?litemode=1&sqlstmt=...
        if ($this->input('litemode')) {
            $this->showLite();
        }


        // 实例化数据库操作类
        $server = $this->AppBase->parseEnv($token);
        try {
            $this->AppBase->setPdoAdapter(new PdoAdapter($server));
        } catch (\Exception $ex) {
            echo $ex->getMessage();
            exit;
        }

        // 脚本头部事件处理
        $this->handleHeadAction($config['migration_framework']);

        // 完整版模式
        // 页面标题前缀，显示当前服务器
        $title = $this->AppBase->getStrictMode() ? "[谨慎模式 {$env}] " : "[{$env}] ";
        $title .= ($this->AppBase->getObject() ? $this->AppBase->getObject() . ' - ' : '') . AppBase::APP_NAME . ' - ' . AppBase::APP_VERSION;
        $this->title = $title;
        $this->show();
    }

    /**
     * 脚本头部事件处理
     */
    protected function handleHeadAction($migrationfw)
    {
        $action = $this->AppBase->getAction();
        $db = $this->AppBase->getDb();
        $object = $this->AppBase->getObject();
        $sql = $this->AppBase->getSql();
        if (!$action) {
            return;
        }
        $table = $this->AppBase->getObjType() == 'table' ? $object : '';
        $exportHandler = new ExportHandler($this->AppBase->getPdoAdapter());

        switch ($action) {
            case 'phpinfo':
                // 显示 phpinfo 信息
                phpinfo();
                exit();
            case 'dashboard':
                // 数据监控台
                $this->showDashboard();
                break;
            case 'filesave':
                // 保存文件
                $path = $this->input('path');
                $content = $this->input('file_content', '', null);
                if (!$path || !$content) {
                    $result = false;
                } else {
                    $result = file_put_contents($path, $content);
                }
                echo json_encode(['result' => $result ? 'success' : 'failure', 'path' => $path]);
                exit();
            case 'fileread':
                // 读取文件
                $path = $this->input('path');
                if ($path) {
                    echo file_get_contents($path);
                }
                exit();
            default:
                break;
        }
        // 导出数据库表结构到 Markdown|Excel|HTML 文件
        if ($db) {
            switch ($action) {
                case 'export-db-markdown':
                    $exportHandler->exportDbSchemaAsMarkdown($db);
                    break;
                case 'export-db-excel':
                    $exportHandler->exportDbSchemaAsExcelForDownload($db);
                    break;
                case 'export-db-html':
                    $exportHandler->exportDbSchemaAsHtmlForDownload($db);
                    break;
                case 'export-db-model':
                    $exportHandler->exportDbSchemaAsHtmlOnline($db);
                    break;
                case 'export-db-sql':
                    $exportHandler->exportDbSchemaAsSqlForDownload($db);
                    break;
                default:
                    break;
            }
        }
        if ($table) {
            switch ($action) {
                case 'export-table-migrate':
                    $exportHandler->exportTableSchemaAsMigrateForDownload($db, $table, $migrationfw);
                    break;
                case 'export-table-seed':
                    $exportHandler->exportTableSchemaAsSeedForDownload($db, $table, $migrationfw);
                    break;
                case 'export-table-html':
                    $exportHandler->exportTableSchemaAsHtmlOnline($db, $table);
                    break;
                case 'export-table-excel':
                    $exportHandler->exportTableSchemaAsExcelForDownload($db, $table);
                    break;
                case 'export-table-sql':
                    $exportHandler->exportDbSchemaAsSqlForDownload($db, $table);
                    break;
                default:
                    break;
            }
        }
        // 导出查询数据到 Markdown 文件
        if ($sql) {
            switch ($action) {
                case 'export-query-md':
                    $exportHandler->exportQueryDataAsMarkdownForDownload($db, $table, $sql);
                    break;
                case 'export-query-excel':
                    $exportHandler->exportQueryDataAsExcelForDownload($db, $table, $sql);
                    break;
                case 'export-query-html':
                    $exportHandler->exportQueryDataAsHtmlForDownload($db, $table, $sql);
                    break;
                case 'export-query-htmlonline':
                    $exportHandler->exportQueryDataAsHtmlOnline($db, $table, $sql);
                    break;
                case 'export-query-json':
                    $exportHandler->exportQueryDataAsJsonForDownload($db, $table, $sql);
                    break;
                case 'export-query-jsononline':
                    $exportHandler->exportQueryDataAsJsonForWeb($db, $table, $sql);
                    break;
                case 'export-query-sql':
                    // 导出查询数据到 SQL 文件
                    $exportHandler->exportQueryDataAsSqlForDownload($db, $table, $sql);
                    break;
                default:
                    break;
            }
        }
    }
    // 显示完整版模式
    public function show()
    {
        // 显示页面结构头部
        $this->showPageHead();
        // 显示页面内容头部
        $this->showHeader();
        // 显示概览标签页内容
        $this->showOverviewTabContent();
        echo '<div class="d-flex">';
        $this->showAsideContent();
        $this->showMainContent();
        echo '</div>';
        // 显示页面结构底部
        $this->showPageFoot();
    }
    // 显示精简版模式
    public function showLite()
    {
        $sql = $this->AppBase->getSql();
        // 精简模式 2017-12-15
        echo '<form action="?action=run">';
        echo '<input type="text" name="sql" style="width:100%" value="', $sql, '" />';
        echo '<button type="submit">执行</button> <button type="reset">重置</button>';
        echo '<input type="hidden" name="env" value="', $this->AppBase->getEnv(), '" />';
        echo '<input type="hidden" name="db" value="', $this->AppBase->getDb(), '" />';
        echo '<input type="hidden" name="table" value="', $this->AppBase->getObject(), '" />';
        echo '<input type="hidden" name="token" value="', $this->_token, '" />';
        echo '<input type="hidden" name="litemode" value="1" />';
        echo '</form>';
        if ($sql) {
            if (0 === strpos($sql, 'select ')) {
                try {
                    $list = $this->AppBase->getPdoAdapter()->query($sql);
                    HtmlHandler::showTableWebLite($list);
                    unset($list);
                } catch (\Exception $ex) {
                    echo $ex->getMessage();
                }
            } else {
                echo '命令不支持：', $sql;
            }
        }
        exit;
    }

    /**
     * 显示页面结构头部
     */
    public function showPageHead($style = '')
    {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>', $this->title, '</title>';
        echo HtmlUIResource::getResourceLink(['bootstrap']);
        echo '<style>';
        echo HtmlUIResource::getStyle();
        if ($style) {
            echo $style;
        }
        echo '</style>';
        echo '</head><body>';
    }
    /**
     * 显示页面结构底部
     */
    public function showPageFoot($scripts = '')
    {
        echo HtmlUIResource::getResourceLink(['jquery', 'bootstrap'], 'javascript');
        echo '<script>';
        echo HtmlUIResource::getScript();
        if ($scripts) {
            echo $scripts;
        }
        echo '</script>';
        echo '</body></html>';
    }
    /**
     * 显示页面内容头部
     */
    private function showHeader()
    {
        $env = $this->AppBase->getEnv();
        $database = $this->AppBase->getDb();
        $token = $this->_token;
        echo '<header>';
        echo '<nav class="navbar navbar-expand-lg navbar-light bg-light">';
        echo '<a class="navbar-brand" href="#">', AppBase::APP_NAME, '</a>';
        echo '<span class="badge badge-info">', AppBase::APP_VERSION, '</span>';
        echo '<button class="btn btn-sm btn-toggle-panel" data-target-selector="aside" title="展开或收起侧边栏">&equiv;</button>';
        echo '<div class="collapse navbar-collapse" id="navbarText">';
        echo '<ul class="navbar-nav mr-auto">';

        $eltpl = "<li class='nav-item'><a class='nav-link' href='%2\$s' title='%3\$s' %4\$s>%1\$s</a></li>";
        echo sprintf($eltpl, '新窗口', "?env={$env}&token={$token}", '新开一个页面', 'target="_blank"');
        echo sprintf($eltpl, '重置', "?env={$env}&token={$token}", '重置当前页面', '');
        echo sprintf($eltpl, '刷新', '#', '刷新当前页面', 'onclick="location.reload();"');
        echo sprintf($eltpl, 'phpinfo', '?action=phpinfo&token=' . $token, '查看 phpinfo() 信息', 'target="_blank"');
        echo sprintf($eltpl, '监控大屏', "?env={$env}&db={$database}&token={$token}&action=dashboard", '查看服务器监控大屏', 'target="_blank"');
        $config = $this->AppBase->getConfig();
        if (isset($config['data_monitor_dashboard'])) {
            echo sprintf($eltpl, '测试监控台', "?env={$env}&db={$database}&token={$token}&action=dashboard", '查看测试监控台', 'target="_blank"');
        }
        echo sprintf($eltpl, '在线设计', "?env={$env}&db={$database}&token={$token}&action=dashboard", '在线设计数据库表单', 'target="_blank"');

        echo '</ul><div class="form-inline"><div>';
        // 用户登录信息
        echo '<table><tr><td>';
        echo '<span title="登录过期时间：', $this->_loginExp;
        echo ' (超时前进行跳转操作能让登录会话自动延期,避免重登录操作~)">', $this->_loginId, '</span> ';
        echo '<a class="btn btn-outline-secondary btn-sm" href="?action=logout" ',
        'onclick="return confirm(\'您确定要注销登录吗?\');">注销</a> ';
        // 显示本地时间
        echo '</td><td>';
        HtmlHandler::showLocaltime('<span class="align-items-center" style="display:inline-block;'
            . 'font-size:12px;margin-left:3px" title="第 {{yearweek}} 周 / 共 {{yearweeks}} 周，'
            . '第 {{yearday}} 天 / 共 {{yeardays}} 天！今年剩余 {{remaindays}} 天，{{remainweeks}} 周！' . PHP_EOL
            . '{{motto}}">' . date('Y-m-d') . '<br />' . date('H:i') . ' 星期{{weekday}}</span>');
        echo '</td></tr></table>';
        echo '</div></div></nav>';
        echo '</header>';
    }

    /**
     * 显示页面顶部标签页内容
     */
    private function showOverviewTabContent()
    {
        $tabs = [
            'empty' => ['title' => '折叠此面板', 'text' => '&equiv;'],
            'overview' => ['title' => 'Overview', 'text' => '概览'],
            'dbinfo' => ['title' => 'Status Variables', 'text' => '数据库状态变量'],
            'optimizes' => ['title' => 'Optimizes Advisor', 'text' => '优化建议'],
            'settings' => ['title' => 'Settings', 'text' => '设置'],
            'tutorial' => ['title' => 'Tutorial', 'text' => '教程'],
        ];
        echo '<!-- 概览标签页 -->';
        HtmlHandler::makeNavTabs('overview', $tabs, 'overview');
        echo '<div class="tab-content" id="nav-tabContent">';
        // tabContent empty
        echo '<div class="tab-pane fade overflow-auto" id="nav-overview-empty">';
        echo '</div><div class="tab-pane fade overflow-auto show active" id="nav-overview-overview">';
        echo '<p class="overview">客户端IP <mark>', $_SERVER['REMOTE_ADDR'],
        '</mark><span style="display:none">服务器IP <mark>';
        echo JwtHandler::getHostIP(), '</mark>数据库连接 <mark title="', $this->AppBase->getHost(), ':', $this->AppBase->getPort(), '">', $this->AppBase->getHost(),
        '</mark></span> ';
        echo '<a href="#" id="lnk-toggle-ipinfo">[...]</a><br />环境 <mark>', $this->AppBase->getEnv(), '</mark>数据库 ';
        echo '<mark>', $this->AppBase->getDb(), '</mark>版本 <mark>', $this->AppBase->getPdoAdapter()->getDbVersion(), '</mark></p>';
        // tabContent dbinfo
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-overview-dbinfo">';
        $optimizes = [];
        HtmlHandler::showDbVariablesTable($this->AppBase->getPdoAdapter()->getDbVariables(), $optimizes);
        // tabContent optimizes
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-overview-optimizes">';
        if ($optimizes) {
            echo '<h6>数据库配置文件优化建议</h6>';
            HtmlHandler::startTable();
            echo '<tr><th>变量名</th><th>当前值</th><th>建议值</th><th>描述</th></tr>';
            foreach ($optimizes as $row) {
                echo '<tr><td>', $row['name'], '</td><td>', $row['value'], '</td><td>', $row['recommend'];
                echo '</td><td>', $row['remark'], '</td></tr>';
            }
            echo '</table>';
        }
        // tabContent settings
        echo '</div><div class="tab-pane fade" id="nav-overview-settings">';
        echo '...';
        // tabContent tutorial
        echo '</div><div class="tab-pane fade" id="nav-overview-tutorial">';
        if (!empty($this->AppBase->getConfigValue('tutorial'))) {
            $tutorials = $this->AppBase->getConfigValue('tutorial');
            $groupCount = count($tutorials);
            $colCount = floor(12 / $groupCount);
            echo '<div class="row">';
            foreach ($tutorials as $groupTitle => $rows) {
                echo '<div class="col-', $colCount, '"><div class="card"><div class="card-body">';
                echo '<h5 class="card-title">', $groupTitle,
                '</h5><ul class="list-unstyled overflow-auto" style="max-height:150px">';
                foreach ($rows as $row) {
                    echo '<li><a href="', $row['url'], '" target="_blank" title="', $row['title'], '">',
                    CommonHelper::shortText($row['title'], 30),
                    '</a></li>';
                }
                echo '</ul></div></div></div>';
            }
            echo '</div>';
        }
        echo '</div></div>';
        echo '<!-- /概览标签页 -->';
    }
    private function showAsideContent()
    {

        // 配置中的环境配置列表
        $environmentList = $this->AppBase->getConfigValue('environments');
        $databaseList = $this->AppBase->getPdoAdapter()->getDatabases();
        // 当前数据库所有数据表的结构信息
        $tableNameList = [];
        // 当前数据库所有视图的结构信息
        $viewList = [];
        // 当前数据库所有函数的结构信息
        $functionList = [];
        // 当前数据库所有存储过程的结构信息
        $procedureList = [];
        $db = $this->AppBase->getDb();
        $env = $this->AppBase->getEnv();
        $token = $this->_token;
        $object = $this->AppBase->getObject();
        $tablePrefix = $this->AppBase->getTablePrefix();
        echo '<aside>';

        echo '<!--转到-->';
        $value = $object ? $object : $tablePrefix;
        $tabs = [
            'empty' => ['title' => '折叠此面板', 'text' => '&equiv;'],
            'goto' => ['title' => '', 'text' => '转到'],
            'search' => ['title' => '', 'text' => '查找'],
            'export' => ['title' => '', 'text' => '服务器与数据库'],
        ];
        HtmlHandler::makeNavTabs('jump', $tabs, 'goto');
        echo <<<tpl
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade overflow-auto" id="nav-jump-empty"></div>
                <div class="tab-pane fade overflow-auto show active" id="nav-jump-goto">
                    <form action="?action=jump">
                        跳转到：
                        <input class="form-control form-control-sm" type="text" name="table" value="{$value}" title="{$object}" required="required" placeholder="数据表/视图/函数" />
                        <div style="margin-top:5px">
                            <button class="btn btn-primary btn-sm" type="submit" title="跳转到表" data-db="{$db}">确定</button>
                            <button class="btn btn-secondary btn-sm" type="reset">重置</button>
                        </div>
                        <input type="hidden" name="action" value="egselect" />
                        <input type="hidden" name="env" value="{$env}" />
                        <input type="hidden" name="db" value="{$db}" />
                        <input type="hidden" name="token" value="{$token}" />
                    </form>
        tpl;
        if ($db) {
            // TABLE_NAME, TABLE_TYPE, ENGINE, DATA_LENGTH, CREATE_TIME, TABLE_COLLATION, TABLE_COMMENT
            $tableNameList = $this->AppBase->getPdoAdapter()->getTables($db);
            $viewList = $this->AppBase->getPdoAdapter()->getViews($db); // TABLE_NAME
            // ROUTINE_NAME, ROUTINE_TYPE
            $procedureList = $this->AppBase->getPdoAdapter()->getRoutines($db, 'procedure');
            // ROUTINE_NAME, ROUTINE_TYPE
            $functionList = $this->AppBase->getPdoAdapter()->getRoutines($db, 'function');
            if ($tableNameList || $viewList || $procedureList || $functionList) {
                echo '<div style="margin-top:5px;">快捷跳转：<div style="line-height:24px;">';
                $this->WebHandler->showAsideTableList($token, $env, $db, $object, $this->AppBase->getConfig, $tableNameList, $tablePrefix, false);
                $this->WebHandler->showAsideViewList($token, $env, $db, $object, $viewList, false);
                $this->WebHandler->showAsideRoutineList($token, $env, $db, $object, 'procedure', $procedureList, false);
                $this->WebHandler->showAsideRoutineList($token, $env, $db, $object, 'function', $functionList, false);
                echo '</div></div>';
            }
        }
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-jump-search">';
        echo '<!--/转到-->';
        $searchInfo = $this->AppBase->getSearchInfo();
        $optionsHtml = '<option value="all" title="数据库数据较多时不要直接查询全部表！！！"' . ($searchInfo['range'] == 'all' ? ' selected' : '') . '>全部表</option>';
        if ($tableNameList) {
            foreach ($tableNameList as $v) {
                $optionsHtml .= '<option value="' . $v['TABLE_NAME'] . '"' . ($v['TABLE_NAME'] == $searchInfo['range'] ? ' selected' : '') . '>' . $v['TABLE_NAME'] . '</option>';
            }
        }
        echo <<<tpl
        <form action="?action=search">
        关键词：
            <input class="form-control form-control-sm" type="text" name="search_word" value="{$searchInfo['word']}" placeholder="请输入搜索关键词" />
            <div class="my-2">范围：<select name="search_range" class="form-control form-control-sm">{$optionsHtml}</select></div>
            <div>
                <button class="btn btn-primary btn-sm" type="submit" title="全文搜索" data-db="{$db}">搜索</button>
                <button class="btn btn-secondary btn-sm" type="reset">重置</button>
            </div>
            <input type="hidden" name="action" value="search" />
            <input type="hidden" name="env" value="{$env}" />
            <input type="hidden" name="db" value="{$db}" />
            <input type="hidden" name="token" value="{$token}" />
        </form>
        tpl;
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-jump-export">';
        /* 侧边栏显示对象名称列表 */
        // SCHEMA_NAME,DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME
        echo '服务器';
        echo '<select class="form-control form-control-sm" id="servslt">';
        $tmpisprod = false;
        foreach ($environmentList as $k => $v) {
            // 过滤无效环境配置节点
            if ('default_database' == $k || empty($v) || empty($v['password'])) {
                continue;
            }
            $tmpisprod = isset($v['strict_mode']) && $v['strict_mode'];
            echo '<option value="', $k, '"', ($env == $k ? ' selected' : '');
            echo " data-url=\"?env={$k}&token={$token}\"";
            echo ' title="', $k, ($tmpisprod ? '(生产环境)' : ''), '"', ($tmpisprod ? ' style="color:#F00"' : ''),
            '>';
            echo CommonHelper::shortText($k, 30);
            echo '</option>';
        }
        echo '</select>';
        echo '数据库';
        echo '<select class="form-control form-control-sm" id="dbslt">';
        echo '<option value="">==选择数据库==</option>';
        foreach ($databaseList as $v) {
            $dbObject = $v['SCHEMA_NAME'];
            echo "<option value=\"{$dbObject}\"";
            echo $db == $dbObject ? ' selected="selected"' : '';
            echo " data-url=\"?env={$env}&db={$dbObject}&token={$token}\" title=\"{$dbObject}\">";
            echo CommonHelper::shortText($dbObject, 30), ' [', $v['DEFAULT_CHARACTER_SET_NAME'], ']';
            echo '</option>';
        }
        echo '</select>';
        echo '<!--/数据库选择器-->';
        if ($db) {
            echo '数据库操作';
            $this->WebHandler->showAsideDbOptionButtons($token, $env, $db);
        }
        echo '</div></div>';
        echo '<!--/搜索-->';

        echo '<!--数据表-->';
        $tabs = [
            'empty' => ['title' => '折叠此面板', 'text' => '&equiv;'],
            'table' => ['title' => '', 'text' => '数据表'],
            'view' => ['title' => '', 'text' => '视图'],
            'procedure' => ['title' => '', 'text' => '过程'],
            'function' => ['title' => '', 'text' => '函数'],
        ];
        HtmlHandler::makeNavTabs('dbtable', $tabs, 'table');

        echo '<div class="tab-content" id="nav-tabContent">';
        echo '<div class="tab-pane fade overflow-auto" id="nav-dbtable-empty"></div>';
        echo '<div class="tab-pane fade overflow-auto show active" id="nav-dbtable-table">';
        if ($db && $tableNameList) {
            $this->WebHandler->showAsideTableList($token, $env, $db, $object, $this->AppBase->getConfig, $tableNameList, $tablePrefix, true, 30);
        }
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-dbtable-view">';
        if ($db && $viewList) {
            $this->WebHandler->showAsideViewList($token, $env, $db, $object, $viewList, true, 30);
        }
        echo '</div>';
        echo '<!--/数据表-->';
        echo '<div class="tab-pane fade overflow-auto show active" id="nav-dbroutine-procedure">';
        if ($db && $procedureList) {
            $this->WebHandler->showAsideRoutineList($token, $env, $db, $object, 'procedure', $procedureList, true, 30);
        }
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-dbroutine-function">';
        if ($db && $functionList) {
            $this->WebHandler->showAsideRoutineList($token, $env, $db, $object, 'function', $functionList, true, 30);
        }
        echo '</div></div>';
        echo '<!--/存储过程-->';
        echo '</aside>';
    }

    private function showMainContent()
    {
        $action = $this->AppBase->getAction();
        $db = $this->AppBase->getDb();
        $object = $this->AppBase->getObject();
        $objType = $this->AppBase->getObjType();
        $objTitle = $db . ' 数据库';
        $objUnfinded = false;
        $fields = [];
        $columnList = [];
        $routineParamNames = [];
        $routineParamList = [];
        if ($db && $objType) {
            switch ($objType) {
                    // 当前数据表对象的操作
                case 'table':
                    $objTitle = $object . ' 数据表';
                    // COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION,
                    // NUMERIC_SCALE, EXTRA, COLUMN_COMMENT
                    $columnList = $this->AppBase->getPdoAdapter()->getColumns($db, $object);
                    $objUnfinded = empty($columnList);
                    $fields = CommonHelper::arrayColumn($columnList, 'COLUMN_NAME'); // 字段名称集合
                    // 获取具体示例语句
                    if ($action) {
                        $this->AppBase->setSql(SqlHandler::getTableExampleSql($action, $db, $object, $fields));
                    }
                    break;
                    // 当前视图对象的操作
                case 'view':
                    $objTitle = $object . ' 视图';
                    // COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION,
                    // NUMERIC_SCALE, EXTRA, COLUMN_COMMENT
                    $columnList = $this->AppBase->getPdoAdapter()->getColumns($db, $object);
                    $objUnfinded = empty($columnList);
                    $fields = CommonHelper::arrayColumn($columnList, 'COLUMN_NAME'); // 字段名称集合// 获取示例语句
                    // 获取具体示例语句
                    if ($action) {
                        $this->AppBase->setSql(SqlHandler::getViewExampleSql($action, $db, $object, $fields));
                    }
                    break;
                    // 当前程序对象的操作
                case 'procedure':
                case 'function':
                    $objTitle = $object . ($objType == 'procedure' ? ' 过程' : ' 函数');
                    // 参数名称集合 ORDINAL_POSITION, PARAMETER_MODE, PARAMETER_NAME, DATA_TYPE,
                    // CHARACTER_MAXIMUM_LENGTH, DTD_IDENTIFIER
                    $routineParamList = $this->AppBase->getPdoAdapter()->getRoutineParams($db, $object);
                    $routineParamNames = CommonHelper::arrayColumn($routineParamList, 'PARAMETER_NAME');
                    $this->AppBase->getPdoAdapter()->getRoutineParamsSimple($db, $object); // 获取示例语句
                    // 获取具体示例语句
                    if ($action) {
                        $this->AppBase->setSql(SqlHandler::getRoutineExampleSql($action, $object, $objType, $routineParamNames));
                    }
                    break;
                default:
                    break;
            }
        }
        // 当前环境的数据库服务器中的数据库列表
        $databaseList = $this->AppBase->getPdoAdapter()->getDatabases();
        echo '<main class="flex-grow-1">';
        // 显示对象信息标签页内容
        $this->showObjectInfoTabContent($objTitle, $objUnfinded, $fields, $routineParamNames, $routineParamList, $columnList, $databaseList);
        // 显示编辑器标签页内容
        $this->showEditorTabContent();
        // 显示查询结果标签页内容
        $this->showQueryResultTabContent();
        echo '</main>';
    }
    private function showObjectInfoTabContent($objTitle, $objUnfinded, $fields, $routineParamNames, $routineParamList, $columnList, $databaseList)
    {
        $env = $this->AppBase->getEnv();
        $token = $this->_token;
        $db = $this->AppBase->getDb();
        $object = $this->AppBase->getObject();
        $objType = $this->AppBase->getObjType();
        $table = $objType == 'table' ? $object : '';

        echo '<!--数据表信息-->';
        $tabs = [
            'empty' => ['title' => '折叠此面板', 'text' => '&equiv;'],
            'summary' => ['title' => '', 'text' => $objTitle],
            'schema' => ['title' => '', 'text' => '架构信息'],
            'new-item' => ['title' => '', 'text' => '新增记录'],
            'template' => ['title' => '', 'text' => '数据模板'],
        ];
        HtmlHandler::makeNavTabs('objinfo', $tabs, 'summary');
        echo <<<tpl
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade overflow-auto" id="nav-objinfo-empty"></div>
                <div class="tab-pane fade overflow-auto show active" id="nav-objinfo-summary">
        tpl;
        if (!$objUnfinded) {
            switch ($objType) {
                case 'table':
                    echo '<div>快速操作</div>';
                    $this->WebHandler->showTableOptionButtons($token, $env, $db, $object);
                    echo '<div>数据表信息</div>';
                    $tableNameList = $this->AppBase->getPdoAdapter()->getTables($db);
                    HtmlHandler::showPropertyTable(current(CommonHelper::arraySearchRows($tableNameList, ['TABLE_NAME' => $table])));
                    break;
                case 'view':
                    echo '<div>快速操作</div>';
                    $this->WebHandler->showViewOptionButtons($token, $env, $db, $object, $fields);
                    break;
                case 'function':
                case 'procedure':
                    echo '<div>快速操作</div>';
                    $this->WebHandler->showRoutineOptionButtons($token, $env, $db, $object, $objType, $routineParamNames);
                    break;
                default:
                    echo '<div>快速操作</div>';
                    $this->WebHandler->showDefaultOptionButtons($token, $env, $db);
                    echo '<div>数据库信息</div>';
                    HtmlHandler::showPropertyTable(current(CommonHelper::arraySearchRows($databaseList, ['SCHEMA_NAME' => $db])));
                    break;
            }
        }
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-objinfo-schema">';
        if ($objUnfinded) {
            echo '<div class="text-danger">' . $objTitle
                . '不存在! <a class="btn btn-sm btn-secondary" href="?env=' . $env
                . '&token=' . $token . '">刷新</a></span></div>';
        } else {
            switch ($objType) {
                case 'table':
                    echo '<div>字段概览</div>';
                    echo '<code>', implode(', ', $fields), '</code>';
                    echo '<div>数据列信息</div>';
                    HtmlHandler::showTableWeb($columnList);
                    break;
                case 'view':
                    echo '<code>', implode(', ', $fields), '</code>';
                    echo '<div>数据列信息</div>';
                    HtmlHandler::showTableWeb($columnList);
                    break;
                case 'procedure':
                case 'function':
                    HtmlHandler::showPropertyTable($routineParamList);
                    echo implode(', ', $routineParamNames);
                    break;
                default:
                    echo '<div>数据表信息</div>';
                    $tableNameList = $this->AppBase->getPdoAdapter()->getTables($db);
                    HtmlHandler::showTableWebLite($tableNameList);
                    break;
            }
        }
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-objinfo-new-item">';
        echo '通过可视化表单创建新记录，支持批量创建种子数据';
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-objinfo-template">';
        echo '生成数据模板';
        echo <<<tpl
            </div>
            <!-- /tab-pane -->
        </div>
        <!--/数据表信息-->
        tpl;
    }
    private function showEditorTabContent()
    {
        $env = $this->AppBase->getEnv();
        $token = $this->_token;
        $db = $this->AppBase->getDb();
        $object = $this->AppBase->getObject();
        $objType = $this->AppBase->getObjType();
        $table = $objType == 'table' ? $object : '';
        $action = $this->AppBase->getAction();

        echo '<!--SQL编辑器-->';
        $tabs = [
            'empty' => ['title' => '折叠此面板', 'text' => '&equiv;'],
            'sqleditor' => ['title' => '', 'text' => 'SQL 编辑器'],
            'mysaleditor' => ['title' => '', 'text' => '文件编辑器'],
        ];
        HtmlHandler::makeNavTabs('editor', $tabs, 'sqleditor');
        echo <<<tpl
             <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade overflow-auto" id="nav-editor-empty"></div>
                <div class="tab-pane fade overflow-auto show active" id="nav-editor-sqleditor">
            tpl;
        /* 快捷查询 */
        if ($table && !empty($this->AppBase->getConfigValue('fastsqls'))) {
            $fastsqls = SqlHandler::parseFastSqls($table, $this->AppBase->getConfigValue('fastsqls'));
            if ($fastsqls) {
                if ($action && isset($fastsqls[$action])) {
                    $sql = $fastsqls[$action]['sql'];
                    $sql = str_replace('{#object}', $object, $sql); // 替换{#object}参数
                }
                echo '<div class="btngroup" style="margin-bottom:2px">';
                // 读取快捷方式的配置
                if ($fastsqls) {
                    echo '快捷查询：';
                    $url = '?env=' . $env . '&db=' . $db . '&table=' . $table . '&token='
                        . $token . '&action=';
                    foreach ($fastsqls as $name => $v) {
                        echo '<a class="btn btn-sm btn-outline-info" style="padding:0 .5rem" href="';
                        echo $url, $name, '" title="';
                        echo (isset($v['remark']) ? $v['remark'] : ''), '">';
                        echo (isset($v['text']) ? $v['text'] : $name), '</a>';
                    }
                }
                echo '</div>';
            }
        }
        /* 初始化界面 */
        if (!$object && $action) {
            if (0 === strpos($action, 'eg')) {
                $this->AppBase->setSql(SqlHandler::getDefaultExampleSql($action));
            } else {
                /* 数据库操作 */
                echo '<div class="location">当前数据库：<strong>', $db, '</strong></div>';
                $this->AppBase->setSql(SqlHandler::getDbExampleSql($action, $db));
            }
        }
        // sqlEditor可视化编辑器
        echo <<<tpl
        <div class="h5ve-container card" id="h5ve_introduction">
        <div class="h5ve-toolbar card-header bg-gray d-flex" style="padding: 0;">
            <div class="flex-grow-1">
                <div class="btn-group" title="工具">
                    <button class="btn btn-sm btn-run" title="复制">执行</button>
                    <button class="btn btn-sm btn-save" title="保存">保存</button>
                    <button class="btn btn-sm btn-favorite" title="收藏">收藏</button>
                </div>
                <div class="btn-group" title="工具">
                    <button class="btn btn-sm btn-select" title="全选"
                        onclick="return doEditorCmd('sqleditor','select');">全选</button>
                    <button class="btn btn-sm btn-copy" title="复制"
                        onclick="return doEditorCmd('sqleditor','copy');">复制</button>
                    <button class="btn btn-sm btn-paste" title="粘贴"
                        onclick="return doEditorCmd('sqleditor','paste');">粘贴</button>
                    <button class="btn btn-sm btn-clear" title="清空"
                        onclick="return doEditorCmd('sqleditor','clear');">清空</button>
                </div>
                <div class="btn-group" title="视图">
                    <button class="btn btn-sm btn-print" title="打印">打印</button>
                    <button class="btn btn-sm btn-fullwindow" title="全屏">全屏</button>
                    <button class="btn btn-sm btn-fullscreen" title="满屏">满屏</button>
                </div>
                <div class="btn-group" title="帮助">
                    <button class="btn btn-sm btn-help" title="帮助">帮助</button>
                </div>
            </div>
            <div>
                <button class="btn btn-sm btn-toggle-panel" data-target-selector="#sql_favorite_panel" title="查看收藏夹">收藏夹 &equiv;</button>
            </div>
        </div>
        <div class="h5ve-content card-body d-flex" style="padding:0">
        tpl;
        $url = "?env={$env}&db={$db}&token={$token}";
        echo '<form class="flex-grow-1" id="sqleditorform" method="post" action="', $url, '">';
        echo '<textarea class="form-control form-control-sm code-editor" id="sqleditor" name="sql" rows="8" required="required" placeholder="';
        echo $this->WebHandler->getSqlEditorPlaceholder(), '" title="';
        if ($object) {
            echo '当前支持{#object}变量,可以自动替换为当前表名或程序名; 点击3次自动去除注释';
        }
        echo '">', $this->AppBase->getSql(), '</textarea>';
        echo <<<tpl
            <input type="hidden" name="env" value="{$env}" />
            <input type="hidden" name="db" value="{$db}" />
            <input type="hidden" name="table" value="{$table}" />
            <input type="hidden" name="token" value="{$token}" />
            tpl;
        if ($this->AppBase->getStrictMode()) {
            echo HtmlHandler::getCaptcha(); // 正式环境启用验证码防止删库跑路的悲剧
        }
        echo '</form>';
        echo '<div id="sql_favorite_panel" class="d-none p-2" title="收藏夹"><ul class="list-unstyled"><li>select * from tbl_user limit 10</li></ul></div>';
        echo <<<tpl
            </div>
            <div class="h5ve-footer card-footer d-flex" style="padding:5px">
                <div class="flex-grow-1">自定义快速命令：</div>
                <div><span class="text-danger" id="exectimer">本次执行总耗时 0 秒</span></div>
            </div>
        </div>
        tpl;
        echo '</div><div class="tab-pane fade overflow-auto" id="nav-editor-mysaleditor">';
        echo '<div class="row"><div class="col-2">';
        // mysal目录的文件列表
        echo '<div style="max-height:210px; overflow-y:auto" id="file-list">',
        '<ul class="list-unstyled">';
        if (is_dir('./mysqladminlite')) {
            // 匿名方法四个参数：file:文件名,filepath:文件路径,level:目录层级,isfile:是否文件
            FileHandler::scandir('./mysqladminlite', true, '', '', function ($a, $b, $c, $d) use ($token, $env) {
                $url = '###';
                if ($d) {
                    $url = "?action=fileread&env={$env}&token={$token}&path={$b}";
                }
                echo '<li><a href="', $url, '" data-path="', $b, '">';
                echo ($c > 0 ? '|' : ''), str_repeat('--', $c), $a, '</a></li>';
            });
        } else {
            echo '<li>路径[mysal]不存在!<br />请在命令行输入：<br />',
            '<code>php mysqladminlite.php --init</code><br />进行初始化后再刷新本页面</li>';
        }
        echo '</ul></div>';
        echo '</div><div class="col-10">';
        echo <<<tpl
            <form action="?action=filesave" id="file-form">
            <textarea class="form-control form-control-sm code-editor" id="file-editor" name="file_content" rows="8" required="required"></textarea>
            <input type="hidden" name="token" value="{$token}" />
            <input type="hidden" name="env" value="{$env}" />
            <input type="hidden" name="action" value="savefile" />
            <input type="hidden" name="path" id="file-path" value="" />
            <button class="btn btn-primary btn-sm" type="submit">保存</button>
            <button class="btn btn-secondary btn-sm" type="reset">重置</button>
            <div class="btn-group btn-group-sm" role="group">
                <a class="btn btn-light" onclick="return doEditorCmd('file-editor','select');">全选</a>
                <a class="btn btn-light" onclick="return doEditorCmd('file-editor','copy');">复制</a>
                <a class="btn btn-light" onclick="return doEditorCmd('file-editor','clear');">清空</a>
            </div>
        </form>
        tpl;
        echo '</div></div>';
        echo '<!--/row-->';
        echo '</div></div>';
        echo '<!--/SQL编辑器-->';
    }
    private function showQueryResultTabContent()
    {
        $action = $this->AppBase->getAction();
        $env = $this->AppBase->getEnv();
        $token = $this->_token;
        $db = $this->AppBase->getDb();
        $object = $this->AppBase->getObject();
        $objType = $this->AppBase->getObjType();
        $table = $objType == 'table' ? $object : '';
        $sql = $this->AppBase->getSql();
        echo '<!--结果展示-->';
        // 显示命令执行结果(支持批量语句)
        $sqls = [];
        if ($sql && strpos($sql, ' ') > 0) {
            // 生成测试数据 2017-11-24
            if (strpos($sql, 'testdata ') === 0) {
                $sqls = []; // 先清空,不然上面有残留数据
                $sqls[] = SqlHandler::parseSeedSqlStatement($sql);
            } else {
                // 普通命令解析 (textarea回车符号是\r,和OS平台无关)
                $sqls = SqlHandler::parseSqlStatement(explode("\r", $sql));
            }
        }
        // 全文搜索
        if ($action == 'search') {
            $searchInfo = $this->AppBase->getSearchInfo();
            $searchSqls = $this->AppBase->getPdoAdapter()->getFullTextSearchSqls($searchInfo['word'], $searchInfo['range']);
            if ($searchSqls) {
                $sqls = $searchSqls;
                unset($searchSqls);
            }
        }
        $tabs = [
            'empty' => ['title' => '折叠此面板', 'text' => '&equiv;'],
            'panel' => ['title' => '共' . count($sqls) . '条命令', 'text' => '结果展示'],
        ];
        HtmlHandler::makeNavTabs('result', $tabs, 'panel');
        echo <<<tpl
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade overflow-auto" id="nav-result-empty"></div>
                <div class="tab-pane fade overflow-auto show active" id="nav-result-panel" style="max-height:500px;">
            tpl;
        // 显示命令执行结果(支持批量语句)
        if ($sqls) {
            $time_start = microtime(true); // 性能计时器

            $exportHandler = new ExportHandler($this->AppBase->getPdoAdapter());
            $exportHandler->showQueryResultForWeb($sqls, $env, $db, $table, $token);
            $time = microtime(true) - $time_start;
            echo '<input type="hidden" id="exectimer_value" value="', round($time, 2), '" />';
        }
        echo <<<tpl
            </div>
        </div>
        <!--/结果展示-->
        tpl;
    }
    /**
     * 显示数据监控台
     */
    private function showDashboard()
    {
        // 变量
        $config = $this->AppBase->getConfig();
        if (!isset($config['data_monitor_dashboard'])) {
            exit('data_monitor_dashboard 配置项的内容无效');
        }
        $config = $config['data_monitor_dashboard'];
        if (isset($config['enable']) && !$config['enable']) {
            exit('data_monitor_dashboard 功能状态设置为禁用，如需启用，请更改配置项：data_monitor_dashboard.enable=true');
        }
        $env = $this->AppBase->getEnv();
        $token = $this->_token;
        $db = $this->AppBase->getDb();
        $searchWord = $this->input('w'); // 关键词
        $intWord = intval($searchWord);
        $searchRange = $this->input('range'); // 查询范围
        $searchGroup = $this->input('group'); // 查询数据分组
        $action2 = $this->input('action2'); // 操作
        $table = $this->input('table'); //  数据表名称
        $exportType = $this->input('export'); //  导出类型
        if ($exportType) {
            $exportHandler = new ExportHandler($this->AppBase->getPdoAdapter());
            switch ($exportType) {
                case 'markdown':
                    $exportHandler->exportQueryDataAsMarkdownForDownload($db, $table, "select * from {$table}");
                    break;
                case 'excel':
                    $exportHandler->exportQueryDataAsExcelForDownload($db, $table, "select * from {$table}");
                    break;
                case 'html':
                    $exportHandler->exportQueryDataAsHtmlOnline($db, $table, "select * from {$table}");
                    break;
                default:
                    break;
            }
            exit;
        }
        // 显示页面结构头部
        $this->showPageHead();
        // 显示页面内容头部
        $this->showHeader();
        echo '<div class="px-3">';
        echo '<h4 class="mt-3">数据监控台</h4>';
        echo "<div class='mb-1'>服务器：{$env}，数据库：{$db}</div>";
        $resetSqls = $config['onekeyreset']['reset_sqls'];
        if ($action2) {
            switch ($action2) {
                case 'reset':
                    echo '<h5>一键重置用户数据</h5>';
                    echo '<div>命令列表</div>';
                    echo '<table class="table table-sm table-striped table-bordered table-hover">';
                    if ($table && isset($resetSqls[$table])) {
                        $resetSqls = [$table => $resetSqls[$table]];
                    }
                    foreach ($resetSqls as $table => $field) {
                        if (is_array($field)) {
                            $sql = 'update ' . $table . ' set ';
                            foreach ($field as $v) {
                                $sql .= (strpos($v, '=') ? $v : ($v . '=0')) . ',';
                            }
                            $sql = substr($sql, 0, -1);
                        } else if (strlen($field) > 0) {
                            $sql = 'update ' . $table . ' set ' . str_replace(',', '=0, ', $field) . '=0';
                        } else {
                            $sql = 'truncate table ' . $table;
                        }
                        echo '<tr><td>', $table, '</td><td><code>', $sql, '</code></td></tr>';
                        $this->AppBase->getPdoAdapter()->exec($sql);
                    }
                    echo '</table>';
                    $message = '执行完毕，用户数据重置成功！';
                    break;
                case 'truncate':
                    echo '<h5>清空数据</h5>';
                    if ($table) {
                        $sql = 'truncate table ' . $table;
                        echo '执行命令：<code>', $sql, '</code><br />';
                        $this->AppBase->getPdoAdapter()->exec($sql);
                        $message = '用户数据已清空！';
                    }
                    break;
                default:
                    break;
            }
            $url = "?env={$env}&db={$db}&group={$searchGroup}&action=dashboard&reset-success&token={$token}";
            echo '<p class="my-2"><strong>', $message, '</strong><a class="btn btn-sm btn-primary" href="', $url, '">点击进行跳转</a></p>';
            exit;
        }
        // 显示页面内容
        echo '<div><button class="btn btn-sm btn-warning" onclick="fnReset()">', $config['onekeyreset']['text'], '</button>';
        echo '<span class="ml-2 text-secondary">', $config['onekeyreset']['message'], '</span></div>';
        echo '<form class="form-inline my-3">';
        echo '关键词：<input type="text" name="w" class="form-control form-control-sm mr-2" placeholder="请输入关键词..." size="10" value="', $searchWord, '" /> ';
        // 查询范围（设置作用于搜索关键词的查询字段分组，具体在 query_sqls[table][where] 节点中设置）
        echo '查询范围：<select name="range" class="form-control form-control-sm mr-2" title="设置作用于搜索关键词的查询字段分组">';
        $searchRanges = isset($config['search_range']) ? $config['search_range'] : [];
        foreach ($searchRanges as $k => $v) {
            echo '<option value="', $k, '"', ($searchRange == $k ? ' selected' : ''), '>', $v, '</option>';
        }
        echo '</select> ';
        // 数据分组（用于控制哪些数据表显示或隐藏，便于数据变动的观察）
        echo '显示范围：<select name="group" class="form-control form-control-sm mr-2" title="用于控制哪些数据表显示或隐藏，便于数据变动的观察">';
        $dataGroups = isset($config['data_group']) ? $config['data_group'] : ['all' => ['title' => '全部', 'tables' => []]];
        foreach ($dataGroups as $k => $v) {
            echo '<option value="', $k, '"', ($searchGroup == $k ? ' selected' : ''), '>', $v['title'], '</option>';
        }
        echo '</select> ';
        echo '<input type="hidden" name="env" value="', $env, '" /><input type="hidden" name="db" value="', $db, '" /><input type="hidden" name="token" value="', $token, '" /><input type="hidden" name="action" value="dashboard" />';
        echo '<button type="submit" class="btn btn-sm btn-primary">查询</button><button type="reset" class="btn btn-sm btn-secondary mx-2">重置</button></form>';
        // 查询处理
        $dataGroupTables = isset($dataGroups[$searchGroup]) ? $dataGroups[$searchGroup]['tables'] : [];
        if ($dataGroupTables && gettype($dataGroupTables) == 'string') {
            $dataGroupTables = explode(',', $dataGroupTables);
        }
        $sqls = isset($config['query_sqls']) ? $config['query_sqls'] : [];
        $querySqlConfigInfo = [];
        if (isset($sqls['__config__'])) {
            $querySqlConfigInfo = $sqls['__config__'];
            unset($sqls['__config__']);
        }
        $sqlList = [];
        foreach ($sqls as $table => $info) {
            if ($dataGroupTables && !in_array($table, $dataGroupTables)) {
                $continue = true;
                foreach ($dataGroupTables as $v) {
                    if (strpos($v, '*')) {
                        $v = substr($v, 0, -1);
                        if (false !== strpos($table, $v)) {
                            $continue = false;
                            break;
                        }
                    }
                }
                if ($continue) {
                    continue;
                }
            }
            $where = '';
            if (strlen($searchWord) > 0 && isset($info['where'][$searchRange])) {
                $where = ' where ' . str_replace([':searchWord', ':intWord'], [$searchWord, $intWord], $info['where'][$searchRange]);
            }
            $sql = 'select ' . (isset($info['field']) ? $info['field'] : '*') . ' from ' . $table . ' ' . $where;
            if (!empty($info['order'])) {
                $sql .= ' order by ' . $info['order'];
            }
            $sql .= ' limit ' . (empty($info['limit']) ? 10 : $info['limit']);
            $sqlList[$table] = $sql;
        }

        $tableNameList = $this->AppBase->getPdoAdapter()->getTables($db);
        $tables = [];
        foreach ($tableNameList as $row) {
            $tables[$row['TABLE_NAME']] = $row['TABLE_COMMENT'];
        }
        $url = "?env={$env}&db={$db}&action=dashboard&export=[export]&table=[table]&token={$token}";
        // 显示数据表格
        foreach ($sqlList as $table => $sql) {
            echo '<section>';
            echo '<h5>', $table, '<span class="ml-2">', (isset($tables[$table]) ? $tables[$table] : ''), '</span>';
            echo '<span class="btn-group btn-group-sm ml-2">';
            echo '<button class="btn btn-sm btn-light" onclick="fnToggle(this)" title="展开或收起内容">&or;</button>';
            echo '<button class="btn btn-sm btn-light" onclick="fnTruncate(this)" data-table="', $table, '" title="清空所有数据">清空</button>';
            if (isset($resetSqls[$table])) {
                echo '<button class="btn btn-sm btn-light" onclick="fnReset(this)" data-table="', $table, '" title="重置用户数据">重置</button>';
            }
            echo '<a class="btn btn-light" href="', str_replace(['[export]', '[table]'], ['html', $table], $url), '" target="_blank">新页面查看</a>';
            echo '<a class="btn btn-light" href="', str_replace(['[export]', '[table]'], ['markdown', $table], $url), '" target="_blank">导出 Markdown</a>';
            echo '<a class="btn btn-light" href="', str_replace(['[export]', '[table]'], ['excel', $table], $url), '" target="_blank">导出 Excel</a>';
            echo '</span></h5>';
            echo '<code>', $sql, '</code><div class="table-container">';
            // 显示备注信息
            $querySqlInfo = $sqls[$table];
            if (isset($querySqlInfo['remark'])) {
                echo '<p class="text-muted">', $querySqlInfo['remark'], '</p>';
            }
            // 查询数据
            $list = $this->AppBase->getPdoAdapter()->query($sql);
            // 应用函数到特殊字段
            $unixtimeFields = $querySqlConfigInfo['unixtime_fields'];
            if (!empty($querySqlInfo['function'])) {
                foreach ($list as &$row) {
                    foreach ($querySqlInfo['function'] as $field => $fn) {
                        if (isset($row[$field])) {
                            $row[$field] = $fn($row[$field]);
                            if ($unixtimeFields && in_array($field, $unixtimeFields)) {
                                $unixtimeFields = array_merge(array_diff($unixtimeFields, [$field]));
                            }
                        }
                    }
                }
            }
            // 还原时间戳字段
            if (!empty($unixtimeFields)) {
                foreach ($list as &$row) {
                    foreach ($unixtimeFields as $field) {
                        if (isset($row[$field]) && is_numeric($row[$field])) {
                            $row[$field] = date('Y-m-d H:i:s', $row[$field]);
                        }
                    }
                }
            }
            HtmlHandler::showTableWeb($list);
            echo '</div></section>';
        }
        echo '</div>';

        // 显示页面结构底部
        $scripts = <<<tpl
        // 一键重置用户数据
        function fnReset(elBtn) {
            let table=elBtn ? elBtn.dataset.table : '';
            if(table) {
                if (confirm('您确定要一键重置 '+table+' 表用户数据吗？')) {
                    location.href = '?env={$env}&db={$db}&group={$searchGroup}&action=dashboard&action2=reset&token={$token}&table='+table;
                }
                return;
            }
            if (confirm('您确定要一键重置用户数据吗？')) {
                location.href = '?env={$env}&db={$db}&group={$searchGroup}&action=dashboard&action2=reset&token={$token}';
            }
        }
        // 显示或隐藏内容
        function fnToggle(elBtn) {
            $(elBtn).parent().parent().parent().find('.table-container').toggle();
            elBtn.dataset.switch=elBtn.dataset.switch && elBtn.dataset.switch==0?1:0;
            elBtn.innerHTML = elBtn.dataset.switch==0 ? '&and;' : '&or;'
        }
        // 清空表数据
        function fnTruncate(elBtn) {
            if (confirm('您确定要清空数据表 '+elBtn.dataset.table+' 中的所有记录吗？')) {
                location.href = '?env={$env}&db={$db}&group={$searchGroup}&action=dashboard&action2=truncate&token={$token}&table='+elBtn.dataset.table;
            }
        }
        tpl;
        $this->showPageFoot($scripts);
        exit;
    }
    /**
     * 获取用户输入
     * @param string $name
     * @param mixed $defv
     * @param integer $filter
     * @param boolean $istrim
     * @return mixed
     */
    protected function input($name, $defv = '', $filter = FILTER_SANITIZE_STRING, $trim = true)
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
