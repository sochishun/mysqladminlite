<?php

namespace mysqladminlite\lib;

/**
 * 控制台 Trait
 * 说明：为了支持合并代码到 SPA 文件，由 Trait 类型改为 Class 类型
 */
class ConsoleHandler
{

    /**
     * 命令行参数
     * @var array
     */
    protected $_params = [];

    public function getParams()
    {
        return $this->_params;
    }
    public function setParams($value)
    {
        $this->_params = $value;
    }

    public function init($argc, $argv)
    {
        $this->_params = $this->parseParams($argc, $argv);
    }

    /**
     * 添加变量
     * @param string $name
     * @param string $value
     */
    public function inputAdd($name, $value)
    {
        $this->_params[$name] = $value;
    }
    /**
     * 返回命令行参数列表
     * @param array $excludeFields 排除的字段
     * @param array $allowFields 允许的字段
     * @return array
     */
    public function inputAll(array $excludeFields = [], array $allowFields = [])
    {
        $params = $this->_params;
        if ($allowFields) {
            $data = [];
            foreach ($allowFields as $key) {
                if (isset($params[$key])) {
                    $data[$key] = $params[$key];
                }
            }
            return $data;
        }
        if ($excludeFields) {
            foreach ($excludeFields as $key) {
                if (isset($params[$key])) {
                    unset($params[$key]);
                }
            }
        }
        return $params;
    }
    /**
     * 尝试重新获取参数或者中断执行
     */
    public function inputRetry($key, $message)
    {
        $value = $this->input($key);
        if (!$value) {
            $value = $this->stdin($message);
        }
        if (!$value) {
            exit(PHP_EOL . '操作已取消！' . PHP_EOL);
        }
        return $value;
    }
    /**
     * 确认操作
     */
    public function inputConfirm($value, $message)
    {
        return $value == $this->stdin($message);
    }
    /**
     * 返回参数值或者中断执行
     */
    public function inputRequired($key, $message = '必须输入 %s 参数')
    {
        $value = $this->input($key);
        if (!$value) {
            exit(sprintf($message, $key));
        }
        return $value;
    }
    /**
     * 返回参数值是否与比对值相等
     */
    public function inputEqual($key, $compareValue = 'y')
    {
        return $compareValue == $this->input($key);
    }

    /**
     * 返回数组中指定键名的值
     * @param string|array $keys
     * @param array $params
     * @param mixed $defv
     * @return mixed
     */
    public function input($keys, $defv = '')
    {
        if (!($params = $this->_params)) {
            return $defv;
        }
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (isset($params[$key])) {
                    return trim($params[$key]);
                }
            }
            return $defv;
        } else {
            return isset($params[$keys]) ? trim($params[$keys]) : $defv;
        }
    }
    /**
     * 是否强制执行
     */
    public function checkIsForce()
    {
        return 'y' == $this->input('--force');
    }
    /**
     * 判断参数是否存在
     */
    public function hasParam($name)
    {
        if (!($params = $this->_params)) {
            return false;
        }
        return array_key_exists($name, $params);
    }
    /**
     * 返回命令行参数
     */
    public function parseParams($argc, $argv)
    {
        if ($argc < 3) {
            return false;
        }
        $pkeys = [];
        $pvals = [];
        foreach ($argv as $key => $value) {
            if ($key < 2) {
                continue;
            }
            if ($key % 2 < 1) {
                $pkeys[] = $value;
            } else {
                $pvals[] = $value;
            }
        }
        // 最后一个参数值忘记填写则默认为空值
        if (count($pkeys) != count($pvals)) {
            $pvals[] = '';
        }
        return array_combine($pkeys, $pvals);
    }
    /**
     * 交互式获取参数
     * @param string $msg 提示消息
     * @return mixed 用户输入的内容
     */
    public function stdin($msg)
    {
        fwrite(STDOUT, $msg);
        return trim(fgets(STDIN));
    }
}
