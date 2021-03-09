<?php

namespace mysqladminlite\lib;

/**
 * Html UI资源类
 */
class HtmlUIResource
{
    static $uiResource = [
        'provider' => 'local',
        'css' => [
            '_tpl' => '<link href="%s" rel="stylesheet" />',
            'bootstrap' => [
                'cdn' => 'https://cdn.bootcss.com/twitter-bootstrap/4.4.1/css/bootstrap.min.css',
                'local' => '/assets/dist/css/adminlte.min.css'
            ]
        ],
        'javascript' => [
            '_tpl' => '<script src="%s"></script>',
            'jquery' => [
                'cdn' => 'https://cdn.bootcss.com/jquery/3.4.1/jquery.min.js',
                'local' => '/assets/dist/plugins/jquery/jquery.min.js'
            ],
            'bootstrap' => [
                'cdn' => 'https://cdn.bootcss.com/twitter-bootstrap/4.4.1/js/bootstrap.bundle.min.js',
                'local' => '/assets/dist/plugins/bootstrap/bootstrap.bundle.min.js'
            ]
        ]
    ];
    public static function setUiProvider($provider)
    {
        self::$uiResource['provider'] = $provider;
    }
    public static function setUiResource($map)
    {
        self::$uiResource = array_merge(self::$uiResource, $map);
    }

    public static function getResourceLink($names, $type = 'css')
    {
        $maps = self::$uiResource;
        if (!$names || !$type || !$maps) {
            return '';
        }
        if (!array_key_exists($type, $maps)) {
            return '';
        }
        if (!is_array($names)) {
            $names = [$names];
        }
        $uri = '';
        $provider = $maps['provider'];
        $map = $maps[$type];
        $content = '';
        foreach ($names as $name) {
            if (empty($map[$name])) {
                continue;
            }
            $uri = empty($map[$name][$provider]) ? false : $map[$name][$provider];
            if (!$uri) {
                foreach ($map[$name] as $varUrl) {
                    if ($varUrl) {
                        $uri = $varUrl;
                        break;
                    }
                }
                if (!$uri) {
                    continue;
                }
            }
            $content .= sprintf($map['_tpl'], $uri) . PHP_EOL;
        }
        return $content;
    }

