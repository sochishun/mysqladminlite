<?php

namespace mysqladminlite\lib;

/**
 * SQL 命令助手类
 */
class SqlHandler
{
    /**
     * 生成数据列 SQL 语句
     * @param array $column
     */
    public static function makeColumnSql(array $column)
    {
        $sql = $column['COLUMN_NAME'] . ' ' . $column['COLUMN_TYPE'];
        $sql .= $column['IS_NULLABLE'] == 'NO' ? ' NOT NULL' : ' NULL';
        $default = $column['COLUMN_DEFAULT'];
        if (!is_null($default)) {
            $sql .=  " DEFAULT '{$default}'";
        }
        $comment = $column['COLUMN_COMMENT'];
        if ($comment) {
            $sql .= " COMMENT '{$comment}'";
        }
        return $sql;
    }

    /**
     * 格式化sql脚本
     * <br />去除表注释、字段注释、多余空格、drop命令
     * @param string $str
     * @param string $break_line
     * @return string
     */
    public static function formatSql($str, $break_line = '\r')
    {
        $str = preg_replace("/ comment '[^']*'/", '', $str); // comment '...'
        $str = preg_replace("/ comment='[^']*'/", '', $str); // comment='...'
        $str = preg_replace("/--[^$break_line]+/", '', $str); // -- ...
        $str = preg_replace("/\/\*[^\/]+\//", '', $str); // /*...*/
        $str = preg_replace("/drop[^;]+;/", '', $str); // drop table ...;
        $str = preg_replace('/[\s]+/', ' ', trim($str)); // 多个空格合并成一个
        return $str;
    }

