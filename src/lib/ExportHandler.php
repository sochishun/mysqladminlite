<?php

namespace mysqladminlite\lib;

class ExportHandler
{
    private $_pdoAdapter;

    function __construct($pdoAdapter)
    {
        $this->_pdoAdapter = $pdoAdapter;
    }
    /**
     * 导出数据库表结构到 PHP 文件
     * @param string $database
     * @param string $table
     * @param boolean $isEachFile
     * @param string $filename
     * @param string $tablePrefix
     * @version 2020-2-6
     */
    public function exportDbSchemaAsPhpForCli($database, $table, $isEachFile, $filename = '', $tablePrefix = '')
    {
        $option = ['isDownload' => false, 'isEachFile' => $isEachFile];
        $this->exportDbSchemaAsSql($database, $table, $option, $filename, 'php', $tablePrefix);
    }
    /**
     * 导出数据库表结构到 PHP 文件
     * @param string $database
     * @param string $table
     */
    public function exportDbSchemaAsPhpForDownload($database, $table = '')
    {
        $option = ['isDownload' => true, 'isEachFile' => false];
        $this->exportDbSchemaAsSql($database, $table, $option, '', 'php', '');
    }
    /**
     * 导出数据库表结构到 SQL 文件
     * @param string $database
     * @param string $table
     * @param boolean $isEachFile
     * @param string $filename
     * @param string $tablePrefix
     * @version 2020-2-6
     */
    public function exportDbSchemaAsSqlForCli($database, $table, $isEachFile, $filename = '', $tablePrefix = '')
    {
        $option = ['isDownload' => false, 'isEachFile' => $isEachFile];
        $this->exportDbSchemaAsSql($database, $table, $option, $filename, $tablePrefix);
    }
    /**
     * 导出数据库表结构到 SQL 文件
     * @param string $database
     * @param string $table
     */
    public function exportDbSchemaAsSqlForDownload($database, $table = '')
    {
        $option = ['isDownload' => true, 'isEachFile' => false];
        $this->exportDbSchemaAsSql($database, $table, $option, '', '');
    }
    /**
     * 导出数据库表结构到 SQL 文件
     * @param string $database
     * @param string $table
     * @param array $option
     * @param string $filename
     * @param string $tablePrefix
     * @version 2020-2-6
     */
    private function exportDbSchemaAsSql($database, $table, $option, $filename = '', $fileType = 'sql', $tablePrefix = '')
    {
        $defaultoption = ['isDownload' => true, 'isEachFile' => true];
        extract(array_merge($defaultoption, $option));

        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        if (!$filename) {
            $filename = $table ? $table : $database;
            $filename = "mysal-{$filename}.{$fileType}";
        }
        if ($isDownload) {
            header("Content-type:application/octet-stream");
            header('Content-Disposition:attachment; filename=' . $filename);
        }
        $list = $this->_pdoAdapter->getTables($database, $table);
        $content = '';
        $contenthd = '';
        if ($fileType == 'php') {
            $contenthd = '<?php' . PHP_EOL . 'return ';
            if (!$isEachFile) {
                $contenthd .= 'array(' . PHP_EOL . "'_lock' => false," . PHP_EOL . "'_version' => '1.0.0'," . PHP_EOL;
            }
        }
        $content = $contenthd;
        $data = [];
        $objname = '';
        foreach ($list as $row) {
            $objname = $row['TABLE_NAME'];
            if ($tablePrefix && false === strpos($objname, $tablePrefix)) {
                continue;
            }
            if ($fileType == 'php') {
                $createsql = $this->_pdoAdapter->find('show create table ' . $objname . ';');
                $createsql = trim($createsql['Create Table']);
                $columns = $this->_pdoAdapter->getColumns($database, $objname, true);
                $data = [
                    '_lock' => false,
                    '_enable' => true,
                    '_version' => '1.0.0',
                    'TABLE_NAME' => $row['TABLE_NAME'],
                    'TABLE_COMMENT' => $row['TABLE_COMMENT'],
                    'ENGINE' => $row['ENGINE'],
                    'TABLE_COLLATION' => $row['TABLE_COLLATION'],
                    'TABLE_COLUMNS' => $columns,
                    'CREATE_SQL' => $createsql,
                ];
                if ($isEachFile) {
                    $content .= var_export($data, true) . ';' . PHP_EOL;
                } else {
                    unset($data['_lock']);
                    unset($data['_version']);
                    $content .= "'{$objname}'=>" . var_export($data, true) . ',' . PHP_EOL;
                }
            } else {
                $createsql = $this->_pdoAdapter->find('show create table ' . $objname . ';');
                $createsql = trim($createsql['Create Table']);
                if (substr($createsql, -1) != ';') {
                    $createsql .= ';';
                }
                $content .= 'DROP TABLE IF EXISTS `' . $objname . '`;' . PHP_EOL;
                $content .= $createsql . PHP_EOL . PHP_EOL;
            }
            if ($isEachFile && !$isDownload) {
                file_put_contents("{$filename}{$objname}.{$fileType}", $content);
                $content = $contenthd;
            }
        }
        unset($list);
        if ($isDownload) {
            echo $content;
            exit();
        } else {
            if (!$isEachFile) {
                if ($fileType == 'php') {
                    $content .= ');';
                }
                $dirname = dirname($filename);
                if (!is_dir(dirname($dirname))) {
                    mkdir(dirname($dirname), 0777, true);
                }
                file_put_contents($filename, $content);
            }
        }
    }

