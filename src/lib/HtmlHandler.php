<?php

namespace mysqladminlite\lib;

/**
 * Html页面助手类
 */
class HtmlHandler
{
    /**
     * 设置用户导出下载的浏览器头部信息
     * @param boolean $isexcel 是否 Excel 格式
     * @param string $filename 文件名
     */
    public static function setExportHeader($isexcel, $filename)
    {
        if ($isexcel) {
            header("Content-Type: application/vnd.ms-excel; name='excel'");
        }
        header("Content-type:application/octet-stream");
        header('Content-Disposition:attachment; filename=' . $filename);
    }
    /**
     * 获取验证码
     * @return string
     */
    public static function getCaptcha()
    {
        $type = rand(1, 3);
        $tip = '<span style="color:#F00; margin-left:10px;"><strong id="error"></strong>（注意：生产环境才会出现验证码，'
            . '请务必谨慎操作！做好数据备份！）</span>';
        switch ($type) {
            case 1:
                $a = rand(1, 150);
                $b = rand(150, 100);
                $val = $b - $a;
                return '<input type="text" name="captcha" placeholder="' . $val . '" title="' . $val
                    . '" required="required" style="width:3em" />+' . $a . '=' . $b . $tip;
            case 2:
                $a = rand(1, 100);
                $b = rand(100, 1000);
                $val = $b - $a;
                return $a . '+<input type="text" name="captcha" placeholder="' . $val . '" title="' . $val
                    . '" required="required" style="width:3em" />=' . $b . $tip;
            case 3:
                $a = rand(1, 150);
                $b = rand(1, 150);
                $val = $a + $b;
                return $a . '+' . $b . '=<input type="text" name="captcha" placeholder="' . $val . '" title="' . $val
                    . '" required="required" style="width:3em" />' . $tip;
        }
    }

    /**
     * 显示本地时间
     * @param string $str 消息模板
     */
    public static function showLocaltime($str)
    {
        $yeardays = date('L') ? 366 : 365;
        $yearweeks = ceil($yeardays / 7);
        $motto = [
            '生命的价值在于自己看得起自已，人生的意义在于努力进取。',
            '光阴易逝，年华不再，时间就是生命，不要留下遗憾！',
            '不走心的努力，都是在敷衍自己！',
            '人生路上，我们都是孤独的行者，真正能帮你的，永远只有你自己。',
            '努力是一种生活态度，与年龄无关。',
        ];
        $replaces = [
            '{{yearweeks}}' => ceil($yeardays / 7), // 一年总周数
            '{{yearweek}}' => intval(date('W')), // 一年中第几周
            '{{yeardays}}' => $yeardays, // 一年总天数
            '{{yearday}}' => date('z'), // 一年中第几天
            '{{remaindays}}' => $yeardays - date('z'), // 一年还剩多少天
            '{{remainweeks}}' => $yearweeks - date('W'), // 一年还剩多少周
            '{{weekday}}' => mb_substr('日一二三四五六', date('w'), 1), // 星期几
            '{{motto}}' => $motto[array_rand($motto)], // 格言
        ];
        echo str_replace(array_keys($replaces), array_values($replaces), $str);
    }
    /**
     * 展示数据库信息表格
     * @param array $dbvariables
     * @param array &$optimizes
     */
    public static function showDbVariablesTable($dbvariables, &$optimizes)
    {
        echo '<table class="table table-striped table-bordered table-hover table-sm"><tr>';
        $i = 0;
        $tmpvarvalue = '';
        $tmpvarname = '';
        foreach ($dbvariables as $row) {
            if ($i > 0 && ($i % 3 == 0)) {
                echo '</tr><tr>';
            }
            $tmpvarname = $row['Variable_name'];
            $tmpvarvalue = $row['Value'];
            echo '<th>', $tmpvarname, '</th>';
            echo '<td class="text-wrap text-break" style="max-width:15rem">';
            if (strpos($tmpvarname, '_size') || strpos($tmpvarname, '_packet')) {
                echo '<span title="' . $tmpvarvalue . '">' . FileHandler::formatBytes($tmpvarvalue) . '</span>';
            } else {
                echo $tmpvarvalue;
            }
            echo '</td>';
            switch ($tmpvarname) {
                case 'max_connections':
                    if (intval($tmpvarvalue) < 2000) {
                        $optimizes[] = [
                            'name' => $tmpvarname, 'value' => $tmpvarvalue, 'recommend' => 2000,
                            'remark' => '最大连接数量'
                        ];
                    }
                    break;
                case 'sort_buffer_size':
                    if ($tmpvarvalue < 8388608) {
                        $optimizes[] = [
                            'name' => $tmpvarname,
                            'value' => FileHandler::formatBytes($tmpvarvalue),
                            'recommend' => '8 MB',
                            'remark' => '排序缓冲，增加 sort_buffer_size 值可以加速 ORDER BY 或 GROUP BY 操作，和连接数成'
                                . '正比，比如 1000 连接数就是 8MB*1000=8GB 的内存使用量'
                        ];
                    }
                    break;
                case 'key_buffer_size':
                    if ($tmpvarvalue < 536870912) {
                        $optimizes[] = [
                            'name' => $tmpvarname, 'value' => FileHandler::formatBytes($tmpvarvalue),
                            'recommend' => '512 MB',
                            'remark' => '索引的缓冲区大小，对于内存在 4GB 左右的服务器来说，该参数可设置为 256 MB'
                        ];
                    }
                    break;
                case 'innodb_buffer_pool_size':
                    if ($tmpvarvalue < 536870912) {
                        $optimizes[] = [
                            'name' => $tmpvarname, 'value' => FileHandler::formatBytes($tmpvarvalue),
                            'recommend' => '512 MB',
                            'remark' => '只需要用 Innodb 的话则可以设置它高达 70-80% 的可用内存。如果你的数据量不大，并且不会'
                                . '暴增，那么无需设置的太大了'
                        ];
                    }
                    break;
                case 'innodb_thread_concurrency':
                    if ($tmpvarvalue < 32) {
                        $optimizes[] = [
                            'name' => $tmpvarname, 'value' => $tmpvarvalue, 'recommend' => '32',
                            'remark' => '在并发量大的实例上，增加这个值，可以降低 InnoDB 在并发线程之间切换的花销，以增加'
                                . '系统的并发吞吐量'
                        ];
                    }
                    break;
                case 'innodb_log_files_in_group':
                    if ($tmpvarvalue < 3) {
                        $optimizes[] = [
                            'name' => $tmpvarname, 'value' => $tmpvarvalue, 'recommend' => '3',
                            'remark' => '循环方式将日志文件写到多个文件。推荐设置为 3'
                        ];
                    }
                    break;
            }
            $i++;
        }
        echo '</tr></table>';
    }