    /**
     * 解析 SQL 语句中的首个对象名称
     * @param string $sqlstmt
     * @return array [object, type]
     * @version 2017-12-20
     */
    public static function getObjectBySql($sqlstmt)
    {
        $return = array('object' => '', 'type' => '');
        if (!$sqlstmt) {
            return $return;
        }
        $sqlstmt = trim($sqlstmt);
        if (false === strpos($sqlstmt, ' ')) {
            return $return;
        }
        $count = 0;
        $firstword = substr($sqlstmt, 0, strpos($sqlstmt, ' '));
        switch ($firstword) {
            case 'select': // select ... from ($table|$view) | select $func
                $count = preg_match('/ from[\s]+([^\s|^\(]+)/', $sqlstmt, $matches);
                if (!$count && false === strpos($sqlstmt, ' from ')) {
                    $return['type'] = 'function';
                    $count = preg_match('/select[\s]+([^\s|^\(]+)/', $sqlstmt, $matches);
                }
                break;
            case 'delete': // delete from $table
                $count = preg_match('/ from[\s]+([^\s]+)/', $sqlstmt, $matches);
                break;
            case 'insert': // insert into $table
                $count = preg_match('/ into[\s]+([^\s]+)/', $sqlstmt, $matches);
                break;
            case 'update': // update $table
                $count = preg_match('/update[\s]+([^\s]+)/', $sqlstmt, $matches);
                break;
            case 'alter': // alter table $table
                $count = preg_match('/ table[\s]+([^\s]+)/', $sqlstmt, $matches);
                break;
            case 'call': // call $proc
                $count = preg_match('/call[\s]+([^\s|^\(]+)/', $sqlstmt, $matches);
                $return['type'] = 'procedure';
                break;
        }
        if ($count) {
            $return['object'] = $matches[1];
            if (!$return['type']) {
                $return['type'] = 'table';
            }
        }
        return $return;
    }
    /**
     * 过滤数据库备注信息
     * @param string $comment
     * @return string
     */
    public static function parseDbComment($comment)
    {
        return addslashes(str_replace(['|', PHP_EOL], ['\|', ' '], $comment));
    }
    /**
     * 加载 sql 文件为分号分割的数组，注意最后一条命令语句要以分号结尾，否则最后一条命令不会解析出来
     * 支持存储过程和函数提取，自动过滤注释
     * @param string $path 文件路径
     * @return boolean|array
     * @version 1.0 2015-5-27 Added.
     * @version 1.1 2016-9-6 修正 drop function 或 drop procedure 无法解析的问题
     */
    public static function parseSqlStatement($lines, $routinesplitor = ';;')
    {
        // $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // 读取文件到数组
        $outlines = [];
        $stmt = '';
        $isroutine = false;
        $routinesplitorend = $routinesplitor[0];
        $isskip = false; // 是否过滤批量注释
        foreach ($lines as $line) {
            $line = trim($line); // 过滤头尾空格
            if (!$line) {
                continue; // 过滤空行
            }
            if (0 === strpos($line, '/*')) { // 批量注释开始
                if (false === strpos($line, '*/')) { // 如果不是单行注释，则从此行开始，一直过滤到发现*/字符为止
                    $isskip = true; // 过滤批量注释
                }
                continue; // 该批量注释是单行注释
            }
            if ($isskip && (false !== strpos($line, '*/'))) { // 批量注释结束
                $isskip = false;
                continue;
            }
            if ($isskip) {
                continue; // 过滤批量注释
            }
            if (0 === strpos($line, '-- ')) {
                continue; // 过滤单行注释
            }
            $lowerline = strtolower($line);
            // 过滤注释 ...
            // 提取存储过程和函数
            if (!$isroutine && 0 === strpos($lowerline, 'delimiter ' . $routinesplitor)) {
                $isroutine = true;
                continue;
            }
            if ($isroutine) {
                // drop 语句独立一行
                if (0 === strpos($lowerline, 'drop function ') || 0 === strpos($lowerline, 'drop procedure ')) {
                    $outlines[] = $line;
                    $stmt = '';
                    continue;
                }
                if (0 === strpos($lowerline, 'delimiter ' . $routinesplitorend)) {
                    $isroutine = false;
                    $outlines[] = $stmt;
                    $stmt = '';
                    continue;
                }
                $stmt .= $line . ' ';
                continue;
            }
            // 提取普通语句，支持一个语句拆分成很多行的写法，注意语句结束后必须以分号结尾
            $stmt .= $line . ' '; // 串联行内容要加个空格，防止 select a\rfrom table，拼凑成 select afrom table
            if (false !== strpos($line, ';') && strlen($line) == (strpos($line, ';') + 1)) {
                $outlines[] = $stmt;
                $stmt = '';
            }
        }
        // 兼容单sql语句，且没有以分号结尾的情况
        // if (!$outlines && $stmt) { // 这样写最后一条命令如果没有分号结尾会解析不出来
        if ($stmt) {
            $outlines[] = $stmt;
        }
        return $outlines;
    }
    /**
     * 加载 sql 文件为分号分割的数组
     * parseSqlStatement() 的替代方法，去除单行注释内容有问题，还没有经过测试，暂时不要用
     * 思路是：提取引号内容--将引号内容中的注释和分号转义保留--分割分号数组--循环数组并对数组元素还原注释转义
     * @version 2020-3-2
     */
    private function parseBatchSqls($sqltext)
    {
        $symbols = ['\\\'' => '#@sq#', '\\\"' => '#@dq#'];
        $sqltext = str_replace(array_keys($symbols), array_values($symbols), $sqltext);
        $pattern = '/([\'"])([^\'"\.]*?)\1/';
        $pattern = '/[\'"]([^\'"]*)[\'"]/';
        $sqltext = preg_replace_callback($pattern, function ($a) {
            $a[0] = str_replace(';', '#@sem#', $a[0]); // 分号处理
            $a[0] = preg_replace('/(--.*)|(\/\*(.|\s)*?\*\/)|(\n)/', '', $a[0]); // 注释处理
            return $a[0];
        }, $sqltext);
        $sqls = explode(';', $sqltext); // 拆分成数组
        $sqls = array_filter($sqls); // 过滤空元素
        $outsqls = [];
        $symbols[';'] = '#@sem#';
        foreach ($sqls as $sql) {
            $outsqls[] = str_replace(array_values($symbols), array_keys($symbols), $sql);
        }
        return $outsqls;
    }

