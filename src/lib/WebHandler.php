<?php

namespace mysqladminlite\lib;

/**
 * Web Trait
 * 说明：为了支持合并代码到 SPA 文件，由 Trait 类型改为 Class 类型
 */
class WebHandler
{
    /**
     * 显示侧边栏数据库操作按钮
     */
    public function showAsideDbOptionButtons($token, $env, $db)
    {
        $url = "?env={$env}&db={$db}&token={$token}&action=";
        echo '<div class="btn-group-vertical btn-group-sm w-100">';
        echo '<a class="btn btn-light text-left" title="查看 ', $db, ' 数据库变量信息（show global variables）" ';
        echo 'href="', $url, 'dbvariables">查看数据库变量信息</a>';
        echo '<a class="btn btn-light text-left" title="查看 ', $db, ' 数据库变量信息（show status）" ';
        echo 'href="', $url, 'dbstatus">查看数据库状态信息</a>';
        echo '<a class="btn btn-light text-left" title="查看 ', $db, ' 数据库架构信息（information_schema.TABLES）" ';
        echo 'href="', $url, 'dbschema">查看数据库架构信息</a>';
        echo '<a class="btn btn-light text-left" title="查看 ', $db, ' 创建数据库语句" href="', $url, 'dbcreate">';
        echo '查看数据库创建语句</a>';
        echo '<a class="btn btn-light text-left" title="查看 ', $db, ' 所有数据表的模型" href="', $url, 'export-db-model" ';
        echo 'target="_blank">查看数据表模型</a>';
        echo '<a class="btn btn-light text-left" title="导出 ', $db, ' 所有数据表结构到 Markdown 文件，以便于发布到';
        echo '在线文档系统" href="', $url, 'export-db-markdown">导出表结构到 Markdown 文件</a>';
        echo '<a class="btn btn-light text-left" title="导出 ', $db, ' 所有数据表结构到 Excel 文件，以便于快速模型设计';
        echo '或文件归档" href="', $url, 'export-db-excel">导出表结构到 Excel 文件</a>';
        echo '<a class="btn btn-light text-left" title="导出 ', $db, ' 所有数据表结构到 HTML 文件，以便于快速浏览模型';
        echo '大纲" href="', $url, 'export-db-html">导出数据表结构到 Html 文件</a>';
        echo '<a class="btn btn-light text-left" title="导出 ', $db, ' 所有数据表结构到 SQL 文件，以便于迁移数据库" ';
        echo 'href="', $url, 'export-db-sql">导出数据表结构到 SQL 文件</a>';
        echo '</div>';
    }

    /**
     * 显示数据表的操作示例按钮
     * @version 1.8 2017-10-16
     * @version 1.9 2017-11-23 新增data-sql功能
     */
    public function showTableOptionButtons($token, $env, $db, $table)
    {
        $url = "?env={$env}&db={$db}&object={$table}&objtype=table&token={$token}&action=";

        echo '<div class="btngroup">';
        echo '<a class="btn btn-sm btn-info" href="', $url, '" title="在新窗口打开数据表" target="_blank">新窗口打开</a>';
        echo '<a class="btn btn-sm btn-info" title="查看表结构模型" href="', $url,
        'export-table-html" target="_blank">查看模型</a>';
        echo '<a class="btn btn-sm btn-info" title="导出表结构模型到 Excel 文件" href="', $url,
        'export-table-excel" target="_blank">导出模型到 Excel</a>';
        echo '<a class="btn btn-sm btn-info" title="导出创建表命令到 SQL 文件" href="', $url,
        'export-table-sql" target="_blank">导出创建表命令</a>';
        echo '<a class="btn btn-sm btn-info" title="导出与表结构相匹配的 Phinx Migrate 迁移文件" href="', $url,
        'export-table-migrate">导出 Phinx Migrate</a>';
        echo '<a class="btn btn-sm btn-info" title="导出与表结构相匹配的 Phinx Seed 测试数据文件" href="', $url,
        'export-table-seed">导出 Phinx Seed</a>';
        echo '</div><div class="btngroup" style="margin:5px 0">';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egselect" title="查询记录语句示例">SELECT</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egcount" title="查询表记录数量语句示例">COUNT</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egupdate" title="更新记录语句示例">UPDATE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'eginsert" title="插入记录语句示例">INSERT</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egdelete" title="删除记录语句示例">DELETE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egalter" title="更改表结构语句示例">ALTER</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egcreate" title="创建表结构语句示例">CREATE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egduplicate" title="复制表语句示例">SELECT INTO</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egtruncate" title="清空表记录语句示例">TRUNCATE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egdrop" title="丢弃表结构语句示例">DROP</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egdesc" title="查看表结构语句示例">DESC</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egstructure" title="查看表结构语句示例">STRUCTURE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egmeta" title="查询数据表元数据语句示例">META</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egindex" title="查看表索引语句示例">INDEX</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egtriggers" title="查看表触发器语句示例">TRIGGERS</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egtestinsert" title="批量生成测试数据">TEST DATA</a>';
        echo '</div>';
    }