    /**
     * 显示属性表格
     * @param array $data
     * @param boolean $isCli
     */
    public static function showPropertyTable($data, $isCli = false)
    {
        if (!$data) {
            echo $isCli ? '没有查询到任何记录' : '<p>没有查询到任何记录!</p>';
            return;
        }
        $i = 0;
        if (!$isCli) {
            echo '<table class="table table-sm table-striped table-bordered table-hover table-responsive" ',
            'style="border:none">';
        }
        foreach ($data as $k => $v) {
            $i++;
            if ($isCli) {
                echo '# ', $i, ' ------------------------------------------------', PHP_EOL;
                echo $k, ' = ', $v, PHP_EOL;
            } else {
                echo '<tr><td>', $i, '</td><td>', $k, '</td>';
                echo '<td>', (is_null($v) ? '<span style="color:#AAA">NULL</span>' : htmlspecialchars($v)), '</td>';
                echo '</tr>';
            }
        }
        if (!$isCli) {
            echo '</table>';
        }
    }
    public static function showTableCli($list)
    {
        self::showTable($list, ['islite' => false, 'iscli' => true]);
    }
    public static function showTableWeb($list)
    {
        self::showTable($list, ['islite' => false, 'iscli' => false]);
    }
    public static function showTableWebLite($list)
    {
        self::showTable($list, ['islite' => true, 'iscli' => false]);
    }
    /**
     * 把数据集显示为表格
     * @param array $list
     * @param boolean $islite
     * @param boolean $isCli
     * @version 2017-12-15
     */
    private static function showTable($list, array $option)
    {
        $defaultOption = ['isLite' => false, 'isCli' => false];
        $option = array_merge($defaultOption, $option);
        extract($option);
        if (!$list) {
            echo $isCli ? '没有查询到任何记录' : '<p>没有查询到任何记录!</p>';
            return;
        }
        $i = 0;
        if (!$isCli) {
            echo '<table class="table table-sm table-striped table-bordered table-hover">';
        }
        foreach ($list as $row) {
            if ($i < 1 && !$isCli) {
                if ($islite) {
                    echo '<tr><th style="min-width:40px;">#</th><th>', implode('</th><th>', array_keys($row)),
                    '</th><tr>';
                } else {
                    $fragment = 'result' . $i;
                    echo '<tr><th style="min-width:40px;">#<a name="', $fragment, '" href="###', $fragment,
                    '" class="lnk-show" title="显示所有隐藏列">[+]</a></th><th>';
                    echo implode(
                        '<a href="###' . $fragment . '" class="lnk-hide" title="隐藏此列">[-]</a></th><th>',
                        array_keys($row)
                    );
                    echo '<a href="###' . $fragment . '" class="lnk-hide" title="隐藏此列">[-]</a></th><tr>';
                }
            }
            $i++;
            if ($isCli) {
                echo '# ', $i, ' ------------------------------------------------', PHP_EOL;
                foreach ($row as $k => $v) {
                    echo $k, ' = ', $v, PHP_EOL;
                }
            } else {
                echo '<tr><td>', $i, '</td>';
                foreach ($row as $td) {
                    echo '<td>', (is_null($td) ? '<span style="color:#AAA">NULL</span>' : htmlspecialchars($td)),
                    '</td>';
                }
                echo '</tr>';
            }
        }
        if (!$isCli) {
            echo '</table>';
        }
    }
    public static function startTable()
    {
        echo '<table class="table table-striped table-bordered table-hover table-sm">';
    }
    public static function endTable()
    {
        echo '</table>';
    }
    public static function makeNavTabs($id, $tabs, $activeId = '')
    {
        echo '<nav><div class="nav nav-tabs">';
        foreach ($tabs as $key => $tab) {
            $keyName = $id . '-' . $key;
            echo '<a class="nav-item nav-link', ($activeId == $key ? ' active' : ''), '" id="nav-', $keyName, '-tab" data-toggle="tab" href="#nav-', $keyName, '" title="', $tab['title'], '">', $tab['text'], '</a>';
        }
        echo '</div></nav>';
    }
    public static function makeAccordionCardStart($group, $id, $text)
    {
        $id = $group . $id;
        echo <<<tpl
        <div class="card">
            <div class="card-header" id="heading{$id}">
                <h2 class="mb-0">
                    <button class="btn btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapse{$id}" aria-expanded="false" aria-controls="collapse{$id}">{$text}</button>
                </h2>
            </div>
            <div id="collapse{$id}" class="collapse show" aria-labelledby="heading{$id}" data-parent="#accordion{$group}">
                <div class="card-body">
        tpl;
    }
    public static function makeAccordionCardEnd()
    {
        echo '</div></div></div>';
    }
}