    /**
     * 解析批量测试数据生成语句
     * @param string $sqlstmt
     * @return string
     * @version 2017-11-24
     */
    public static function parseSeedSqlStatement($sqlstmt)
    {
        $table = ''; // 测试数据表
        $count = 5; // 默认生成 5 条数据
        $start = 1; // 起始序号
        $sqlstmtcount = 0;
        $testdatafields = []; // 测试数据所有字段定义
        $sqlstmts = explode("\r", $sqlstmt); // textarea 回车符号是 \r，和 OS 平台无关
        // 逐行分析字段
        foreach ($sqlstmts as $sqlstmtone) {
            $sqlstmtone = trim($sqlstmtone);
            $pos = strpos($sqlstmtone, '// ');
            if (0 === $pos) {
                continue; // 删除注释
            }
            if ($pos) {
                $sqlstmtone = substr($sqlstmtone, 0, $pos); // 删除注释
            }
            if ($sqlstmtcount < 1) {
                $rowarr = explode(' ', $sqlstmtone, 2); // 第一行格式务必 "testdata {tablename},{count},{start}"
                $table = $rowarr[1];
                $tabletmparr = explode(',', $table);
                switch (count($tabletmparr)) {
                    case 3:
                        list($table, $count, $start) = $tabletmparr;
                        break;
                    case 2:
                        list($table, $count) = $tabletmparr;
                        break;
                }
                $sqlstmtcount = 1;
                continue;
            }
            $rowarr = explode('=', $sqlstmtone, 2); // 字段定义格式务必 "field=value"，一个一行
            $value = $rowarr[1];
            $testdatafields[$rowarr[0]] = $value;
            $sqlstmtcount = 1;
        }
        $testdatavaluesarr = [];
        for ($i = 0; $i < $count; $i++) {
            // 逐行分析
            // 支持函数: {i}, {rand(min,max)},{rang(1,3,5)}, {time()}, {date('Y-m-d H:i:s')}
            $valuearr = [];
            foreach ($testdatafields as $value) {
                if (false !== strpos($value, '{i}')) {
                    $value = str_replace('{i}', $start + $i, $value);
                }
                if (preg_match("/\{rand\(([\d]+),([\d]+)\)\}/", $value, $matches)) {
                    // 随机数：{rand(min,max)}, 如 测试{rand(1,10)}
                    $value = str_replace($matches[0], rand($matches[1], $matches[2]), $value);
                }
                if (preg_match("/\{rang\((.+)\)\}/", $value, $matches)) {
                    // 范围值：{rang(v1,v2,v3)}, 如 测试{rang(A,B,C)}
                    $fnvaluearr = explode(',', $matches[1]);
                    $value = str_replace($matches[0], $fnvaluearr[array_rand($fnvaluearr)], $value);
                }
                if (preg_match("/\{time\(\)\}/", $value, $matches)) {
                    // 时间戳：{time()}, 如 测试{time()}
                    $value = str_replace($matches[0], time(), $value);
                }
                if (preg_match("/\{date\((.+)\)\}/", $value, $matches)) {
                    // 日期时间：{date('Y-m-d H:i:s')}, 如 测试{date('Y-m-d H:i:s')}
                    $value = str_replace($matches[0], date($matches[1]), $value);
                }
                $valuearr[] = $value;
            }
            $testdatavaluesarr[] = "(" . implode(",", $valuearr) . ")";
        }
        $sqlstmt = 'insert into ' . $table . ' (`' . implode('`, `', array_keys($testdatafields)) . '`) values '
            . implode(',', $testdatavaluesarr) . ';';
        return $sqlstmt;
    }
    /**
     * 解析快速查询命令
     * @param string $tablename 数据表名称
     * @param array $fastsqls 快速命令配置内容
     * @return array 返回解析后的快速命令
     */
    public static function parseFastSqls($tablename, $fastsqls)
    {
        $quickquerysqls = [];
        if (!empty($fastsqls['-group-'])) {
            foreach ($fastsqls['-group-'] as $key => $val) {
                if (in_array($tablename, $val)) {
                    $quickquerysqls = array_key_exists($key, $fastsqls) ? $fastsqls[$key] : [];
                    break;
                }
            }
        }
        if (empty($quickquerysqls) && array_key_exists($tablename, $fastsqls)) {
            $quickquerysqls = $fastsqls[$tablename];
        }
        return $quickquerysqls;
    }
    /**
     * 获取示例语句
     * @param string $action
     * @return string
     * @version 1.0 2017-10-16
     */
    public static function getDefaultExampleSql($action, $isComment = true)
    {
        $sqlstmt = '';
        $egsqlarr = [];
        switch ($action) {
            case 'egcreate_database':
                $egsqlarr = [
                    'CREATE DATABASE IF NOT EXISTS db_test DEFAULT CHARSET utf8 COLLATE utf8_general_ci;',
                    'CREATE DATABASE IF NOT EXISTS db_test DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_general_ci;',
                    '-- ----',
                    '-- utf8 和 utf8mb4 区别：',
                    '-- 1. utf8 一个字符占用 3 个字节，utf8mb4 一个字符占用 4 个字节，但实际应用中，数据库体积差距可以忽略不计',
                    '-- 2. utf8 不支持 emoji 表情，utf8mb4 支持 emoji 表情',
                    '-- 3. 在排序和校对规则方面，utf8_unicode_ci 比较准确，utf8_general_ci 速度比较快',
                    '-- 4. 如果数据表使用 utf8mb4 编码，数据库也要使用 utf8mb4 编码',
                    '-- 5. 字段的最大长度是 767 字节，utf8 编码可以支持 255 个字符（255*3=765），utf8mb4 只能支持 191 个'
                        . '字符（191*4bit=764）',
                    '-- 6. 建议普通表使用 utf8，如果这个表需要支持 emoji 表情符号就使用 utf8mb4',
                    '-- 7. 包含 emoji 表情符号的字段需设置为编码为 utf8mb4_unicode_ci，字段最长为 191 个字符，否则会报错：'
                        . 'Specified key was too long; max key length is 767 bytes',
                    "-- -- 例如 `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';"
                ];
                break;
            case 'egcreate_table':
                $egsqlarr = array(
                    "drop table if exists `t_test`;",
                    "create table if not exists `t_test` (",
                    "   id int auto_increment primary key comment '主键编号',",
                    "   `name` varchar(32) not null default '' comment '名称',",
                    "   `content` varchar(20480) not null default '' comment '内容',",
                    "   user_id int not null default 0 comment '用户编号',",
                    "   ordinal smallint not null default 0 comment '排列次序',",
                    "   `status` smallint not null default 0 comment '状态:0=未审核,1=正常,4=关闭',",
                    "   created_time timestamp not null default CURRENT_TIMESTAMP comment '创建时间',",
                    "   PRIMARY KEY(id),",
                    "   UNIQUE  (user_id, `name`),",
                    "   FOREIGN KEY (category_id) REFERENCES t_category(id)",
                    "   CONSTRAINT `fk_test_category_id` FOREIGN KEY (`category_id`) REFERENCES `t_category` (`id`),",
                    "   INDEX idx_userid (user_id)",
                    ") comment 'test表[v1.0 " . date('Y-m-d') . " by author]' ENGINE=InnoDB default CHARSET=utf8;",
                );
                break;
            case 'egcreate_view':
                $egsqlarr = array(
                    "drop view if exists `v_test`;",
                    "create or replace view v_test as select t1.*,t2.colname from t_test1 t1 left join t_test2 t2 "
                        . "on t1.id=t2.fkid order by t1.id desc;",
                );
                break;
            case 'egcreate_function':
                $egsqlarr = array(
                    "DELIMITER ;;",
                    "drop function if exists fn_indexOf;;",
                    "create function fn_indexOf",
                    "(",
                    "    -- 要分割的字符串",
                    "    pstr_text varchar(20480),",
                    "    -- 分隔符号",
                    "    pstr_splitor varchar(10),",
                    "    -- 取第几个元素",
                    "    pint_index int",
                    ")",
                    "returns varchar(1024)",
                    "begin",
                    "    declare vint_location int;",
                    "    declare vint_start int;",
                    "    declare vint_next int;",
                    "    declare vint_seed int;",
                    "    set pstr_text=ltrim(rtrim(pstr_text));",
                    "    set vint_start=1;",
                    "    set vint_next=1;",
                    "    set vint_seed=length(pstr_splitor);",
                    "    set vint_location=position(pstr_splitor in pstr_text);",
                    "    while vint_location<>0 and pint_index>vint_next do",
                    "        set vint_start=vint_location+vint_seed;",
                    "        set vint_location=locate(pstr_splitor,pstr_text,vint_start);",
                    "        set vint_next=vint_next+1;",
                    "    end while;",
                    "    if vint_location =0 THEN",
                    "        set vint_location =length(pstr_text)+1;",
                    "    end if;",
                    "    -- 这里存在两种情况：1、字符串不存在分隔符号 2、字符串中存在分隔符号，跳出 while 循环后，"
                        . "vint_location为0，那默认为字符串后边有一个分隔符号。",
                    "    set pstr_text=substring(pstr_text,vint_start,vint_location-vint_start);",
                    "    return pstr_text;",
                    "end;;",
                    "DELIMITER ;",
                );
                $sqlstmt = "/**\r获取数组中指定位置的元素\r如果超过界限则返回最后界限的值\r@version 1.0 2014-11-12\r"
                    . "调用示例：select func_indexOf('8,9,4',',',2)\r返回值：9\r*/\r";
                break;
            case 'egcreate_procedure':
                $egsqlarr = array(
                    "DELIMITER ;;",
                    "drop procedure if exists proc_array_test;;",
                    "create procedure proc_array_test(pstr_numbers text)",
                    "begin",
                    "    declare vstr_item varchar(255);",
                    "    declare vint_next int;",
                    "    set vint_next=1;",
                    "    while vint_next<=func_count_split(pstr_numbers,'+') do",
                    "        set vstr_item = func_indexOf(pstr_numbers,'+',vint_next);",
                    "        set vstr_item=REPLACE(vstr_item,':',' and phoneNumber=\'');",
                    "        set vstr_item=CONCAT('update t_cti_call_plan set state=1 where taskID=',vstr_item,'\';');",
                    "        set @vstr_sql=vstr_item;",
                    "        prepare stmt from @vstr_sql;",
                    "        execute stmt;",
                    "        deallocate prepare stmt;",
                    "       set vint_next=vint_next+1;",
                    "    end while;",
                    "end;;",
                    "DELIMITER ;",
                );
                $sqlstmt = "/**\r* 批量更新号码状态\r* @version 1.0 2014-11-1 by author\r* @example call proc_array_test"
                    . "('1212:13950076987+1211:13950076987+1210:13950076987');\r*/\r";
                break;
            case 'egcreate_trigger':
                $egsqlarr = array(
                    "delimiter ;;",
                    "create trigger trg_update_test after update on t_test for each row",
                    "begin",
                    "    call proc_update_test(t_test.id,t_test.status);",
                    "end;;",
                    "delimiter ;",
                    "--",
                    "DELIMITER ;;",
                    "create trigger trg_insert_user alter insert on t_user for each row",
                    "begin",
                    "    insert into t_finance_account (userID) values (t_user.id);",
                    "end;;",
                    "DELIMITER ;",
                );
                break;
            case 'egcreate_user':
                $egsqlarr = array(
                    '-- 创建用户同时授权',
                    "GRANT ALL PRIVILEGES ON databasename.tablename TO 'username'@'host' IDENTIFIED BY '123456';",
                    "GRANT ALL PRIVILEGES ON `db_test`.* TO 'db_user'@'localhost' IDENTIFIED BY '123456';",
                    "FLUSH PRIVILEGES;",
                    "-- 创建用户",
                    "CREATE USER 'username'@'host' IDENTIFIED BY 'password';",
                    "eg. CREATE USER 'db_user'@'127.0.0.1' IDENTIFIED BY '123456';",
                    "-- 授权用户",
                    "GRANT privileges ON databasename.tablename TO 'username'@'host'",
                    "eg. GRANT SELECT, INSERT ON `db_test`.* TO 'db_user'@'localhost';",
                    '-- 设置与更改用户密码',
                    "SET PASSWORD FOR 'username'@'host' = PASSWORD('newpassword');",
                    '-- 撤销用户权限',
                    "REVOKE privilege ON databasename.tablename FROM 'username'@'host';",
                    "eg. REVOKE SELECT ON mq.* FROM 'dog2'@'localhost';",
                    '-- 删除用户',
                    "DROP USER 'username'@'host';",
                    "eg. DROP USER 'db_user'@'127.0.0.1';",
                    '-- 查看用户的授权',
                    "SHOW GRANTS FOR 'username'@'host';",
                    "eg. SHOW GRANTS FOR db_user@127.0.0.1;",
                );
                break;
            case 'egexport_import':
                $egsqlarr = array(
                    '-- 导出整个数据库的表结构，包含数据',
                    'eg. mysqldump -uroot -proot --add-drop-table db_test > ./db_test.sql',
                    '-- 只导出整个数据库的表结构，不含数据（-d 等同于 --no-data）',
                    'eg. mysqldump -uroot -proot -d --add-drop-table db_test > ./db_test.sql',
                    '-- 只导出整个数据库数据，不包含表结构（-t 等同于 --no-create-info）',
                    'eg. mysqldump -uroot -proot -t db_test > ./db_test.sql',
                    '-- 导出整个数据库的表结构和存储过程、自定义函数，不含数据（-R 等同于 --routines）',
                    'eg. mysqldump -uroot -proot -d --add-drop-table -R db_test > ./db_test_routines.sql',
                    '-- 导出指定的数据表的表结构和数据',
                    'eg. mysqldump -uroot -proot db_test t_test1 t_test2 > ./db_test.test1n2.sql',
                    '-- 将 h1 服务器中的 db_test 数据库导入到 h2 中的 db_test 数据库中，h2 的 db_test 数据库必须存在否则'
                        . '会报错（-c参数表示压缩数据）',
                    'eg. mysqldump -h127.0.0.1 -uroot -proot -c db_test | mysql -h127.0.0.1 -uroot -proot db_test',
                    '-- 导入数据',
                    'eg. use db_test;',
                    'eg. source ./db_test.sql;',
                );
                break;
        }
        if ($egsqlarr) {
            $sqlstmt .= $isComment ? ('-- ' . implode("\r-- ", $egsqlarr)) : (implode(PHP_EOL, $egsqlarr));
        }
        return $sqlstmt;
    }