    /**
     * 导出数据库表结构到 Markdown 文件
     * @param string $database
     * @version 2017-12-15
     */
    public function exportDbSchemaAsMarkdown($database, $prefix = '')
    {
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        header("Content-type:application/octet-stream");
        header("Content-Disposition:attachment; filename=mysal-$database.md");

        $list = $this->_pdoAdapter->getTables($database);
        foreach ($list as $row) {
            $objname = $row['TABLE_NAME'];
            if ($prefix && false === strpos($objname, $prefix)) {
                continue;
            }
            $columns = $this->_pdoAdapter->getColumns($database, $objname);
            if ($columns) {
                echo '### ', $objname, PHP_EOL, PHP_EOL;
                echo '| 字段 | 数据类型 | 可空 | 默认值 | 索引键 | 备注 |', PHP_EOL;
                echo '| --- | --- | --- | --- | --- |', PHP_EOL;
                foreach ($columns as $col) {
                    echo '| ', $col['COLUMN_NAME'], ' | ', $col['COLUMN_TYPE'], ' | ', $col['IS_NULLABLE'], ' | ';
                    echo $col['COLUMN_DEFAULT'], ' | ', $col['COLUMN_KEY'], ' | ';
                    echo SqlHandler::parseDbComment($col['COLUMN_COMMENT']), ' |', PHP_EOL;
                }
                echo PHP_EOL, PHP_EOL;
            }
        }
        unset($list);
        exit();
    }

    /**
     * 导出数据库表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $filename 文件名
     * @param string $tablePrefix 数据表前缀
     */
    public function exportDbSchemaAsExcelForCli($database, $filename = '', $tablePrefix = '')
    {
        $option = ['isExcel' => true, 'isDownload' => false, 'isLocalFile' => true];
        $this->exportDbSchemaAsExcel($database, $option, $filename, $tablePrefix);
    }

    /**
     * 导出数据库表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $filename 文件名
     * @param string $tablePrefix 数据表前缀
     */
    public function exportDbSchemaAsHtmlForCli($database, $filename = '', $tablePrefix = '')
    {
        $option = ['isExcel' => false, 'isDownload' => false, 'isLocalFile' => true];
        $this->exportDbSchemaAsExcel($database, $option, $filename, $tablePrefix);
    }
    /**
     * 导出数据库表结构到 Excel 文件
     * @param string $database 数据库名称
     */
    public function exportDbSchemaAsHtmlOnline($database)
    {
        $option = ['isExcel' => false, 'isDownload' => false, 'isLocalFile' => false];
        $this->exportDbSchemaAsExcel($database, $option, '', '');
    }
    /**
     * 导出数据库表结构到 Excel 文件
     * @param string $database 数据库名称
     */
    public function exportDbSchemaAsHtmlForDownload($database)
    {
        $option = ['isExcel' => false, 'isDownload' => true, 'isLocalFile' => false];
        $this->exportDbSchemaAsExcel($database, $option, '', '');
    }
    /**
     * 导出数据库表结构到 Excel 文件
     * @param string $database 数据库名称
     */
    public function exportDbSchemaAsExcelForDownload($database)
    {
        $option = ['isExcel' => true, 'isDownload' => true, 'isLocalFile' => false];
        $this->exportDbSchemaAsExcel($database, $option, '', '');
    }
    /**
     * 导出数据库表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param array $option 选项参数
     * @param string $filename 文件名
     * @param string $tablePrefix 数据表前缀
     * @version 2020-1-13
     */
    private function exportDbSchemaAsExcel($database, $option, $filename = '', $tablePrefix = '')
    {
        $defaultoption = ['isExcel' => true, 'isDownload' => true, 'isLocalFile' => false];
        extract(array_merge($defaultoption, $option));

        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        if (!$filename) {
            $filename = 'mysal-' . $database . ($isExcel ? '.xls' : '.html');
        }
        if ($isDownload) {
            HtmlHandler::setExportHeader($isExcel, $filename);
        }
        $content = '';
        if ($isExcel) {
            $content .= '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:'
                . 'office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta http-equiv=Content-Type content='
                . '"text/html; charset=utf-8"></head><body>';
        } else {
            $content .= '<html><head><title>数据库 ' . $database . ' 表结构</title><style type="text/css">body{color:#333;'
                . 'font-size:12px;}h3{margin:5px 0;}h3 a{color:#333;}h3 small{font-size:12px;color:#aaa;margin-left:'
                . '10px;}section{width:400px;height:250px;margin:0 10px 10px 0;float:left;}.content{height:210px;'
                . 'overflow:auto;}table{width:100%; border:solid 1px #CCC;border-top:none;border-left:none;}th'
                . '{white-space:nowrap;}th,td{padding:3px;font-size:12px;border:solid 1px #CCC;border-right:none;'
                . 'border-bottom:none;}</style></head></body>';
        }

        $list = $this->_pdoAdapter->getTables($database);
        $content .= '<h1>数据库 ' . $database . ' 表结构</h1><div class="summary">日期：' . date('Y-m-d H:i:s')
            . '<br />数据表：' . count($list) . '个</div>';
        foreach ($list as $row) {
            $objname = $row['TABLE_NAME'];
            if ($tablePrefix && false === strpos($objname, $tablePrefix)) {
                continue;
            }
            $columns = $this->_pdoAdapter->getColumns($database, $objname);
            $indexes = $this->_pdoAdapter->getIndexes($database, $objname);
            $content .= '<section><h3>';
            if (!$isExcel) {
                $content .= '<a href="#" onclick="this.parentNode.parentNode.style.display=\'none\';return false;" '
                    . 'title="隐藏此模型">[x]</a> ';
            }
            $content .= $objname . ' <small>';
            $content .= ($row['TABLE_COMMENT'] ? ('(' . $row['TABLE_COMMENT'] . ') ') : '');
            $content .= count($columns) . ' 个字段</small></h3>';
            if ($columns) {
                if ($isExcel) {
                    $content .= '<table border="1">';
                } else {
                    $content .= '<div class="content"><table cellspacing="0" cellpadding="0">';
                }
                $content .= '<tr><th>--</th><th>字段</th><th>数据类型</th><th>可空</th><th>默认值</th><th>索引键</th>'
                    . '<th>备注</th></tr>';
                $i = 0;
                foreach ($columns as $col) {
                    $i++;
                    $content .= '<tr><td>' . $i . '</td><td>' . $col['COLUMN_NAME'] . '</td><td>' . $col['COLUMN_TYPE']
                        . '</td><td>' . $col['IS_NULLABLE'] . '</td><td>' . $col['COLUMN_DEFAULT'] . '</td><td>'
                        . $col['COLUMN_KEY'] . '</td><td>' . SqlHandler::parseDbComment($col['COLUMN_COMMENT'])
                        . '</td></tr>';
                }
                $content .= '</table>';
            }
            if ($indexes) {
                $content .= $isExcel ? '<table border="1">' : '<table cellspacing="0" cellpadding="0" '
                    . 'style="margin-top:1px"><tr><th>索引名称</th><th>列名称</th></tr>';
                foreach ($indexes as $idx) {
                    $content .= '<tr><td>' . $idx['Key_name'] . '</td><td>' . $idx['Column_name'] . '</td></tr>';
                }
                $content .= '</table>';
            }
            $content .= '</div></section>';
        }
        $content .= '</body></html>';
        if ($isLocalFile) {
            file_put_contents($filename, $content);
        } else {
            echo $content;
            exit();
        }
    }

