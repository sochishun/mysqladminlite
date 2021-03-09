<?php

namespace mysqladminlite\lib;

/**
 * 数据表操作类
 * 用于数据迁移功能
 * @version 2020-2-14
 */
class TableHandler
{
    /**
     * PdoAdapter实例
     * @var PdoAdapter
     */
    protected $pdo = null;
    /**
     * 数据表名称
     * @var string
     */
    protected $tablename = '';
    /**
     * 数据表属性数组
     * @var array
     */
    protected $tableAttr = [];
    /**
     * 数据表字段数组
     * @var array
     */
    protected $fields = [];
    /**
     * 构造方法
     * @param PdoAdapter $pdoAdapter
     */
    public function __construct($pdoAdapter)
    {
        $this->pdo = $pdoAdapter;
    }
    /**
     * 设置数据表名称和属性
     * @param string $name 数据表名称
     * @param array $option 数据表属性,支持三个属性：comment, charset, engine
     * @return TableHandler 返回实例自身
     */
    public function name($name, $option = null)
    {
        $this->tableName = $name;
        if ($option) {
            $this->tableAttr = $option;
        }
        return $this;
    }
    /**
     * 返回 PDO 适配器实例
     */
    public function getPdoAdapter()
    {
        return $this->pdo;
    }
    /**
     * 添加字段设置
     * @param string $name 字段名称
     * @param string $type 字段类型
     * @param array $option 字段属性
     */
    public function addColumn($name, $type, array $option = [])
    {
        $this->fields[] = ['name' => $name, 'type' => $type, 'option' => $option];
        return $this;
    }
    /**
     * 添加索引
     */
    public function addKey($name, $field)
    {
    }
    /**
     * [不可用]创建表
     * 未完成，暂不可用，等有闲余时间再完善
     */
    public function create($dropIfExists = false)
    {
        $sql = 'drop table if exists ``';
        $sql = 'create table `` (';
        if (!empty($fields = $this->fields)) {
            foreach ($fields as $col) {
                $sql .= $col['name'];
                $sql .= $col['type'];
                if (false === $col['null']) {
                }
            }
        }
        $tableattrs = ['engine' => 'engine', 'charset' => 'default charset', 'comment' => 'comment'];
        if (!empty($tableAttr = $this->tableAttr)) {
            foreach ($tableattrs as $k => $v) {
                if (!empty($tableAttr[$k])) {
                    $sql .= ' ' . $v . '=' . $tableAttr[$k];
                }
            }
            $sql .= ';';
        }
    }
    /**
     * 更新表数据
     * @param array $data 数据数组
     * @param string $where 更新条件
     * @param string $table 临时指定的数据表名
     */
    public function update($data, $where, $table = '')
    {
        if (!$table) {
            $table = $this->tableName;
        }
        $sql = 'update ' . $table . ' set ';
        foreach ($data as $k => $v) {
            $sql .= "`$k` = '$v'";
        }
        if ($where) {
            $sql .= ' where ' . $where;
        }
        return $this->pdo->execute($sql);
    }
    /**
     * 写入数据
     * @param array $data 数据数组
     * @param string $table 临时指定的数据表名
     */
    public function insert($table, $data)
    {
        if (!$data) {
            return false;
        }
        if (!$table) {
            $table = $this->tableName;
        }
        try {
            if (is_array(current($data))) {
                $sqls = [];
                foreach ($data as $row) {
                    $sql = 'insert into ' . $table . ' (`' . implode('`,`', array_keys($row)) . '`) values ';
                    $sql .= '(\'' . implode('\',\'', array_values($row)) . '\');';
                    $sqls[] = $sql;
                }
                return $this->pdo->batchQuery($sqls, true);
            } else {
                $sql = 'insert into ' . $table . ' (`' . implode('`,`', array_keys($data)) . '`) values ';
                $sql .= '(\'' . implode('\',\'', array_values($data)) . '\');';
                return $this->pdo->execute($sql);
            }
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }
    /**
     * 清空数据表
     */
    public function truncate($table)
    {
        return $this->pdo->exec('truncate table ' . $table);
    }
}
