<?php

namespace mysqladminlite\lib;

class MigrationHandler
{
    public function getTemplateContent($framework, $classname, $table, $tableInfo, $columns)
    {

        // 排版变量
        $tab = "\n";
        $tabx2 = "\t\t";
        $content = '<?php' . PHP_EOL . PHP_EOL;
        // 每种框架头部都不一样
        switch ($framework) {
            case 'mysal':
                $content .= 'class ' . $classname . ' {' . PHP_EOL . PHP_EOL;
                $content .= $tab . 'protected $table=\'' . $table . '\';' . PHP_EOL . PHP_EOL;
                $content .= $tab . 'public function change($table) {' . PHP_EOL . PHP_EOL;
                break;
            default:
                $content .= 'use Phinx\Migration\AbstractMigration;' . PHP_EOL . PHP_EOL;
                $content .= 'class ' . $classname . ' extends AbstractMigration {' . PHP_EOL . PHP_EOL;
                $content .= $tab . 'protected $table=\'' . $table . '\';' . PHP_EOL . PHP_EOL;
                $content .= $tab . 'public function change() {' . PHP_EOL . PHP_EOL;
                break;
        }

        $content .= $tabx2 . '$table=$this->table($this->tableName, [\'comment\'=>\''
            . SqlHandler::parseDbComment($tableInfo['TABLE_COMMENT']) . '\']);' . PHP_EOL;
        $isfirst = true;
        $datatype = '';
        $defaultval = '';
        $colname = '';
        $hascreatedat = false;
        foreach ($columns as $col) {
            $colname = $col['COLUMN_NAME'];
            // phinx 默认创建 id 主键列，所以无需在代码中定义 id 主键列
            if ('id' == $colname) {
                continue;
            }
            // 时间戳字段
            if ('created_at' == $colname) {
                $hascreatedat = true;
                $content .= "\t\t\t->addTimestamps()" . PHP_EOL;
                continue;
            }
            if ($hascreatedat && 'updated_at' == $colname) {
                $hascreatedat = false;
                continue;
            }
            // fastadmin 等框架的时间戳字段
            if ('createtime' == $colname) {
                $hascreatedat = true;
                $content .= "\t\t\t->addTimestamps('createtime','updatetime')" . PHP_EOL;
                continue;
            }
            if ($hascreatedat && 'updatetime' == $colname) {
                $hascreatedat = false;
                continue;
            }
            if ($isfirst) {
                $isfirst = false;
                $content .= $tabx2 . '$table->addColumn(\'';
            } else {
                $content .= "\t\t\t->addColumn('";
            }
            $datatype = SqlHandler::parseDbTypeToPhinxType($col['COLUMN_TYPE']);
            $defaultval = $col['COLUMN_DEFAULT'];
            $content .= $col['COLUMN_NAME'] . '\', \'' . $datatype['type'] . '\', [\'comment\'=>\''
                . SqlHandler::parseDbComment($col['COLUMN_COMMENT']) . '\'';
            if (isset($datatype['values'])) {
                $content .= ', \'values\'=>[' . $datatype['values'] . ']';
            }
            if ('YES' == $col['IS_NULLABLE']) {
                $content .= ', \'null\'=>true';
            }
            switch ($datatype['type']) {
                case 'string':
                    $content .= ', \'limit\'=>' . $col['CHARACTER_MAXIMUM_LENGTH'];
                    break;
                case 'float':
                case 'decimal':
                    $content .= ', \'precision\'=>' . $col['NUMERIC_PRECISION'];
                    $content .= ', \'scale\'=>' . $col['NUMERIC_SCALE'];
                    break;
                default:
                    break;
            }
            if (!is_null($defaultval)) {
                $content .= ', \'default\'=>' . ($datatype['is_number'] ? $defaultval : "'$defaultval'");
            }
            $content .= '])' . PHP_EOL;
        }
        foreach ($columns as $col) {
            if ($col['COLUMN_KEY'] && 'id' != $col['COLUMN_NAME']) {
                $content .= "\t\t\t" . '->addIndex(\'' . $col['COLUMN_NAME'] . '\' . [\'name\'=>\'idx_'
                    . $col['COLUMN_NAME'] . '\'])' . PHP_EOL;
            }
        }
        $content .= "\t\t\t->create();" . PHP_EOL;
        $content .= "\t}" . PHP_EOL . '}' . PHP_EOL;
    }
}