    /**
     * 导出数据库所有表结构到数据种子文件
     * @param string $database 数据库名称
     * @param string $table 数据表名称
     * @param string $framework 数据种子框架
     * @param string $dir 种子文件目录路径
     * @param boolean $overwrite 是否覆盖
     * @version 2020-2-16
     */
    public function exportDbSchemaAsSeed($database, $framework = 'phinx', $dir = '', $overwrite = false, $eltcount = 0, $prefix = '', $count = 1000)
    {
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        if ($eltcount > 0) {
            $list = $this->_pdoAdapter->getEmptyTables($database, $eltcount);
        } else {
            $list = $this->_pdoAdapter->getTables($database);
        }
        if (!$list) {
            return [0, 0, 0];
        }
        $ido = 0;
        $i = 0;
        $objname = '';
        foreach ($list as $row) {
            $objname = $row['TABLE_NAME'];
            if ($prefix && false === strpos($objname, $prefix)) {
                continue;
            }
            $filename = $dir . CommonHelper::table2classname($objname) . 'Seeder.php';
            $result = $this->exportTableSchemaAsSeedForCli($database, $objname, $framework, $filename, $overwrite, $count);
            if ($result) {
                $ido++;
            }
            $i++;
        }
        return [$i, $ido, $i - $ido];
    }


    /**
     * 导出表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $table 数据表名称
     * @param string $filename 文件名
     */
    public function exportTableSchemaAsExcelForCli($database, $table, $filename = '')
    {
        $option = ['isExcel' => true, 'isDownload' => false, 'isLocalFile' => true];
        $this->exportTableSchemaAsExcel($database, $table, $option, $filename);
    }

    /**
     * 导出表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $table 数据表名称
     * @param string $filename 文件名
     */
    public function exportTableSchemaAsHtmlForCli($database, $table, $filename = '')
    {
        $option = ['isExcel' => false, 'isDownload' => false, 'isLocalFile' => true];
        $this->exportTableSchemaAsExcel($database, $table, $option, $filename);
    }

    /**
     * 导出表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $table 数据表名称
     */
    public function exportTableSchemaAsHtmlOnline($database, $table)
    {
        $option = ['isExcel' => false, 'isDownload' => false, 'isLocalFile' => false];
        $this->exportTableSchemaAsExcel($database, $table, $option, '');
    }

