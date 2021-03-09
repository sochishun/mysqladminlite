<?php

namespace mysqladminlite\lib;

/**
 * 公共函数助手类
 */
class CommonHelper
{
    /**
     * 截短字符串
     * @param string $str 字符串
     * @param integer $len 长度
     * @return string
     */
    public static function shortText($text, $length, $lastLen = 8, $ellipsis  = '...')
    {
        if (empty($text) || mb_strlen($text) <= $length) {
            return $text;
        }
        if ($lastLen > 0) {
            $text = mb_substr($text, 0, $length - $lastLen) . $ellipsis . mb_substr($text, -$lastLen);
        } else {
            $text = mb_substr($text, 0, $length) . $ellipsis;
        }
        return  $text;
    }
    /**
     * HTML 内容转换为纯文本
     * 例如：html2text($content,['/[\*|？|！|。|，]+/']);
     * @param string
     * @return
     */
    public static function html2text($content, $expattern = [])
    {
        // 将 html 实体符号（&nbsp; 等）还原成 html 标签 -> 过滤首尾空白字符 -> 过滤 html 标签
        $content = strip_tags(trim(html_entity_decode($content)));
        $pattern = [
            '/\s/', // 匹配任何空白字符，包括空格、制表符、换页符等，等价于 [\f\n\r\t\v]
        ];
        if ($expattern) {
            $pattern = array_merge($pattern, $expattern);
        }
        return preg_replace($pattern, '', $content);
    }


    /**
     * 类名转换为数据表名
     * @param string $name 类名称
     */
    public static function classname2table($name)
    {
        if (!$name) {
            return $name;
        }
        $chars = str_split($name);
        array_walk($chars, function (&$v, $i) {
            $ord = ord($v);
            if ($ord <= 90 && $ord >= 65) {
                $v = strtolower($v);
                if ($i > 0) {
                    $v = '_' . $v;
                }
            }
        });
        return implode('', $chars);
    }

    /**
     * 数据表名称转换为类名
     * @param string $table 数据表名称
     */
    public static function table2classname($table)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
    }

    /**
     * 返回二维数组中匹配的所有行
     * @param array $list
     * @param array $compareData
     * @param integer $limit
     * @return array
     */
    public static function arraySearchRows($list, $compareData, $limit = 1)
    {
        $rows = [];
        if (!$list || !is_array($list)) {
            return $rows;
        }
        $n = count($compareData);
        $i = 0;
        foreach ($list as $k => $v) {
            foreach ($compareData as $field => $val) {
                if (isset($v[$field]) && $v[$field] == $val) {
                    $i++;
                }
            }
            if ($i == $n) {
                $rows[$k] = $v;
                if ($limit > 0 && $i >= $limit) {
                    break;
                }
            }
        }
        return $rows;
    }
    /**
     * 返回二维数组中指定列的值
     * @param array $list 二维数组
     * @param string $name  列名称
     * @param array 指定列的值的数组
     */
    public static function arrayColumn($list, $name)
    {
        $col = [];
        if ($list) {
            foreach ($list as $row) {
                if (array_key_exists($name, $row)) {
                    $col[] = $row[$name];
                }
            }
        }
        return $col;
    }

    /**
     * ver_export() 方法的现代风格版
     */
    function varExport($var, $indent = "")
    {
        switch (gettype($var)) {
            case "string":
                return '\'' . addcslashes($var, "\\\$\"\r\n\t\v\f") . '\'';
            case "array":
                $indexed = array_keys($var) === range(0, count($var) - 1);
                $r = [];
                foreach ($var as $key => $value) {
                    $r[] = "$indent    " . ($indexed ? "" : $this->varExport($key) . " => ") . $this->varExport($value, "$indent    ");
                }
                return "[\n" . implode(",\n", $r) . "\n" . $indent . "]";
            case "boolean":
                return $var ? "TRUE" : "FALSE";
            default:
                return var_export($var, true);
        }
    }
}