    /**
     * 获取数据操作示例语句
     * @param string $action
     * @param string $curdbname
     * @return string
     * @version 1.0 2017-10-16
     */
    public static function getDbExampleSql($action, $curdbname)
    {
        $sqlstmt = '';
        switch ($action) {
            case 'dbvariables':        // 查看数据库变量信息
                // $sqlstmt = 'select VARIABLE_NAME, VARIABLE_VALUE FROM information_schema.global_variables;';
                $sqlstmt = 'show global variables;';
                break;
            case 'dbstatus':
                $sqlstmt = 'show status;';
                break;
            case 'dbcreate':        // 查看创建数据库命令
                $sqlstmt = 'show create database ' . $curdbname . ';';
                break;
            case 'dbschema':        // 查看创建数据库命令
                $sqlstmt = 'select TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, AUTO_INCREMENT, '
                    . 'CREATE_TIME, UPDATE_TIME, TABLE_COLLATION, TABLE_COMMENT from information_schema.TABLES where '
                    . "information_schema.TABLES.TABLE_SCHEMA = '$curdbname';";
                break;
        }
        return $sqlstmt;
    }

    /**
     * 获取数据表示例语句
     * @param string $action
     * @param string $curdbname
     * @param string $curtable
     * @param array $fields
     * @param array $columns
     * @return string
     * @version 1.0 2017-10-16
     * @version 1.1 2017-11-24 新增测试数据示例语句功能
     */
    public static function getTableExampleSql($action, $curdbname, $curtable, $fields, $columns = [])
    {
        $sqlstmt = '';
        switch ($action) {
            case 'egmeta':
                $sqlstmt = 'SELECT `TABLE_CATALOG`,`TABLE_SCHEMA`,`TABLE_NAME`,`TABLE_TYPE`,`ENGINE`,`VERSION`,'
                    . '`ROW_FORMAT`,`TABLE_ROWS`,`AVG_ROW_LENGTH`,`DATA_LENGTH`,`MAX_DATA_LENGTH`,`INDEX_LENGTH`,'
                    . '`DATA_FREE`,`AUTO_INCREMENT`,`CREATE_TIME`,`UPDATE_TIME`,`CHECK_TIME`,`TABLE_COLLATION`,'
                    . '`CHECKSUM`,`CREATE_OPTIONS`,`TABLE_COMMENT` FROM `information_schema`.`TABLES` where '
                    . 'TABLE_SCHEMA=\'' . $curdbname . '\'and TABLE_NAME=\'' . $curtable . '\';';
                break;
            case 'egselect':
                if ($fields) {
                    $sqlstmt = 'select `' . implode('`, `', $fields) . '` from ' . $curtable . ' order by ' . $fields[0]
                        . ' desc limit 50;';
                }
                break;
            case 'egupdate':
                if ($fields) {
                    $sqlstmt = 'eg. update ' . $curtable . ' set `' . implode('`=?, `', $fields) . '`=? where '
                        . $fields[0] . '=?;';
                }
                break;
            case 'eginsert':
                $sqlstmt = 'eg. insert into ' . $curtable . ' (`' . implode('`, `', $fields) . '`) values (:'
                    . implode(', :', str_replace('`', '', $fields)) . ');';
                break;
            case 'egdelete':
                if ($fields) {
                    $sqlstmt = 'eg. delete from ' . $curtable . ' where ' . $fields[0] . '=?;';
                }
                break;
            case 'egdesc': // desc命令
                $sqlstmt = "desc $curtable;";
                break;
            case 'egstructure': // 查看表结构
                $sqlstmt = 'select COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, '
                    . 'NUMERIC_SCALE, EXTRA, COLUMN_COMMENT from information_schema.columns WHERE TABLE_SCHEMA='
                    . "'$curdbname' AND TABLE_NAME='$curtable';";
                break;
            case 'egindex': // 查看索引
                $sqlstmt = "show index from $curtable;";
                break;
            case 'egtriggers': // 查看触发器
                $sqlstmt = "show triggers like '$curtable';";
                break;
            case 'egalter': // alter命令
                $egsqlarr = array(
                    '-- 重命名表',
                    "eg. alter table `$curtable` rename to `$curtable`",
                    '-- 新增列',
                    "eg. alter table `$curtable` add `id` int comment 'id 列的备注信息';",
                    "eg. alter table `$curtable` add `colname1` varchar(255) comment 'colname1 列的备注信息';",
                    '-- 重命名列名',
                    "eg. alter table `$curtable` change colname1 colname2 varchar(255) comment '修改了 colname1 的列名为"
                        . "colname2 并增加备注信息';",
                    '-- 修改列的数据类型',
                    "eg. alter table `$curtable` modify colname2 varchar(32) comment 'colname2 的备注信息';",
                    "eg. alter table `$curtable` change colname2 colname2 varchar(64) not null;",
                    '-- 添加主键约束',
                    "eg. alter table `$curtable` add primary key(id);",
                    '-- 删除主键约束',
                    "eg. alter table `$curtable` drop primary key;",
                    '-- 添加外键约束',
                    "eg. alter table `$curtable` add foreign key (category_id) references t_category(id);",
                    '-- 解除外键约束',
                    "eg. alter table `$curtable` drop foreign key category_id",
                    '-- 删除外键约束（alter table 从表 add constraint 外键（FK_从表_主表）foreign key （从表外键字段） '
                        . 'references 主表（主键字段））',
                    "eg. alter table `$curtable` drop fk_test_category_id",
                    '-- 添加索引',
                    "eg. alter table `$curtable` add index idx_name1_name2 (colname1,colname2);",
                    '-- 删除索引',
                    "eg. alter table `$curtable` drop index idx_name1_name2;",
                    '-- 加唯一限制条件的索引',
                    "eg. alter table `$curtable` add unique idxu_colname2 (colname2);",
                );
                $sqlstmt = implode("\r", $egsqlarr);
                unset($egsqlarr);
                break;
            case 'egcreate': // 创建表
                $sqlstmt = "show create table $curtable;";
                break;
            case 'egduplicate': // 复制表
                $sqlstmt = 'eg. insert into table2 select * from table1;' . PHP_EOL;
                $sqlstmt .= 'eg. insert into table2 (field1,field2) select field1,field2 from table1;' . PHP_EOL;
                $sqlstmt .= 'eg. create table table2(select * from table1);' . PHP_EOL;
                $sqlstmt .= '-- -- mysql 不支持 select * into table2 from table 这种复制命令';
                break;
            case 'egtruncate': // truncate命令
                $sqlstmt = "eg. truncate table $curtable;";
                break;
            case 'egcount': // count命令
                $sqlstmt = "select count(*) from $curtable;";
                break;
            case 'egdrop':
                $sqlstmt = "eg. drop table if exists $curtable;";
                break;
            case 'egtestinsert':
                // COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE,
                // EXTRA, COLUMN_COMMENT
                $sqlstmt = 'eg. testdata ' . $curtable . ",10,1\r";
                $sqlstmt .= "// 第一行格式务必为：testdata {数据表名},{生成数量},{序号起始值}\r";
                $sqlstmt .= "// 字段定义每个一行，格式为：field=value\r";
                $sqlstmt .= "// 字段值最多可包含一个函数，支持的函数如下：\r";
                $sqlstmt .= "// 序号：{i}，随机数：{rand(min,max)}，范围值：{rang(A,B,C)}，时间戳：{time()}，时间："
                    . "{date('Y-m-d H:i:s')}\r";
                foreach ($columns as $column) {
                    if ($column['IS_NULLABLE'] == 'YES') {
                        continue; // 允许null的直接跳过
                    }
                    $field = $column['COLUMN_NAME'];
                    if ($column['DATA_TYPE'] == 'varchar' || $column['DATA_TYPE'] == 'char') {
                        $comment = ' // [' . $column['DATA_TYPE'] . '(' . $column['CHARACTER_MAXIMUM_LENGTH'] . ')] '
                            . $column['COLUMN_COMMENT'];
                    } else {
                        $comment = ' // [' . $column['DATA_TYPE'] . '] ' . $column['COLUMN_COMMENT'];
                    }
                    $comment = ' // [' . $column['DATA_TYPE'] . ($column['DATA_TYPE'] == 'varchar' ? '('
                        . $column['CHARACTER_MAXIMUM_LENGTH'] . ')' : '') . '] ' . $column['COLUMN_COMMENT'];
                    switch ($column['DATA_TYPE']) {
                        case 'int':
                        case 'integer':
                        case 'tinyint':
                        case 'smallint':
                        case 'bigint':
                        case 'decimal':
                        case 'float':
                        case 'real':
                        case 'double':
                            if ($field == 'id') {
                                $value = '{i}';
                            } else {
                                if (false !== strpos($field, 'time')) {
                                    $value = '{time()}';
                                } elseif (
                                    false !== strpos($field, 'status') ||
                                    false !== strpos($field, 'state') ||
                                    false !== strpos($field, 'sex')
                                ) {
                                    $value = '{rang(0,1,2)}';
                                } else {
                                    $value = '{rand(1,100)}';
                                }
                            }
                            break;
                        case 'datetime':
                        case 'date':
                        case 'time':
                        case 'timestamp':
                            $value = "{date('Y-m-d H:i:s')}";
                            break;
                        default:
                            if (false !== strpos($field, 'name') || false !== strpos($field, 'title')) {
                                $value = "'{$field}_{i}'";
                            } else {
                                $value = "'{$field}_{rand(1,100)}'";
                            }
                            break;
                    }
                    $sqlstmt .= $field . '=' . $value . $comment . "\r";
                }
                break;
        }
        return $sqlstmt;
    }