    public static function getStyle()
    {
        return <<<tpl
        body{font-size:13px;}
        .nav-tabs{margin-top:5px;}
        .tab-pane{padding-top:6px;max-height:250px;overflow-y:auto;}
        .nav-tabs a.nav-link{padding:0.2rem .6rem;}
        .overview{line-height:25px;}
        .overview mark{margin-right:1em;}
        .table td,.table th{font-size:12px;}
        aside{width:250px !important;}
        aside .tab-content{padding-left:10px;}
        aside .list-unstyled{line-height:21px;}
        main{width:calc(100vw - 250px);padding-left:10px}
        #nav-table{max-height:400px;}
        .btngroup a{margin-right:5px;}
        .accordion .card-header{padding:0;}
        .accordion .card-header h2 button{font-size:13px;}
        .accordion .card-body{padding:0.5rem;}
        .code-editor{background-color:#384548 !important;color:#abe338 !important;}
        .table-container {max-width:100%; overflow-x:auto}
        tpl;
    }
    public static function getScript()
    {
        return <<<tpl
        // 显示或者隐藏侧边栏、收藏夹等
        document.querySelectorAll('.btn-toggle-panel').forEach(el=>{
            el.addEventListener('click', evt => {
                let elPanel = document.querySelector(evt.target.dataset.targetSelector);
                if(elPanel){
                    elPanel.classList.toggle('d-none');
                }
            })
        })
        jQuery(document).ready(function () {
            showDbExecTime();
            mysalInit();
        });
        function mysalInit(){
            // 2017-10-28 服务器信息改为显隐控制，提高安全性，防止被窥探
            jQuery('#lnk-toggle-ipinfo').click(function () {
                let jqA = jQuery(this);
                if (jqA.text() == '[...]') {
                    jqA.text('[x]').prev().show();
                } else {
                    jqA.text('[...]').prev().hide();
                }
                return false;
            })
        
            // 展开或收起fieldset内容
            jQuery('.lnk-switch').click(function () {
                let jqA = jQuery(this);
                jqA.parent().parent().find('.fieldset_content').toggle();
            })
            // 数据库切换, 服务器切换
            jQuery('#dbslt, #servslt').change(function () {
                let val = jQuery(this).val();
                if (!confirm('您确定要跳转到 ' + val + ' 吗?')) {
                    return false;
                }
                if (val.length > 0) {
                    location.href = jQuery(this).find('option[value="' + val + '"]').data('url');
                }
            })
            // 侧边栏表格下拉框切换
            jQuery('#slt-table, #slt-view, #slt-procedure, #slt-function').change(function () {
                let val = jQuery(this).val();
                if (val.length > 0) {
                    location.href = jQuery(this).find('option[value="' + val + '"]').data('url');
                }
            })
            // 选中表格行突出背景色
            jQuery('.table td').click(function () {
                jQuery(this).parent().toggleClass('bg-warning');
            })
            // 隐藏表格列
            jQuery('.table th a.lnk-hide').click(function () {
                let jqTh = jQuery(this).parent();
                let idx = jqTh.index();
                jqTh.hide().parent().parent().find('td:nth-child(' + (idx + 1) + ')').hide();
            })
            // 显示表格列
            jQuery('.table th a.lnk-show').click(function () {
                let jqTh = jQuery(this).parent();
                jqTh.parent().parent().find('td, th').show();
            })
        
            // SQL编辑器验证码验证
            jQuery('#sqleditorform [type="submit"]').click(function () {
                let sql = jQuery.trim(jQuery('textarea[name="sql"]').val());
                if (!sql) {
                    alert('未输入命令!')
                    return false;
                }
                // 2017-10-27 高危命令提醒
                let firstword = sql.split(' ')[0].toLowerCase();
                let isDanger = (firstword == 'drop' || firstword == 'truncate');
                if ((firstword == 'delete' && sql.indexOf(' where ') < 0)) {
                    isDanger = true;
                }
                if (isDanger) {
                    let code = Math.ceil(Math.random() * 1000);
                    let msg = '您将执行的是高危命令！<br />为避免类似删库跑路的惨剧发生，' +
                        '请输入安全码确认操作！<br />安全码是：' + code;
                    let inputCode = prompt(msg.replace(/<br \/>/g, "\\n"));
                    if (!inputCode) {
                        return false;
                    }
                    if (inputCode != code) {
                        alert('安全码输入错误!');
                        return false;
                    }
                }
                // 正式环境验证码
                let jqCaptcha = jQuery('input[name="captcha"]');
                if (jqCaptcha[0]) {
                    if (jqCaptcha.val() != jqCaptcha.prop('placeholder')) {
                        jQuery('#error').text('验证码错误');
                        jqCaptcha.val('');
                        return false;
                    }
                    if (!confirm('当前为生产环境，您确认执行此操作吗？')) {
                        return false;
                    }
                }
                return true;
            })
            // 2017-11-3 验证码点击3次自动填表
            jQuery('input[name="captcha"]').click(function () {
                let jqInput = jQuery(this);
                let n = jqInput.data('clickCount');
                if (!n) {
                    n = 0;
                }
                n++;
                if (n % 3 == 0) {
                    n = 0;
                    jqInput.val(jqInput.prop('placeholder'));
                }
                jqInput.data('clickCount', n);
            })
            // sql编辑器点击3次自动去除注释符号(--或eg.)
            jQuery('textarea[name="sql"]').click(function () {
                let jqTa = jQuery(this);
                let txt = jqTa.val();
                if (!txt) {
                    return;
                }
                let n = jqTa.data('clickcount');
                if (!n) {
                    n = 0;
                }
                n++;
                jqTa.data('clickcount', n);
                if (n % 3 == 0) {
                    let has_run = false;
                    let atxt = txt.split("\\n");
                    let stxt = '';
                    let sline = '';
                    let len = 0;
                    let strlen3 = '';
                    for (let i in atxt) {
                        sline = jQuery.trim(atxt[i]);
                        len = sline.length;
                        strlen3 = sline.substring(0, 3);
                        if (len > 4 && (sline.substring(0, 4) == 'eg. ')) {
                            stxt += sline.substring(4) + "\\n";
                            has_run = true;
                        } else if (len > 3 && (strlen3 == '-- ') || (strlen3 == 'eg.')) {
                            stxt += sline.substring(3) + "\\n";
                            has_run = true;
                        } else if (len > 2 && sline.substring(0, 2) == '--') {
                            stxt += sline.substring(2) + "\\n";
                            has_run = true;
                        } else {
                            stxt += sline + "\\n";
                        }
                    }
                    if (has_run) { // 有替换操作才重新复制,解决无需替换的时候,鼠标点击三次光标会自动移动到最后的问题
                        jqTa.val(stxt);
                    }
                }
            })
            // 在当前SQL编辑窗口显示示例语句 2017-11-23
            jQuery('.btn_example_sql').click(function () {
                let sql = jQuery(this).data('sql');
                if (!sql) {
                    return false;
                }
                let jqTextarea = jQuery('textarea[name="sql"]');
                if (!jqTextarea.val()) {
                    jqTextarea.val(sql);
                } else {
                    jqTextarea.val(jqTextarea.val() + "\\n\\n" + sql);
                }
                return false;
            })
            // SQL编辑器的复制、粘帖等功能
            function doEditorCmd(elid, cmd) {
                let el = document.getElementById(elid);
                switch (cmd) {
                    case 'copy':
                        el.select();
                        document.execCommand("Copy");
                        alert("内容复制成功！");
                        break;
                    case 'paste':
                        alert('该功能尚未支持! 请使用快捷键 [ctrl+v]');
                        el.focus();
                        break;
                    case 'select':
                        el.select();
                        break;
                    case 'clear':
                        el.value = '';
                        break;
                }
                return false;
            }
            // 加载mysal目录的文件
            jQuery('#file-list a').click(function (event) {
                let url = jQuery(this).attr('href');
                if (url.indexOf('###') > -1) {
                    return false;
                }
                jQuery('#file-path').val(jQuery(this).data('path'));
                jQuery('#file-editor').load(url, function (data) {
                    // jquery load 陷阱 www.cnblogs.com/whatlonelytear/p/7887059.html
                    jQuery('#file-editor').val('');
                    jQuery('#file-editor').val(data);
                });
                return false;
            });
            // 修改并保存mysal目录的文件
            jQuery('#file-form .btn-primary').click(function (event) {
                //event.preventDefault();
                let jqForm = jQuery('#file-form');
                jQuery.post(jqForm.attr('action'), jqForm.serialize(), function (data) {
                    console.log(data);
                    alert('保存成功！');
                })
                return false;
            })
        }
        /**
         * 显示数据库脚本执行时间
         * @param {*integer} time 
         */
        function showDbExecTime(){
            let time=jQuery('#exectimer_value').val();
            if(!time){
                return;
            }
            jQuery('#exectimer').text("本次执行总耗时 "+time+" 毫秒 ").show();
        }
        tpl;
    }
}