    /**
     * 显示视图的操作示例按钮
     * @param array $fields
     * @version 1.8 2017-10-16
     * @version 1.9 2017-11-23 新增data-sql功能
     */
    public function showViewOptionButtons($token, $env, $db, $view, $fields)
    {
        $url = "?env={$env}&db={$db}&object={$view}&objtype=view&token={$token}&action=";
        echo '<span class="btngroup">';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egselect" title="查询记录语句示例">SELECT</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egupdate" title="更新记录语句示例" data-sql="',
        SqlHandler::getViewExampleSql('egupdate', $db, $view, $fields),
        '" class="btn_example_sql">UPDATE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'eginsert" title="插入记录语句示例" data-sql="',
        SqlHandler::getViewExampleSql('eginsert', $db, $view, $fields),
        '" class="btn_example_sql">INSERT</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egdelete" title="删除记录语句示例" data-sql="',
        SqlHandler::getViewExampleSql('egdelete', $db, $view, $fields),
        '" class="btn_example_sql">DELETE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egstructure" title="查看视图结构语句示例">STRUCTURE</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egalter" title="更改视图结构语句示例" data-sql="',
        SqlHandler::getViewExampleSql('egalter', $db, $view, $fields),
        '" class="btn_example_sql">ALTER</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egcreate" title="创建视图结构语句示例">CREATE</a>';
        echo '</span>';
    }

    /**
     * 显示程序的操作示例按钮
     * @param array $routineParamNames
     * @version 1.8 2017-10-16
     */
    public function showRoutineOptionButtons($token, $env, $db, $object, $objectType, $routineParamNames)
    {
        $url = "?env={$env}&db={$db}&object={$object}&objtype=$objectType&token={$token}&action=";
        echo '<span class="btngroup">';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, '&action=egcall" title="执行程序语句示例" data-sql="',
        SqlHandler::getRoutineExampleSql('egcall', $object, $objectType, $routineParamNames),
        '" class="btn_example_sql">CALL</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, '&action=egdrop" title="删除程序语句示例" data-sql="',
        SqlHandler::getRoutineExampleSql('egdrop', $object, $objectType, $routineParamNames),
        '" class="btn_example_sql">DROP</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, '&action=egcreate" title="创建程序结构语句示例">CREATE</a>';
        echo '</span>';
    }

    /**
     * 显示默认的操作示例按钮
     * @param string $token
     * @version 1.8 2017-10-16
     */
    public function showDefaultOptionButtons($token, $env, $db)
    {
        $url = "?env={$env}&db={$db}&token={$token}&action=";
        echo '<span class="btngroup">';
        echo '<a class="btn btn-sm btn-secondary" href="', $url,
        'egcreate_database" title="创建数据库语句示例">创建数据库</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url,
        'egcreate_table" title="创建数据表语句示例">创建数据表</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egcreate_view" title="创建视图语句示例">创建视图</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url,
        'egcreate_function" title="创建函数语句示例">创建函数</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url,
        'egcreate_procedure" title="创建存储过程语句示例">创建存储过程</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url,
        'egcreate_trigger" title="创建触发器语句示例">创建触发器</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url, 'egcreate_user" title="创建用户语句示例">创建用户</a>';
        echo '<a class="btn btn-sm btn-secondary" href="', $url,
        'egexport_import" title="数据库导入导出示例">导入导出</a>';
        echo '</span>';
    }

    /**
     * 获取sql编辑器的默认提示
     * @return string
     * @version 1.9 2017-10-16 Added.
     */
    public function getSqlEditorPlaceholder()
    {
        $content_arr = array(
            '/*',
            '* SQL 编辑器',
            '* 支持功能：',
            '* &gt; 支持智能忽略单行注释、多行注释（点击编辑器 N 次自动去除注释）',
            '* &gt; 支持多语句批量执行，每条语句务必以分号结尾并以换行符号隔开',
            '* &gt; 支持存储过程等复杂语句，支持 {#object} 变量',
            '*/',
            '',
            '/* 以下为示例语句 */',
            '-- 多语句批量查询示例（每条语句务必以分号结尾并以换行符号隔开）',
            'insert into table1 values (1,2,3);',
            'select * from table1;',
        );
        return implode("\r", $content_arr);
    }

