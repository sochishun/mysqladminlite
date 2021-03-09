<?php

namespace mysqladminlite\lib;

/**
 * FileHelper 文件助手类
 * @version 2018-1-13 SoChishun Added.
 */
class FileHandler
{
    /**
     * 列出所有文件
     * @param string $dir 路径
     * @param boolean $recursive 是否递归（匿名方法四个参数：file:文件名,filepath:文件路径,level:目录层级,isfile:是否文件）
     * @param array $exts 过滤要保留的扩展名
     * @param string $prefix 过滤要跳过的文件名前缀
     */
    public static function scandir($dir, $recursive = false, $exts = [], $skipPrefix = '', $closure = null, $level = 0)
    {
        $data = [];
        $files = scandir($dir);
        foreach ($files as $file) {
            $filepath = $dir . '/' . $file;
            if (is_dir($filepath)) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                // 如果递归就再循环下去
                if ($recursive) {
                    if ($closure instanceof \Closure) {
                        call_user_func_array($closure, [$file, $filepath, $level, false]);
                        self::scandir($filepath, $recursive, $exts, $skipPrefix, $closure, $level + 1);
                    } else {
                        $data[$file] = self::scandir($filepath, $recursive, $exts, $skipPrefix, $closure, $level + 1);
                    }
                }
                continue;
            }
            // 过滤扩展名和文件名前缀
            $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (($exts && !in_array($fileExt, $exts)) || ($skipPrefix && false === strpos($file, $skipPrefix))) {
                continue;
            }
            if ($closure instanceof \Closure) {
                // 匿名方法四个参数：file:文件名,filepath:文件路径,level:目录层级,isfile:是否文件
                call_user_func_array($closure, [$file, $filepath, $level, true]);
            } else {
                $data[$file] = $filepath;
            }
        }
        return $data;
    }
    /**
     * PHP 高效遍历文件夹（大量文件不会卡死，带文件名排序功能）
     * @param string $path 目录路径
     * @param array $skips 要忽略的文件路径集合
     * @param integer $deepth 扫描深度
     */
    public static function fastScanDirAndFiles($path = './', $skips = array(), $deepth = 0)
    {

        return self::fastScanDir($path, 0, true, $skips, $deepth);
    }
    /**
     * PHP 高效遍历文件夹（大量文件不会卡死，带文件名排序功能）
     * @param string $path 目录路径
     * @param array $skips 要忽略的文件路径集合
     * @param integer $deepth 扫描深度
     */
    public static function fastScanOnlyDir($path = './', $skips = array(), $deepth = 0)
    {
        return self::fastScanDir($path, 0, false, $skips, $deepth);
    }
    /**
     * PHP 高效遍历文件夹（大量文件不会卡死，带文件名排序功能）
     * @param string $path 目录路径
     * @param integer $level 目录深度层级
     * @param boolean $showfile 是否显示文件(否则只遍历显示目录)
     * @param array $skips 要忽略的文件路径集合
     * @param integer $deepth 扫描深度
     */
    private static function fastScanDir($path = './', $level = 0, $showfile = true, $skips = array(), $deepth = 0)
    {
        if (!file_exists($path) || ($deepth && $level > $deepth)) {
            return array();
        }
        $path = str_replace('//', '/', $path . '/');
        $file = new \FilesystemIterator($path);
        $filename = '';
        $icon = ''; // 树形层级图形
        if ($level > 0) {
            $icon = ('|' . str_repeat('--', $level));
        }
        $outarr = array();
        foreach ($file as $fileinfo) {
            $filename = iconv('GBK', 'utf-8', $fileinfo->getFilename()); // 解决中文乱码
            $filepath = $path . $filename;
            if ($fileinfo->isDir()) {
                if (!($skips && in_array($filepath . '/', $skips))) {
                    $outarr[$filename] = array('path' => $filepath, 'type' => 'dir', 'icon' => $icon);
                    $outarr[$filename]['children'] = self::fastScanDir($filepath, $level + 1, $showfile);
                }
                continue;
            }
            if ($showfile && !($skips && !in_array($filepath, $skips))) {
                $outarr[$filename] = array('path' => $filepath, 'type' => 'file', 'icon' => $icon);
            }
        }
        if ($outarr) {
            ksort($outarr);
        }
        return $outarr;
    }

    /**
     * 返回指定路径下的文件信息列表
     * @param string $path 路径
     * @return array
     * @throws Exception
     */
    static function getFileInfoList($path)
    {
        try {
            $dir = new \DirectoryIterator($path);
        } catch (\Exception $e) {
            throw $e;
        }
        $files = array();
        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }
            $exten = $file->getExtension();
            $item = array(
                'name' => $file->getFileName(),
                'path' => $file->getPath(),
                'real_path' => $file->getRealPath(),
                'exten' => $exten,
                'filetype' => self::getExtenCatetoryName($exten),
                'mtime' => $file->getMTime(),
                'ctime' => $file->getCTime(),
                'size' => $file->getSize(),
                'is_dir' => $file->isDir(),
                'is_file' => $file->isFile(),
                'is_link' => $file->isLink(),
                'is_executable' => $file->isExecutable(),
                'is_readable' => $file->isReadable(),
                'is_writable' => $file->isWritable(),
            );
            $item['relative_path'] = self::path2ator($file->getRealPath());
            $item['filepath'] = $item['path'] . '/' . $item['name'];
            $files[] = $item;
        }
        return $files;
    }

    /**
     * 格式化文件字节大小
     * @param int $size
     * @return string
     */
    public static function formatBytes($size)
    {
        $units = array(' B', ' KB', ' MB', ' GB', ' TB');
        for ($i = 0; $size >= 1024 && $i < 4; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . $units[$i];
    }
    /**
     * 获取文件扩展名类型
     * @param string $exten 扩展名(不带.)
     * @return string
     */
    public static function getExtenCatetoryName($exten)
    {
        if ($exten) {
            $filetypes = array(
                'zip' => array('zip', 'rar', '7-zip', 'tar', 'gz', 'gzip'),
                'doc' => array('txt', 'rtf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'wps', 'et'),
                'script' => array('php', 'js', 'css', 'c'),
                'image' => array('jpg', 'jpeg', 'png', 'gif', 'tiff', 'psd', 'bmp', 'ico')
            );
            foreach ($filetypes as $catetory => $extens) {
                if (in_array($exten, $extens)) {
                    return $catetory;
                }
            }
        }
        return '';
    }
    /**
     * 绝对路径转相对路径
     * @param string $path
     * @return string
     */
    public static function path2ator($path)
    {
        $root = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_DIRNAME);
        $path = substr($path, strlen($root));
        if ('/' != DIRECTORY_SEPARATOR) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        }
        return $path;
    }

    /**
     * 相对路径转绝对文件路径
     * @param string $path
     * @return string
     */
    public static function path2rtoa($path)
    {
        $root = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_DIRNAME);
        $pathstr = $root;
        $patharr = explode('/', $path);
        foreach ($patharr as $pathtmpstr) {
            if (!$pathtmpstr || $pathtmpstr == '.') {
                continue;
            }
            if ($pathtmpstr == '..') {
                $pathstr = pathinfo($pathstr, PATHINFO_DIRNAME);
                continue;
            }
            $pathstr .= '/' . $pathtmpstr;
        }

        return $pathstr . (is_dir($path) ? '/' : '');
    }
    /**
     * 自动创建路径中包含的多级目录
     * @param string  $path
     * @return string 目录路径
     */
    public static function mkdir($path)
    {
        // 文件或者目录如果已经存在则直接返回
        if (file_exists($path)) {
            return $path;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext) {
            $path = dirname($path);
            if (is_dir($path)) {
                return $path;
            }
        }
        mkdir($path, 0777, true);
        return $path;
    }

    /**
     * 删除非空目录里面所有文件和子目录
     * @param string $dir
     * @param boolean $delSelf 是否删除自身目录(删除还是清空)
     * @return boolean
     */
    public static function rmdir($dir, $delSelf = true)
    {
        //先删除目录下的文件：
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if ($file != "." && $file != "..") {
                $fullpath = $dir . "/" . $file;
                if (is_dir($fullpath)) {
                    self::rmdir($fullpath, true);
                } else {
                    unlink($fullpath);
                }
            }
        }
        closedir($dh);
        //删除当前文件夹：
        return (!$delSelf || rmdir($dir));
    }

    /**
     * PHP 高效读取文件
     * return 改为直接 echo，否则大文件会显示空白，而且大文件整体读取不如直接用 file_get_contents 高效
     * @param string $filepath
     * @return string
     */
    public static function tail($filepath)
    {
        if (file_exists($filepath)) {
            if (false !== ($fp = fopen($filepath, "r"))) {
                $buffer = 1024; //每次读取 1024 字节
                while (!feof($fp)) { //循环读取，直至读取完整个文件
                    echo htmlspecialchars(fread($fp, $buffer));
                }
                fclose($fp);
            } else {
                echo 'file can not open! [' . $filepath . ']';
            }
        } else {
            echo 'file not exists! [' . $filepath . ']';
        }
    }

    /**
     * PHP 高效写入文件（支持并发）
     * @param string $filepath
     * @param string $content
     */
    static function writeFile($filepath, $content)
    {
        if ($fp = fopen($filepath, 'a')) {
            $startTime = microtime();
            // 对文件进行加锁时，设置一个超时时间为 1ms，如果这里时间内没有获得锁，就反复获得，直接获得到对文件操作权为止。
            // 当然，如果超时限制已到，就必需马上退出，让出锁让其它进程来进行操作。
            do {
                $canWrite = flock($fp, LOCK_EX);
                if (!$canWrite) {
                    usleep(round(rand(0, 100) * 1000));
                }
            } while ((!$canWrite) && ((microtime() - $startTime) < 1000));
            if ($canWrite) {
                fwrite($fp, $content);
            }
            fclose($fp);
        }
    }

    /**
     * 下载远程文件到本地
     * @param string $url
     * @param string $filename
     * @param integer $type
     */
    function downloadFile($url, $filename = '', $type = 0)
    {
        if (!trim($url) || !trim($filename)) {
            return false;
        }
        //创建保存目录
        $dirname = dirname($filename);
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            throw new \Exception("创建目录失败！路径：" . $dirname);
        }
        //获取远程文件所采用的方法
        switch ($type) {
            case 1:
                if (!function_exists('curl_init')) {
                    throw new \Exception("curl 扩展未安装！");
                }
                $timeout = 5;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                $content = curl_exec($ch);
                curl_close($ch);
                break;
            case 2:
                ob_start();
                readfile($url);
                $content = ob_get_contents();
                ob_end_clean();
            default:
                $content = file_get_contents($url);
                break;
        }
        // 判断下载的数据 是否为空 下载超时问题
        if (empty($content)) {
            throw new \Exception("下载错误,无法获取下载文件！");
        }
        // 保持数据
        $fp = @fopen($filename, 'a');
        fwrite($fp, $content);
        fclose($fp);
        return $filename;
    }

    /**
     * 检测并且创建目录
     * @param string $pathname
     * @param integer $mode
     * @param boolean $recursive
     * @return boolean
     */
    public static function ensureDir($pathname, $mode = 0777, $recursive = false)
    {
        if (!$pathname) {
            return false;
        }
        if (is_dir($pathname)) {
            return true;
        }
        return mkdir($pathname, $mode, $recursive);
    }

    /**
     * 自动填充后缀名
     */
    public static function ensureSuffix($filename, $suffix)
    {
        if (!is_array($suffix)) {
            $suffix = [$suffix];
        }
        foreach ($suffix as $v) {
            if ($v == substr($filename, -strlen($v))) {
                continue;
            }
            $filename .= $v;
        }
        return $filename;
    }
}
