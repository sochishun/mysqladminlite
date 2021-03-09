<?php

namespace mysqladminlite\lib;

class SeedHandler
{
    public function execFile($classname, $pdoAdapter)
    {
        $class = new $classname();
        $result = $class->run(new TableHandler($pdoAdapter));
        if (!$result || !isset($result['status'])) {
            return '测试数据写入成功！';
        }
        if (!$result['status']) {
            foreach ($result['data'] as $message) {
                return $message;
            }
        } else {
            return '测试数据写入成功！（受影响行数：' . $result['affected_rows'] . '）';
        }
    }

    public function getTemplateContent($framework, $classname, $table, $comment, $columns, $database, $count)
    {
        $filename = implode(DIRECTORY_SEPARATOR, [dirname(dirname(__DIR__)), 'assets', "seed_{$framework}.php.example"]);
        if (!file_exists($filename)) {
            return '';
        }
        $tabx2 = "\t\t";
        $tabx3 = "\t\t\t";
        $datatype = [];
        $colname = '';
        $enumVars = PHP_EOL;
        $data = '$data[] = [' . PHP_EOL;
        foreach ($columns as $col) {
            $colname = $col['COLUMN_NAME'];
            // $defaultVal = $col['COLUMN_DEFAULT'];
            if ('id' == $colname) {
                continue;
            }
            $datatype = SqlHandler::parseDbTypeToPhinxType($col['COLUMN_TYPE']);
            $data .= "\t\t\t\t'$colname' => ";
            switch ($datatype['type']) {
                case 'datetime':
                    $data .= 'date(\'Y-m-d H:i:s\')';
                    break;
                case 'date':
                    $data .= 'date(\'Y-m-d\')';
                    break;
                case 'time':
                    $data .= 'date(\'H:i:s\')';
                    break;
                case 'string':
                case 'text':
                    // echo is_null($defaultval) ? "'test ' . \$i" : "'$defaultval'";
                    if ('password' == $colname) {
                        $data .= 'md5(\'Aa123456\')';
                    } elseif (false !== strpos($colname, 'name')) {
                        $data .= "'{$colname}-' . \$i";
                    } else {
                        $data .= "'test-' . \$i";
                    }
                    break;
                case 'enum':
                case 'set':
                    $enumVars .= $tabx2 . '$' . $colname . 'Data = [' . $datatype['values'] . '];' . PHP_EOL;
                    $data .= '$' . $colname . 'Data[array_rand($' . $colname . 'Data)]';
                    break;
                case 'year': // year(4)
                    $data .= 'date(\'Y\')';
                    break;
                default:
                    // echo is_null($defaultval) ? '0' : $defaultval;
                    switch ($colname) {
                        case 'created_at':
                        case 'create_time':
                        case 'createtime':
                        case 'updated_at':
                        case 'update_time':
                        case 'updatetime':
                            $data .= 'time()';
                            break;
                        default:
                            $data .= strpos($colname, 'time') ? 'time()' : 'rand(1,100)';
                            break;
                    }
                    break;
            }
            $data .= ',' . PHP_EOL;
        }
        $data .= $tabx3 . '];';
        $content = file_get_contents($filename);
        $replaces = [
            '{comment}' => '// ' . ($comment ? $comment : $table),
            '{class}' => $classname,
            '{enumVars}' => $enumVars,
            '{table}' => $table,
            '{data}' => $data,
            '{count}' => $count,
            '{database}' => $database,
        ];
        return str_replace(array_keys($replaces), array_values($replaces), $content);
    }
}