    /**
     * 导出表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $table 数据表名称
     */
    public function exportTableSchemaAsHtmlForDownload($database, $table)
    {
        $option = ['isExcel' => false, 'isDownload' => true, 'isLocalFile' => false];
        $this->exportTableSchemaAsExcel($database, $table, $option, '');
    }
    /**
     * 导出表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $table 数据表名称
     */
    public function exportTableSchemaAsExcelForDownload($database, $table)
    {
        $option = ['isExcel' => true, 'isDownload' => true, 'isLocalFile' => false];
        $this->exportTableSchemaAsExcel($database, $table, $option, '');
    }
    /**
     * 导出表结构到 Excel 文件
     * @param string $database 数据库名称
     * @param string $table 数据表名称
     * @param array $option 选项参数
     * @param string $filename 文件名
     * @version 2020-2-7
     */
    private function exportTableSchemaAsExcel($database, $table, $option, $filename = '')
    {
        $defaultoption = ['isExcel' => true, 'isDownload' => true, 'isLocalFile' => false];
        extract(array_merge($defaultoption, $option));

        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        if ($isExcel) {
            $fileExt = '.xls';
            $content = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:'
                . 'office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta http-equiv=Content-Type content="'
                . 'text/html; charset=utf-8"></head><body>';
            $tableBegin = '<div><table border="1">';
            $tableBeginIndex = '<table border="1">';
        } else {
            $fileExt = '.html';
            $content = '<html><head><title>数据表 ' . $table . ' 模型信息</title><style type="text/css">body'
                . '{color:#333;font-size:12px;}h1 div{font-size:13px;}h3{margin-bottom:5px;}h3 a{color:#333;}'
                . 'h3 small{font-size:12px;color:#aaa;margin-left:10px;}section{max-width:900px;}'
                . 'th{white-space:nowrap;}td,th{font-size:13px;}.grid{border:solid 1px #CCC;border-bottom:none;'
                . 'border-right:none;}.grid tr:hover{background-color:#efefef;}.grid tr.active{background-color:#fc0;}'
                . '.grid th,.grid td{border:solid 1px #CCC;border-left:none;border-top:none;padding:5px;}.grid th'
                . '{word-break:keep-all;}.grid th a{font-weight:normal;margin-left:3px;}.bluegrid tr:nth-child(even)'
                . '{background:#e6e6e6;}.bluegrid th{background:rgb(81,130,187);color:#FFF;}.bluegrid th a{color:#FFF;}'
                . '</style></head></body>';
            $tableBegin = '<div class="content"><table class="grid bluegrid" cellspacing="0" cellpadding="0">';
            $tableBeginIndex = '<table class="grid bluegrid" cellspacing="0" cellpadding="0">';
        }
        if (!$filename) {
            $filename = 'mysal-' . $table . $fileExt;
        }
        if ($isDownload) {
            HtmlHandler::setExportHeader($isExcel, $filename);
        }
        $row = $this->_pdoAdapter->getTableInfo($database, $table);
        $columns = $this->_pdoAdapter->getColumns($database, $table);
        $indexes = $this->_pdoAdapter->getIndexes($database, $table);
        $content .= '<h1>数据表 ' . $table . '</h1><div>日期：' . date('Y-m-d H:i:s') . '</div>';
        $content .= '<section><h3>';
        $content .= $table . ' <small>';
        $content .= ($row['TABLE_COMMENT'] ? ('(' . $row['TABLE_COMMENT'] . ') ') : '');
        $content .= count($columns) . ' 个字段</small></h3>';
        if ($columns) {
            $content .= $tableBegin . '<tr><th>--</th><th>字段</th><th>数据类型</th><th>可空</th><th>默认值</th><th>索引键</th>'
                . '<th>备注</th></tr>';
            $i = 0;
            foreach ($columns as $col) {
                $i++;
                $content .= '<tr><td>' . $i . '</td><td>' . $col['COLUMN_NAME'] . '</td><td>'
                    . $col['COLUMN_TYPE'] . '</td><td>';
                $content .= $col['IS_NULLABLE'] . '</td><td>' . $col['COLUMN_DEFAULT'] . '</td><td>'
                    . $col['COLUMN_KEY'] . '</td><td>';
                $content .= SqlHandler::parseDbComment($col['COLUMN_COMMENT']) . '</td></tr>';
            }
            $content .= '</table>';
        }
        if ($indexes) {
            $content .= $tableBeginIndex . '<tr><th>索引名称</th><th>列名称</th></tr>';
            foreach ($indexes as $idx) {
                $content .= '<tr><td>' . $idx['Key_name'] . '</td><td>' . $idx['Column_name'] . '</td></tr>';
            }
            $content .= '</table>';
        }
        $content .= '</div></section>';
        $content .= '</body></html>';
        if ($isLocalFile) {
            file_put_contents($filename, $content);
        } else {
            echo $content;
            exit();
        }
    }

    /**
     * 导出数据表结构到 Phinx Migrate 迁移文件
     * @param string $database
     * @param string $table
     * @param string $framework
     * @param string $filename
     * @param boolean $overwrite
     */
    public function exportTableSchemaAsMigrateForCli(
        $database,
        $table,
        $framework = 'phinx',
        $filename = '',
        $overwrite = false
    ) {
        $option = ['isDownload' => false, 'overwrite' => $overwrite];
        $this->exportTableSchemaAsMigrate($database, $table, $option, $framework, $filename);
    }
    /**
     * 导出数据表结构到 Phinx Migrate 迁移文件
     * @param string $database
     * @param string $table
     * @param string $framework
     */
    public function exportTableSchemaAsMigrateForDownload($database, $table, $framework = 'phinx')
    {
        $option = ['isDownload' => true, 'overwrite' => false];
        $this->exportTableSchemaAsMigrate($database, $table, $option, $framework, '');
    }
    /**
     * 导出数据表结构到 Phinx Migrate 迁移文件
     * 如果不知道什么是Phinx可以参考百度一下cakephp Phinx
     * @param string $database 当前数据库
     * @param bool $table 当前数据表
     * @param array $option 参数选项
     * @param string $framework 框架名称
     * @version 2020-2-5
     */
    private function exportTableSchemaAsMigrate($database, $table, $option, $framework = 'phinx', $filename = '')
    {
        $defaultoption = ['isDownload' => true, 'overwrite' => true];
        extract(array_merge($defaultoption, $option));
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);
        $classname = CommonHelper::table2classname($table);
        if ($isDownload) {
            if (!$filename) {
                $filename = date('YmdHis') . '_' . $classname . '.php';
            }
            header("Content-type:application/octet-stream");
            header('Content-Disposition:attachment; filename=' . $filename);
        }