    /**
     * 获取视图示例语句
     * @param string $action
     * @param string $curdbname
     * @param string $curview
     * @param array $fields
     * @return string
     * @version 1.0 2017-10-16
     */
    public static function getViewExampleSql($action, $curdbname, $curview, $fields)
    {
        if (!$fields) {
            return false;
        }
        $sqlstmt = '';
        switch ($action) {
            case 'egselect':
                $sqlstmt = 'select `' . implode('`, `', $fields) . '` from ' . $curview . ' order by ' . $fields[0]
                    . ' desc limit 50;';
                break;
            case 'egupdate':
                if ($fields) {
                    $sqlstmt = 'eg. update ' . $curview . ' set `' . implode('`=?, `', $fields) . '`=? where '
                        . $fields[0] . '=?;';
                }
                break;
            case 'eginsert':
                $sqlstmt = 'eg. insert into ' . $curview . ' (`' . implode('`, `', $fields) . '`) values (:'
                    . implode(', :', str_replace('`', '', $fields)) . ');';
                break;
            case 'egdelete':
                $sqlstmt = 'eg. delete from ' . $curview . ' where ' . $fields[0] . '=?;';
                break;
            case 'egstructure':
                $sqlstmt = 'select COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, '
                    . 'NUMERIC_SCALE, EXTRA, COLUMN_TYPE, COLUMN_KEY, COLUMN_COMMENT from information_schema.columns '
                    . "WHERE TABLE_SCHEMA='$curdbname' AND TABLE_NAME='$curview';";
                break;
            case 'egalter':
                $sqlstmt = "eg. create or replace view $curview as ...;";
                break;
            case 'egcreate':
                $sqlstmt = 'show create view ' . $curview . ';';
                break;
        }
        return $sqlstmt;
    }

