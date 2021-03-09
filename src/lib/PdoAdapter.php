<?php

namespace mysqladminlite\lib;

/**
 * 数据库通用类
 * @version 2017-8-23 Added.
 * @version 2017-12-15 增加导出方法和解析服务器信息的方法
 */
class PdoAdapter
{

    /**
     * 数据库连接DSN
     * @var type
     */
    protected $dsn = '';

    /**
     * 数据库连接对象
     * @var type
     */
    protected $link = null;
    /**
     * 数据库环境信息, 数据库名称，数据库帐号等
     * host,database,user,password,port,charset
     * @var array
     */
    protected $envInfo = [];

    /**
     * 构造方法
     * @param array $dbcfg
     * @throws Exception
     */
    public function __construct(array $dbcfg)
    {
        $this->envInfo = $dbcfg;
        $this->dsn = $this->getDSN($dbcfg);
        if (empty($this->dsn)) {
            throw new \Exception('数据库配置文件无效!');
        }
        try {
            $this->link = new \PDO($this->dsn, $dbcfg['user'], $dbcfg['password']);
            $this->link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); // 提高错误级别
        } catch (\PDOException $ex) {
            // 命令行环境下错误消息显示不出来的解决方法：
            // 设置CMD环境编码为GBK:936,utf-8:65001，否则PDO实例化异常消息会乱码
            // exec("CHCP 936");
            // 浏览器环境下错误消息显示不出来的解决方法：
            // header("Content-Type:text/html;charset=utf-8");
            // 通用解决方法(直接转码)：
            // echo iconv('gbk', 'utf-8', $ex->getMessage());
            // $message = '数据库连接失败：请检查数据库是否可用或数据库连接信息是否正确 [' . $this->dsn . ']';
            $message = '数据库连接失败：' . iconv('gbk', 'utf-8', $ex->getMessage());
            throw new \Exception($message);
        }
    }

    public function getDSN(array $server)
    {
        // 解析host和port是否有端口转发
        $host = $server['host'];
        $port = $server['port'];
        // 数据库类型
        $adapter = isset($server['adapter']) ? $server['adapter'] : 'mysql';
        switch ($adapter) {
            case 'mysql':
                $dsnarr = ['host' => $host, 'port' => $port];
                if (isset($server['charset'])) {
                    $dsnarr['charset'] = $server['charset'];
                }
                if (!empty($server['database'])) {
                    $dsnarr['dbname'] = $server['database'];
                }
                $dsn = '';
                foreach ($dsnarr as $name => $value) {
                    $dsn .= $name . '=' . $value . ';';
                }
                $dsn = 'mysql:' . $dsn;
                break;
            default:
                $dsn = '';
                break;
        }
        return $dsn;
    }
    /**
     * 返回数据库服务器环境变量的值
     * host,database,user,password,port,charset
     * @param string $name 名称
     * @param mixed $defv 默认值
     */
    public function getEnvInfo($name, $defv = '')
    {
        if (!$name) {
            return $this->envInfo;
        }
        return isset($this->envInfo[$name]) ? $this->envInfo[$name] : $defv;
    }

    /**
     * 判断数据库名称是否是当前数据库,不一致则切换到该数据库
     * @param string $dbname 参数指定的数据库名称
     * @return string 数据库名称
     */
    public function checkDbName($dbname)
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        } else {
            // 动态切换数据库
            if ($dbname != $this->envInfo['database']) {
                try {
                    $this->selectDb($dbname);
                } catch (\PDOException $ex) {
                    exit($ex->getMessage());
                }
            }
        }
        return $dbname;
    }

    /**
     * 执行一条预处理语句
     * 这里不能用foreach，参考bindParam陷阱(值参数是引用型变量)：http://www.laruence.com/2012/10/16/2831.html
     * @param string $sqlstmt SQL 命令
     * @param array $params 参数值
     */
    public function execute($sqlstmt, $params = [])
    {
        try {
            $stmt = $this->link->prepare($sqlstmt);
            if ($params) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            return $stmt->rowCount();
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }
    /**
     * 执行一条SQL语句，并返回受影响的行数
     * @param string $sqlstmt
     */
    public function exec($sqlstmt)
    {
        try {
            return $this->link->exec($sqlstmt);
        } catch (\PDOException $ex) {
            // write log
            throw $ex;
        }
    }

    /**
     * 执行SQL 命令并返回一行
     * 这里不能用foreach，参考bindParam陷阱(值参数是引用型变量)：http://www.laruence.com/2012/10/16/2831.html
     * @param string $sqlstmt SQL 命令
     * @param array $params 参数值
     */
    public function find($sqlstmt, $params = [])
    {
        $stmt = $this->link->prepare($sqlstmt);
        try {
            if ($params) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            // write log
            throw $ex;
        }
    }

    /**
     * 执行SQL 命令并返回结果集
     * 这里不能用foreach，参考bindParam陷阱(值参数是引用型变量)：http://www.laruence.com/2012/10/16/2831.html
     * @param string $sqlstmt SQL 命令
     * @param array $params 参数值
     */
    public function query($sqlstmt, $params = [], $fetch_style = \PDO::FETCH_ASSOC)
    {
        $stmt = $this->link->prepare($sqlstmt);
        try {
            if ($params) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $list = $stmt->fetchAll($fetch_style);
            return $list;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    /**
     * 批处理执行SQL语句
     * 批处理的指令都认为是execute操作
     * @param array $asqls SQL批处理指令
     * @param boolean $isexecute 指示方法是执行或查询
     * @param boolean $isrollback 遇到错误是否回滚
     * @return array 错误消息数组
     * @version 1.0 2016-6-15 xcaller Added.
     * @example batchQuery($asql);
     */
    public function batchQuery($asqls = [], $isexecute = false, $isrollback = false)
    {
        // 错误消息数组
        $outdata = ['status' => 0, 'affected_rows' => 0, 'data' => ['没有任何命令']];
        if (!$asqls) {
            return $outdata;
        }
        // 如果参数是单条SQL 命令，则自动封装为数组
        if (!is_array($asqls)) {
            $asqls = array($asqls);
        }
        $outdata['data'] = []; // 清空错误消息数组
        $effectrows = 0; // 受影响的行数
        // 自动启动事务支持，提高执行性能        
        $this->link->beginTransaction(); // mysql_query("BEGIN");
        try {
            foreach ($asqls as $sql) {
                if (!trim($sql)) {
                    continue;
                }
                if ($isexecute) {
                    $result = $this->link->exec($sql);
                    $effectrows += $result;
                } else {
                    $result = $this->link->query($sql);
                }
                // 错误处理
                if (false === $result) {
                    // [SQLSTATE error code, Driver-specific error code, Driver-specific error message] | mysql_error();
                    $errorInfo = $this->link->errorInfo();
                    $result['data'][] = $errorInfo[2];
                    // 发生错误自动回滚事务
                    if ($isrollback) {
                        $this->link->rollBack(); // mysql_query("ROLLBACK");
                    }
                }
            }
            // 提交事务
            $this->link->commit(); // mysql_query("COMMIT"); mysql_query("END");
            $outdata['status'] = 1;
            $outdata['affected_rows'] = $effectrows;
            return $outdata;
        } catch (\PDOException $ex) {
            // 回滚事务
            $this->link->rollBack(); // mysql_query("ROLLBACK"); mysql_query("END");
            throw $ex;
        }
    }
    /**
     * 返回数据库版本号
     * @return string 数据库版本号
     */
    public function getDbVersion()
    {
        $versioninfo = $this->find('select version() as `version`;');
        return $versioninfo['version'];
    }
    /**
     * 返回数据库配置变量列表
     * @return array 配置变量列表
     */
    public function getDbVariables()
    {
        $sqlstmt = 'show global variables';
        try {
            $list = $this->query($sqlstmt);
            return $list;
        } catch (\PDOException $ex) {
            return [['Variable_name' => '查询数据库信息出错：', 'Value' => $ex->getMessage()]];
        }
    }

    /**
     * 返回数据库信息
     * @param string $dbname
     * @return array
     */
    public function getDbInfo($dbname)
    {
        $list = $this->getDatabases($dbname);
        return is_array($list) ? current($list) : [];
    }

    /**
     * 返回所有数据库
     * @param string $dbname 数据库名称
     * @return array SCHEMA_NAME,DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME
     */
    public function getDatabases($dbname = '')
    {
        $sqlstmt = 'select SCHEMA_NAME,DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME from '
            . 'information_schema.schemata where SCHEMA_NAME';
        if ($dbname) {
            $sqlstmt .= ' = ?';
            $params = [$dbname];
        } else {
            $sqlstmt .= ' not in (\'mysql\',\'information_schema\',\'performance_schema\');';
            $params = [];
        }
        $sqlstmt .= ' order by SCHEMA_NAME';
        return $this->query($sqlstmt, $params);
    }
    /**
     * 选择数据库
     */
    public function selectDb($dbname)
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }
        $this->execute('use ' . $dbname);
    }

    /**
     * 返回所有数据表
     * @param string $dbname
     * @param string $tablename
     * @return array TABLE_NAME, TABLE_TYPE, ENGINE, DATA_LENGTH, CREATE_TIME, TABLE_COLLATION, TABLE_COMMENT
     */
    public function getTables($dbname, $tablename = '')
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }

        $sqlstmt = 'select TABLE_NAME, TABLE_TYPE, ENGINE, DATA_LENGTH, CREATE_TIME, TABLE_COLLATION, TABLE_COMMENT '
            . 'from information_schema.tables where TABLE_SCHEMA=:database AND TABLE_TYPE=\'BASE TABLE\'';
        $params = ['database' => $dbname];
        if ($tablename) {
            $sqlstmt .= ' AND TABLE_NAME = :table';
            $params['table'] = $tablename;
        } else {
            $sqlstmt .= ' order by TABLE_NAME';
        }
        return $this->query($sqlstmt, $params);
    }

    /**
     * 返回数据表元数据
     * @param string $dbname
     * @param string $tablename
     * @param string $field
     * @return array TABLE_NAME, TABLE_TYPE, ENGINE, DATA_LENGTH, CREATE_TIME, TABLE_COLLATION, TABLE_COMMENT
     */
    public function getTableInfo($dbname, $tablename, $field = '')
    {
        $list = $this->getTables($dbname, $tablename);
        if (is_array($list)) {
            $data = current($list);
            if ($field && isset($data[$field])) {
                return $data[$field];
            }
            return $data;
        }
        return null;
    }
    /**
     * 返回数据表记录数量少于指定数量的表集合
     * @param string $dbname
     * @param int $eltcount
     */
    public function getEmptyTables($dbname, $eltcount = 0, $prefix = '')
    {
        $this->checkDbName($dbname);
        $list = $this->query('show tables;');
        $tables = [];
        $table = '';
        foreach ($list as $row) {
            $table = current($row);
            if ($prefix && false === strpos($table, $prefix)) {
                continue;
            }
            $count = $this->count($table);
            if ($count > $eltcount) {
                continue;
            }
            $tables[$table] = $row;
        }
        return $tables;
    }
    /**
     * 判断数据表是否存在
     */
    public function hasTable($tablename, $dbname = '')
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }
        $sqlstmt = 'select count(*) from information_schema.tables where TABLE_SCHEMA=:database AND TABLE_NAME=:table;';
        $stmt = $this->link->prepare($sqlstmt);
        $stmt->execute(['database' => $dbname, 'table' => $tablename]);
        return current($stmt->fetch(\PDO::FETCH_ASSOC)) > 0;
    }
    /**
     * 查询数据表记录数量
     * @param string $table
     */
    public function count($table)
    {
        $sqlstmt = "select count(*) as n from $table;";
        $stmt = $this->link->prepare($sqlstmt);
        try {
            $stmt->execute();
            return current($stmt->fetch(\PDO::FETCH_ASSOC));
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    /**
     * 返回所有索引
     * @param string $dbname
     * @param string $tablename
     * @return array Table, Non_unique, Key_name, Seq_in_index, Column_name, Collation, Cardinality, Sub_part, Packed,
     *          Null, Index_type
     */
    public function getIndexes($dbname, $tablename)
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }
        $list = $this->query('show index from ' . filter_var($dbname) . '.' . filter_var($tablename));
        return $list;
    }

    /**
     * 返回所有列
     * @param string $dbname
     * @param string $tablename
     * @param boolean $withKeyName
     * @return array COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE,
     *      EXTRA, COLUMN_DEFAULT, COLUMN_TYPE, COLUMN_KEY, COLUMN_COMMENT
     */
    public function getColumns($dbname, $tablename, $withKeyName = false)
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }
        $sqlstmt = 'select COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, COLUMN_TYPE, IS_NULLABLE, EXTRA, DATA_TYPE, '
            . 'CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_KEY, COLUMN_COMMENT from '
            . 'information_schema.columns WHERE TABLE_SCHEMA=:database AND TABLE_NAME=:table order by ORDINAL_POSITION';
        $list = $this->query($sqlstmt, ['database' => $dbname, 'table' => $tablename]);
        if ($withKeyName) {
            $list2 = [];
            foreach ($list as $row) {
                $list2[$row['COLUMN_NAME']] = $row;
            }
            $list = $list2;
        }
        return $list;
    }

    /**
     * 返回所有视图
     * @param string $dbname
     * @return array TABLE_NAME
     */
    public function getViews($dbname)
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }
        $sqlstmt = 'select TABLE_NAME from information_schema.views where TABLE_SCHEMA=:database order by TABLE_NAME;';
        $list = $this->query($sqlstmt, ['database' => $dbname]);
        return $list;
    }

    /**
     * 返回所有程序
     * @param string $dbname
     * @param string $type
     * @return array ROUTINE_NAME, ROUTINE_TYPE
     */
    public function getRoutines($dbname, $type = '')
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }
        if ($type) {
            $type = strtoupper($type);
        }
        $sqlstmt = 'select ROUTINE_NAME, ROUTINE_TYPE from information_schema.routines WHERE ROUTINE_SCHEMA=:database'
            . ($type ? " and ROUTINE_TYPE='$type'" : '') . ' order by ROUTINE_TYPE, ROUTINE_NAME;';
        $list = $this->query($sqlstmt, ['database' => $dbname]);
        return $list;
    }

    /**
     * 返回程序的所有参数
     * <br />ORDINAL_POSITION=0为返回参数
     * @param string $dbname
     * @param string $routine
     * @return array ORDINAL_POSITION, PARAMETER_MODE, PARAMETER_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH,
     *          DTD_IDENTIFIER
     */
    public function getRoutineParams($dbname, $routine)
    {
        if (!$dbname) {
            $dbname = $this->envInfo['database'];
        }
        $sqlstmt = 'select ORDINAL_POSITION, PARAMETER_MODE, PARAMETER_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, '
            . 'DTD_IDENTIFIER, ROUTINE_TYPE from information_schema.PARAMETERS where SPECIFIC_SCHEMA=:database and '
            . 'SPECIFIC_NAME=:routine';
        $list = $this->query($sqlstmt, ['database' => $dbname, 'routine' => $routine]);
        return $list;
    }
    /**
     * 返回程序的所有参数定义
     * @param string $dbname
     * @param string $routine
     * @return array
     */
    public function getRoutineParamsSimple($dbname, $routine)
    {
        $list = $this->getRoutineParams($dbname, $routine);
        $outlist = [];
        foreach ($list as $row) {
            $objname = $row['PARAMETER_NAME'];
            $datatype = $row['DATA_TYPE'];

            if ($objname) {
                $outlist[] = $row['PARAMETER_MODE'] . ' ' . $objname . ' '
                    . ($datatype == 'varchar' ? $row['DTD_IDENTIFIER'] : $datatype);
            } else {
                $outlist[] = 'returns: ' . ($datatype == 'varchar' ? $row['DTD_IDENTIFIER'] : $datatype);
            }
        }
        return $outlist;
    }

    /**
     * 获取全文搜索的命令
     */
    public function getFullTextSearchSqls($word, $table)
    {
        if (!$word || !$table) {
            return [];
        }
        $allowTypes = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'char', 'varchar'];
        $charTypes = ['char', 'varchar'];
        $isNumType = is_numeric($word);
        $tables = [];
        if ($table == 'all') {
            $tableInfos = $this->getTables('');
            foreach ($tableInfos as $row) {
                $tables[] = $row['TABLE_NAME'];
            }
        } else {
            $tables[] = $table;
        }
        $where = [];
        $sqls = [];
        foreach ($tables as $table) {
            $where = [];
            $columns = $this->getColumns('', $table);
            foreach ($columns as $column) {
                $dataType = $column['DATA_TYPE']; // 小写
                if (!in_array($dataType, $allowTypes)) {
                    continue;
                }
                // 关键词是数字，则所有字段类型都查询；关键词是字符串，则只查询字符串类型的字段
                if (!$isNumType && !in_array($dataType, $charTypes)) {
                    continue;
                }
                $where[] = "`{$column['COLUMN_NAME']}` like '%{$word}%'";
            }
            if ($where) {
                $sqls[] = "select * from {$table} where " . implode(' or ', $where);
            }
        }
        return $sqls;
    }
}