        $tableInfo = $this->_pdoAdapter->getTableInfo($database, $table);
        $columns = $this->_pdoAdapter->getColumns($database, $table);
        $handler = new MigrationHandler();
        $content = $handler->getTemplateContent($framework, $classname, $table, $tableInfo, $columns);
        if ($isDownload) {
            echo $content;
            exit();
        } else {
            file_put_contents($filename, $content);
        }
    }

    /**
     * 导出数据表结构到 Phinx Seed 测试数据文件
     * @param string $database
     * @param string $table
     * @param string $framework
     * @param string $filename
     * @param boolean $overwrite
     */
    public function exportTableSchemaAsSeedForCli($database, $table, $framework = 'phinx', $filename = '', $overwrite = false, $count = 1000)
    {
        $option = ['isDownload' => false, 'overwrite' => $overwrite, 'count' => $count];
        return $this->exportTableSchemaAsSeed($database, $table, $option, $framework, $filename);
    }
    /**
     * 导出数据表结构到 Phinx Seed 测试数据文件
     * @param string $database
     * @param string $table
     * @param string $framework
     */
    public function exportTableSchemaAsSeedForDownload($database, $table, $framework = 'phinx')
    {
        $option = ['isDownload' => true, 'overwrite' => false];
        $this->exportTableSchemaAsSeed($database, $table, $option, $framework, '');
    }
    /**
     * 导出数据表结构到 Phinx Seed 测试数据文件
     * @param string $database
     * @param string $table
     * @param array $option
     * @param string $framework
     * @param string $filename
     * @version 2020-2-5
     */
    private function exportTableSchemaAsSeed($database, $table, $option, $framework = 'phinx', $filename = '')
    {
        $defaultoption = ['isDownload' => true, 'overwrite' => true, 'count' => 1000];
        extract(array_merge($defaultoption, $option));

        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        $classname = CommonHelper::table2classname($table) . 'Seeder';
        if (!$filename) {
            $filename = $classname . '.php';
        }
        if ($isDownload) {
            header("Content-type:application/octet-stream");
            header('Content-Disposition:attachment; filename=' . $filename);
        }
        $comment = $this->_pdoAdapter->getTableInfo($database, $table, 'TABLE_COMMENT');
        $columns = $this->_pdoAdapter->getColumns($database, $table);
        $seedHandler = new SeedHandler();
        $content = $seedHandler->getTemplateContent($framework, $classname, $table, $comment, $columns, $database, $count);
        if ($isDownload) {
            echo $content;
            exit();
        } else {
            if (!$overwrite && file_exists($filename)) {
                return false;
            }
            file_put_contents($filename, $content);
            return true;
        }
    }
    /**
     * 导出查询结果到 Json 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     */
    public function exportQueryDataAsJsonForWeb($database, $table, $sqlstmt)
    {
        $option = ['isCli' => false, 'isDownload' => false];
        $this->exportQueryDataAsJson($database, $table, $sqlstmt, $option);
    }

    /**
     * 导出查询结果到 Json 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     */
    public function exportQueryDataAsJsonForDownload($database, $table, $sqlstmt)
    {
        $option = ['isCli' => false, 'isDownload' => true];
        $this->exportQueryDataAsJson($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Json 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     * @param string $filename
     */
    public function exportQueryDataAsJsonForCli($database, $table, $sqlstmt, $filename)
    {
        $option = ['isCli' => true, 'isDownload' => false, 'filename' => $filename];
        $this->exportQueryDataAsJson($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Json 文件
     * @param string $database
     * @param string $sqlstmt
     * @param array $option
     */
    public function exportQueryDataAsJson($database, $table, $sqlstmt, $option)
    {
        $defaultoption = ['isCli' => false, 'isDownload' => false, 'filename' => ''];
        extract(array_merge($defaultoption, $option));
        if (!$isCli && !$isDownload) {
            header('Content-Type:application/json; charset=utf-8');
        }
        if ($isDownload) {
            header("Content-type:application/octet-stream");
            header("Content-Disposition:attachment; filename=mysal-" . ($table ? $table : '') . ".json");
        }
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);
        $list = $this->_pdoAdapter->query($sqlstmt);
        if ($isCli) {
            file_put_contents($filename, json_encode($list));
        } else {
            echo json_encode($list);
            exit();
        }
    }
    public function exportQueryDataAsMarkdownForCli($database, $table, $sqlstmt, $filename)
    {
        $option = ['isCli' => true, 'isDownload' => false, 'filename' => $filename];
        $this->exportQueryDataAsMarkdown($database, $table, $sqlstmt, $option);
    }
    public function exportQueryDataAsMarkdownForWeb($database, $table, $sqlstmt)
    {
        $option = ['isCli' => false, 'isDownload' => false, 'filename' => ''];
        $this->exportQueryDataAsMarkdown($database, $table, $sqlstmt, $option);
    }
    public function exportQueryDataAsMarkdownForDownload($database, $table, $sqlstmt)
    {
        $option = ['isCli' => false, 'isDownload' => true, 'filename' => ''];
        $this->exportQueryDataAsMarkdown($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Markdown 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     * @param array $option
     * @version 2017-12-15
     */
    public function exportQueryDataAsMarkdown($database, $table, $sqlstmt, $option)
    {
        $defaultoption = ['isCli' => false, 'isDownload' => false, 'filename' => ''];
        extract(array_merge($defaultoption, $option));
        if ($isDownload) {
            if (!$filename) {
                $filename = 'mysal-' . ($table ? $table . '-' : '') . date('Ymd-His') . '.md';
            }
            header("Content-type:application/octet-stream");
            header("Content-Disposition:attachment; filename={$filename}");
        }
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);
        $ln = ($isCli || $isDownload) ? PHP_EOL : '<br />';
        $list = $this->_pdoAdapter->query($sqlstmt);
        $content = '### 导出数据 ' . date('Ymd-His') . $ln . $ln;
        if ($table) {
            $content .= '对象：' . $table . $ln;
        }
        $content .= '数量：' . count($list) . $ln;
        $content .= '命令：' . str_replace('`', '\`', $sqlstmt) . $ln . $ln;
        if ($list) {
            $i = 0;
            foreach ($list as $row) {
                if ($i < 1) {
                    $content .= '| ' . implode(' | ', array_keys($row)) . ' |' . $ln;
                    $content .= '| ' . str_repeat('--- |', count($row)) . $ln;
                }
                $i++;
                $content .= '| ' . implode(" | ", array_values($row)) . ' |' . $ln;
            }
            unset($list);
        } else {
            $content .= '没有查询到任何记录!';
        }
        $content .= $ln . $ln;
        if ($isCli) {
            file_put_contents($filename, $content);
        } else {
            echo $content;
            exit();
        }
    }
    /**
     * 导出查询结果到 Excel 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     */
    public function exportQueryDataAsHtmlOnline($database, $table, $sqlstmt)
    {
        $option = ['isCli' => false, 'isExcel' => false, 'isDownload' => false];
        $this->exportQueryDataAsExcel($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Excel 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     */
    public function exportQueryDataAsHtmlForDownload($database, $table, $sqlstmt)
    {
        $option = ['isCli' => false, 'isExcel' => false, 'isDownload' => true];
        $this->exportQueryDataAsExcel($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Excel 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     * @param string $filename
     */
    public function exportQueryDataAsHtmlForCli($database, $table, $sqlstmt, $filename)
    {
        $option = ['isCli' => true, 'isExcel' => false, 'isDownload' => false, 'filename' => $filename];
        $this->exportQueryDataAsExcel($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Excel 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     */
    public function exportQueryDataAsExcelForDownload($database, $table, $sqlstmt)
    {
        $option = ['isCli' => false, 'isExcel' => true, 'isDownload' => true];
        $this->exportQueryDataAsExcel($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Excel 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     * @param string $filename
     */
    public function exportQueryDataAsExcelForCli($database, $table, $sqlstmt, $filename)
    {
        $option = ['isCli' => true, 'isExcel' => true, 'isDownload' => false, 'filename' => $filename];
        $this->exportQueryDataAsExcel($database, $table, $sqlstmt, $option);
    }
    /**
     * 导出查询结果到 Excel 文件
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     * @param array $option
     * @version 2018-1-10
     */
    public function exportQueryDataAsExcel($database, $table, $sqlstmt, $option)
    {
        $defaultoption = ['isCli' => false, 'isExcel' => true, 'isDownload' => true, 'filename' => ''];
        extract(array_merge($defaultoption, $option));
        if ($isExcel) {
            header("Content-Type: application/vnd.ms-excel; name='excel'");
        }
        if ($isDownload) {
            if (!$filename) {
                $filename = 'mysal-' . ($table ? ($table . '-') : '') . date('Ymd-His')
                    . ($isExcel ? '.xls' : '.html');
            }
            header('Content-type:application/octet-stream');
            header('Content-Disposition:attachment; filename=' . $filename);
        }
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        $list = $this->_pdoAdapter->query($sqlstmt);
        if ($list) {
            if ($isExcel) {
                $content = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-'
                    . 'microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta http-equiv='
                    . 'Content-Type content="text/html; charset=utf-8"></head><body>';
                $tableBegin = '<table border="1">';
            } else {
                $content = '<html><head><title>导出数据 ' . $table . '</title>';
                $content .= '<style>body{font-size:13px;}h1{font-size:18px;margin:5px 0;}.codearea{margin-bottom:'
                    . '10px;}code{display:block;background-color:#384548;color:#abe338;max-width:900px;max-height:'
                    . '150px;overflow:auto;padding:5px;line-height:18x;}td,th{font-size:13px;}.grid{border:solid 1px '
                    . '#CCC;border-bottom:none;border-right:none;}.grid tr:hover{background-color:#efefef;}.grid '
                    . 'tr.active{background-color:#fc0;}.grid th,.grid td{border:solid 1px #CCC;border-left:none;'
                    . 'border-top:none;padding:5px;}.grid th{word-break:keep-all;}.grid th a{font-weight:normal;'
                    . 'margin-left:3px;}.bluegrid tr:nth-child(even){background:#e6e6e6;}.bluegrid th{background:'
                    . 'rgb(81,130,187);color:#FFF;}.bluegrid th a{color:#FFF;}</style></head><body>';
                $tableBegin = '<table cellpadding="0" cellspacing="0" class="grid bluegrid">';
            }
            $content .= '<h1>导出数据 ' . $table . '</h1>';
            $content .= '<div class="codearea">时间：' . date('Y-m-d H:i:s') . '<br />SQL 命令：<code>select '
                . '`condition`, `createtime`, `icon`, `id`, `ismenu`, `name`, `pid`, `remark`, `status`, `title`, '
                . '`type`, `updatetime`, `weigh` from fa_auth_rule order by `condition` desc limit 50;</code></div>'
                . $tableBegin;
            $i = 0;
            foreach ($list as $row) {
                if ($i < 1) {
                    $content .= '<tr><th>--</th><th>' . implode('</th><th>', array_keys($row)) . '</th></tr>';
                }
                $i++;
                $content .= '<tr><td>' . $i . '</td><td>' . implode('</td><td>', array_values($row)) . '</td></tr>';
            }
            unset($list);
            $content .= '</table></body></html>';
        } else {
            $content = '没有查询到任何记录!';
        }
        if ($isCli) {
            file_put_contents($filename, $content);
        } else {
            echo $content;
            exit();
        }
    }

    /**
     * 导出查询结果到 SQL 脚本
     * @param string $database
     * @param string $table
     * @param string|array $sqlstmt
     * @param string $filename
     */
    public function exportQueryDataAsSqlForCli($database, $table, $sqlstmt, $filename, $isTree = false)
    {
        $this->exportQueryDataAsSql($database, $table, $sqlstmt, false, $filename, $isTree);
    }
    /**
     * 导出查询结果到 SQL 脚本
     * @param string $database
     * @param string $table
     * @param string $sqlstmt
     */
    public function exportQueryDataAsSqlForDownload($database, $table, $sqlstmt)
    {
        $this->exportQueryDataAsSql($database, $table, $sqlstmt, true);
    }
    /**
     * 导出查询结果到 SQL 脚本
     * @param string $database
     * @param string $table
     * @param string|array $sqlstmt
     * @param boolean $isDownload
     * @param string $filename
     */
    public function exportQueryDataAsSql($database, $table, $sql, $isDownload = true, $filename = '', $isTree = false)
    {
        if (!$filename) {
            $filename = 'mysal-' . $table . '-' . date('Ymd-His') . '.sql';
        }
        if ($isDownload) {
            header('Content-type:application/octet-stream');
            header('Content-Disposition:attachment; filename=' . $filename);
        }
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);
        // COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE,
        // EXTRA, COLUMN_COMMENT
        if (!is_array($sql)) {
            $sql = [$table => $sql];
        }
        $content = '';
        try {
            foreach ($sql as $table => $sqlstmt) {
                $columns = $this->_pdoAdapter->getColumns($database, $table);
                if (!$columns) {
                    continue;
                }
                if ($isTree) {
                    $hasTmpField = false;
                    foreach ($columns as $column) {
                        if ($column['COLUMN_NAME'] == 'tmp_pname') {
                            $hasTmpField = true;
                            break;
                        }
                    }
                    if (!$hasTmpField) {
                        $this->_pdoAdapter->exec("alter table `{$table}` add tmp_name varchar(30), add tmp_pname varchar(30);");
                        $this->_pdoAdapter->exec("update `{$table}` set tmp_name=concat('id-',id), tmp_pname=concat('id-',pid) where id > 0;");
                        $columns = $this->_pdoAdapter->getColumns($database, $table);
                    }
                }
                $fields = [];
                foreach ($columns as $column) {
                    $fields[$column['COLUMN_NAME']] = $column;
                }
                $list = $this->_pdoAdapter->query($sqlstmt);
                if (!$list) {
                    echo '命令：', $sqlstmt, ' 没有查询到任何记录! ', PHP_EOL;
                    continue;
                }
                $content .= '/* ' . PHP_EOL . '数据脚本';
                $content .= '对象：' . $table;
                $content .= '数量：' . count($list) . PHP_EOL;
                $content .= '时间：' . date('Y-m-d H:i:s') . PHP_EOL;
                $content .= '命令：' . $sqlstmt . PHP_EOL;
                $content .= '*/' . PHP_EOL . PHP_EOL;
                if ($isTree) {
                    $createsql = $this->_pdoAdapter->find('show create table ' . $table . ';');
                    $content .= trim($createsql['Create Table']) . ';' . PHP_EOL . PHP_EOL;
                }
                $content .= "-- alter table `{$table}` add tmp_name varchar(30), add tmp_pname varchar(30);" . PHP_EOL;
                $i = 0;
                $strfields = '';
                $values = [];
                $column = [];
                $numbericTypes = ['int', 'smallint', 'bigint', 'decimal', 'float'];
                foreach ($list as $row) {
                    if ($isTree) {
                        if (isset($row['id'])) {
                            unset($row['id']);
                        }
                        if (isset($row['pid'])) {
                            unset($row['pid']);
                        }
                    }
                    if ($i < 1) {
                        $strfields = implode('`,`', array_keys($row));
                    }
                    $values = [];
                    foreach ($row as $field => $value) {
                        $column = $fields[$field];
                        if (is_null($value)) {
                            $values[] = 'null';
                        } elseif (in_array($column['DATA_TYPE'], $numbericTypes)) {
                            $values[] = $value;
                        } else {
                            $values[] = "'$value'";
                        }
                    }
                    $content .= 'insert into ' . $table . ' (`' . $strfields . '`) values (' . implode(",", $values) . ');' . PHP_EOL . PHP_EOL;
                    $i++;
                }
                if ($isTree) {
                    $content .= "-- update `{$table}` t1 inner join `{$table}` t2 on t1.tmp_pname=t2.tmp_name set t1.pid=t2.id where t1.pid = 0;" . PHP_EOL;
                    // $this->_pdoAdapter->exec("alter table `{$table}` drop column tmp_name, drop column tmp_pname");
                }
            }
        } catch (\PDOException $ex) {
            echo $ex->getMessage();
            return false;
        }
        if ($isDownload) {
            echo $content;
            exit();
        } else {
            file_put_contents($filename, $content);
            return true;
        }
    }

    /**
     * 显示执行结果
     * @param array $sqlstmts
     * @param string $env
     * @param string $database
     * @param string $table
     */
    public function showQueryResultForCli($sqlstmts, $env = '', $database = '', $table = '')
    {
        $isCli = true;
        $this->showQueryResult($sqlstmts, compact('env', 'database', 'table', 'isCli'));
    }
    /**
     * 显示执行结果
     * @param array $sqlstmts
     * @param string $env
     * @param string $database
     * @param string $table
     * @param string $token
     */
    public function showQueryResultForWeb($sqlstmts, $env = '', $database = '', $table = '', $token = '')
    {
        $isCli = false;
        $this->showQueryResult($sqlstmts, compact('env', 'database', 'table', 'token', 'isCli'));
    }
    /**
     * 显示执行结果
     * @param string $sqlstmt SQL 命令
     * @param array $option 参数选项
     */
    private function showQueryResult($sqlstmts, $option)
    {
        $defaultOption = [
            'env' => '',
            'database' => '',
            'table' => '',
            'token' => '',
            'isCli' => false,
        ];
        extract(array_merge($defaultOption, $option));
        if (!$sqlstmts) {
            return;
        }
        // 动态切换数据库
        $database = $this->_pdoAdapter->checkDbName($database);

        $sqlstmtcount = 0; // 有效命令数量
        foreach ($sqlstmts as $sqlstmtone) {
            if (!$sqlstmtone) {
                continue; // 跳过空语句
            }
            $sqlstmtone = trim($sqlstmtone); // 过滤首尾空格
            $whitespacePos = strpos($sqlstmtone, ' ');
            if (false === $whitespacePos) {
                $sqlstmtprefix = $sqlstmtone;
            } else {
                $sqlstmtprefix = trim(strtolower(substr($sqlstmtone, 0, $whitespacePos))); // 命令前缀
            }
            if ($sqlstmtprefix == 'eg.') {
                continue; // 过滤eg.示例前缀
            }
            if ($table) { // 替换语句中的 {#object} 变量，省得表名很长的时候要写很累 2017-12-15
                $sqlstmtone = str_replace('{#object}', $table, $sqlstmtone);
            }
            $sqlstmtcount++;
            switch ($sqlstmtprefix) {
                case 'select':
                case 'show':
                case 'desc':
                    if ($isCli) {
                        echo '# ', $sqlstmtcount, ' [', CommonHelper::shortText($sqlstmtone, 100), '] ';
                    } else {
                        $url = "?env={$env}&db={$database}&table={$table}&token={$token}&sql=" . urlencode($sqlstmtone);
                        echo '<div><div class="btn-group btn-group-sm" title="第', $sqlstmtcount, '条命令">';
                        echo '<a class="btn lnk-switch" href="#" title="展开或收起详情内容">&equiv;</a>';
                        echo '<a class="btn text-info" disabled style="font-size:15px">查询结果 (#', $sqlstmtcount,
                        ')</a>';
                        echo '<a class="btn btn-light" href="', $url, '&action=export-query-md" target="_blank" title="导出',
                        '结果集到 Markdown 文件">导出 MD</a>';
                        echo '<a class="btn btn-light" href="', $url, '&action=export-query-excel" target="_blank" title="导出',
                        '结果集到 Excel 文件">导出 Excel</a>';
                        echo '<a class="btn btn-light" href="', $url, '&action=export-query-html" target="_blank" title="导出',
                        '结果集到HTML文件">导出 HTML</a>';
                        echo '<a class="btn btn-light" href="', $url, '&action=export-query-json" target="_blank" title="导出',
                        '结果集到 JSON 文件">导出 JSON</a>';
                        echo '<a class="btn btn-light" href="', $url, '&action=export-query-jsononline" target="_blank" title="导出',
                        '结果集到 JSON 文件">在线 JSON</a>';
                        if ($database && $table) {
                            echo '<a class="btn btn-light" href="', $url, '&action=export-query-sql" target="_blank" title="',
                            '导出结果集到SQL文件">导出 SQL</a>';
                            echo '<a class="btn btn-light" href="', $url, '&action=export-query-htmlonline" target="_blank" ',
                            'title="在新页面显示结果集">新页面查看</a>';
                        }
                        echo '</div>';
                        echo '<div class="fieldset_content">';
                        echo '<code class="bg-light border p-1" style="display:block;max-height:60px;overflow:auto">', $sqlstmtone, '</code>';
                        echo '<div class="table-container" style="max-height:200px;overflow-y:auto">';
                    }
                    try {
                        $list = $this->_pdoAdapter->query($sqlstmtone);
                        if ($isCli) {
                            echo '共查询到 ', count($list), ' 条记录', PHP_EOL;
                            HtmlHandler::showTableCli($list, false, true);
                        } else {
                            HtmlHandler::showTableWeb($list);
                        }
                        if ($list) {
                            unset($list);
                        }
                    } catch (\Exception $ex) {
                        if ($isCli) {
                            echo $ex->getMessage();
                        } else {
                            echo '<p>', $ex->getMessage(), '</p>';
                        }
                    }
                    if (!$isCli) {
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                    break;
                case 'insert':
                case 'update':
                case 'delete':
                case 'truncate':
                case 'alter':
                case 'create':
                case 'drop':
                case 'grant': // 更改数据库用户授权
                case 'revoke': // 撤销用户权限
                case 'flush': // flush privileges;
                case 'set': // 更改或设置用户密码
                    if (!$isCli) {
                        echo '<fieldset>';
                        echo '<legendtitle="第', $sqlstmtcount, '条命令">执行结果 (#', $sqlstmtcount, ')';
                        echo '<code class="bg-light border p-1" style="display:block;max-height:60px;overflow:auto">', $sqlstmtone, '</code>';
                    }
                    try {
                        $result = $this->_pdoAdapter->execute($sqlstmtone);
                        if ($isCli) {
                            echo '# ', $sqlstmtcount, ' [', CommonHelper::shortText($sqlstmtone, 30), '] 执行结果影响 ';
                            echo $result, ' 行! ', PHP_EOL;
                        } else {
                            $url = '?env=' . $env;
                            if ($database) {
                                $url .= '&db=' . $database;
                            }
                            if ($table) {
                                $url .= '&table=' . $table;
                            }
                            $url .= '&token=' . $token;
                            echo '<p style="margin:2px 0">执行结果影响 ', $result, ' 行! ';
                            echo '<a class="btn btn-sm btn-outline-secondary" href="', $url, '" ',
                            'style="padding:0 0.5rem">刷新</a></p>'; // 刷新侧边栏
                        }
                    } catch (\Exception $ex) {
                        if ($isCli) {
                            echo '# ', $sqlstmtcount, ' 命令执行错误：', PHP_EOL, $ex->getMessage(), PHP_EOL;
                            echo '[', CommonHelper::shortText($sqlstmtone, 120), ']', PHP_EOL, PHP_EOL;
                        } else {
                            echo '<p><span class="text-danger">命令执行错误：</span><br />', $ex->getMessage(), '</p>';
                        }
                    }
                    if (!$isCli) {
                        echo '</fieldset>';
                    }
                    break;
                default:
                    if ($isCli) {
                        echo '# ', $sqlstmtcount, ' 不支持的命令：', $sqlstmtone;
                    } else {
                        echo '<p><strong style="color:#F00">不支持的命令</strong>：', $sqlstmtone, '</p>';
                    }
                    break;
            }
        }
    }
}