    /**
     * 获取程序示例语句
     * @param string $action
     * @param string $curroutine
     * @param string $curroutinetype
     * @param array $routineParamNames
     * @return string
     * @version 1.0 2017-10-16
     */
    public static function getRoutineExampleSql($action, $curroutine, $curroutinetype, $routineParamNames)
    {
        $sqlstmt = '';
        switch ($action) {
            case 'egcall':
                $sqlstmt = 'eg. ' . ($curroutinetype == 'function' ? 'select ' : 'call ') . $curroutine . '('
                    . ($routineParamNames ? implode(', ', $routineParamNames) : '') . ');';
                break;
            case 'egdrop':
                $sqlstmt = 'eg. drop ' . $curroutinetype . ' if exists ' . $curroutine . ';';
                break;
            case 'egcreate':
                $sqlstmt = 'show create ' . $curroutinetype . ' ' . $curroutine . ';';
                break;
        }
        return $sqlstmt;
    }


    /**
     * 数据库类型转换为脚本类型
     * @param string $adapterType
     * @return string
     */
    public static function parseDbTypeToPhinxType($adapterType)
    {
        $types = [
            'char' => ['type' => 'string', 'is_number' => false], // char,varchar
            'bigint' => ['type' => 'biginteger', 'is_number' => true], // 注意顺序要在 int 前面
            'smallint' => ['type' => 'smallint', 'is_number' => true],
            'int' => ['type' => 'integer', 'is_number' => true], // tinyint,smallint,mediumint
            'float' => ['type' => 'float', 'is_number' => true],
            'double' => ['type' => 'decimal', 'is_number' => true],
            'decimal' => ['type' => 'decimal', 'is_number' => true],
            'year' => ['type' => 'year', 'is_number' => true],
        ];
        foreach ($types as $key => $value) {
            if (false !== stripos($adapterType, $key)) {
                return $value;
            }
        }
        // enum 或 set 类型
        if (strpos($adapterType, "'")) {
            $pos = strpos($adapterType, '(');
            return [
                'type' => substr($adapterType, 0, $pos), 'is_number' => false,
                'values' => substr($adapterType, $pos + 1, -1)
            ];
        }
        return ['type' => $adapterType, 'is_number' => false];
    }
}
