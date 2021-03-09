<?php

namespace mysqladminlite\lib;

class XfnHandler
{
    public function execFile($filename, $pdoAdapter, $params, $fn = 'run')
    {
        if (!file_exists($filename)) {
            exit('文件不存在！[' . $filename . ']');
        }
        if (!$fn) {
            $fn = 'run';
        }
        require $filename;
        call_user_func($fn, $pdoAdapter, $params);
        echo '命令调用成功！路径：', $filename, '::', $fn, '()';
    }

    public function getTemplateContent($file)
    {
        $filename = implode(DIRECTORY_SEPARATOR, [dirname(dirname(__DIR__)), 'assets', 'xfn.php.example']);
        if (file_exists($filename)) {
            return str_replace('{file}', $file, file_get_contents($filename));
        }
        return '';
    }
}