    /**
     * 显示数据表列表
     * @param array $list
     * @param string $tableprefix
     * @param boolean $isul
     * @param integer $curLen
     * @version 2017-10-20
     */
    public function showAsideTableList($token, $env, $db, $table, $config, $list, $tableprefix, $isul = true, $curLen = 24)
    {
        if (!$list) {
            return;
        }
        $i = 1;
        $url = '';
        if ($isul) {
            echo '<ul class="list-unstyled">';
        } else {
            echo '<select class="form-control form-control-sm" id="slt-table" title="数据表跳转">';
            // 收藏夹
            if ($db && isset($config['favorite'][$db])) {
                $favorites = $config['favorite'][$db];
                if ($favorites) {
                    echo '<option value="">==收藏夹==</option>';
                    foreach ($favorites as $objname) {
                        if ($tableprefix && (0 === strpos($objname, $tableprefix))) {
                            $title = substr($objname, strlen($tableprefix));
                        } else {
                            $title = $objname;
                        }
                        $url = "?env={$env}&db={$db}&table={$objname}&action=egselect&token={$token}";
                        echo "<option value=\"{$objname}\" title=\"$objname\" data-url=\"{$url}\">",
                        CommonHelper::shortText($title, $curLen),
                        '</option>';
                    }
                }
            }
            echo '<option value="">==选择数据表==</option>';
        }
        $title = '';
        foreach ($list as $row) {
            $objname = $row['TABLE_NAME'];
            if ($tableprefix && (0 === strpos($objname, $tableprefix))) {
                $title =   substr($objname, strlen($tableprefix));
            } else {
                $title =  $objname;
            }

            $url = "?env={$env}&db={$db}&object={$objname}&objtype=table&action=egselect&token={$token}";
            if ($isul) {
                echo '<li', ($table == $objname ? ' class="active"' : ''), '>';
                echo "<a href=\"{$url}\" title=\"{$objname}\">{$i}. " . CommonHelper::shortText($title, $curLen) . "</a></li>";
            } else {
                echo "<option value=\"{$objname}\" title=\"{$objname}\" data-url=\"{$url}\"";
                echo ($objname == $table ? ' selected="selected"' : ''), '>', CommonHelper::shortText($title, $curLen),
                '</option>';
            }
            $i++;
        }
        echo $isul ? '</ul>' : '</select>';
    }

    /**
     * 显示视图列表
     * @param array $list
     * @param boolean $isul
     * @param integer $curLen
     */
    public function showAsideViewList($token, $env, $db, $view, $list, $isul = true, $curLen = 16)
    {
        if (!$list) {
            return;
        }
        $i = 1;
        $url = '';
        if ($isul) {
            echo  '<ul class="list-unstyled">';
        } else {
            echo '<select class="form-control form-control-sm" id="slt-view" title="视图跳转">',
            '<option value="">==选择视图==</option>';
        }
        foreach ($list as $row) {
            $objname = $row['TABLE_NAME'];
            $url = "?env={$env}&db={$db}&object={$objname}&objtype=view&action=egselect&token={$token}";
            if ($isul) {
                echo '<li', ($view == $objname ? ' class="active"' : ''), '><a href="', $url, '" title="', $objname;
                echo '">', $i, '. ', $objname, '</a></li>';
            } else {
                echo "<option value=\"{$objname}\" title=\"{$objname}\" data-url=\"{$url}\"";
                echo ($objname == $view ? ' selected="selected"' : ''), '>', CommonHelper::shortText($objname, $curLen),
                '</option>';
            }
            $i++;
        }
        echo $isul ? '</ul>' : '</select>';
    }

    /**
     * 显示函数列表
     * @param string $objType
     * @param array $list
     * @param boolean $isul
     * @param integer $curLen
     */
    public function showAsideRoutineList($token, $env, $db, $object, $objType, $list, $isul = true, $curLen = 16)
    {
        if (!$list) {
            return;
        }
        $i = 1;
        $url = '';
        $objTypeName = ($objType == 'procedure' ? '存储过程' : '函数');
        if ($isul) {
            echo '<ul class="list-unstyled">';
        } else {
            echo "<select class=\"form-control from-control-sm\" id=\"slt-{$objType}\" title=\"{$objTypeName}跳转\">";
            echo "<option value=\"\">==选择{$objTypeName}==</option>";
        }
        foreach ($list as $row) {
            $objname = $row['ROUTINE_NAME'];
            $routineType = strtolower($row['ROUTINE_TYPE']);
            $url = "?env={$env}&db={$db}&object={$objname}&objtype={$routineType}&action=egcall&token={$token}";
            if ($isul) {
                echo '<li', ($object == $objname ? ' class="active"' : ''), '><a href="', $url, '" title="';
                echo $objname, '">', $i, '. ', $objname, '</a></li>';
            } else {
                echo "<option value=\"{$objname}\" title=\"{$objname}\" data-url=\"{$url}\"";
                echo ($objname == $object ? ' selected="selected"' : ''), '>', CommonHelper::shortText($objname, $curLen),
                '</option>';
            }
            $i++;
        }
        echo $isul ? '</ul>' : '</select>';
    }
}
