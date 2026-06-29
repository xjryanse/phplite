<?php

namespace xjryanse\phplite\service\worker;

use Workerman\Timer;
/**
 * 热重载
 */
trait HotReloadTrait {
    /**
     * 极简版热重载（支持监听子目录文件）
     */
    /** 兼容分步入口（worker.php 等）从外部调用 */
    public static function simpleHotReload() {
        $watchDirs = [
            ROOT_PATH . 'app',
            ROOT_PATH . 'core',
            ROOT_PATH . 'logic',
        ];

        $watchDirs = array_values(array_filter($watchDirs, function ($dir) {
            return is_dir($dir) && is_readable($dir);
        }));

        foreach ($watchDirs as $watchDir) {
            echo "【调试】监听目录：{$watchDir}\n";
        }

        // 初始时间设为当前时间
        $lastMtime = time();

        Timer::add(5, function () use ($watchDirs, &$lastMtime) {
            // 初始化当前最新修改时间
            $currentMtime = $lastMtime;

            // 使用递归函数遍历所有子目录（替代glob，兼容性更好）
            $scanFiles = function ($dir) use (&$scanFiles, &$currentMtime) {
                // 跳过不存在的目录
                if (!is_dir($dir) || !is_readable($dir)) {
                    return;
                }

                // 打开目录句柄
                $dirHandle = opendir($dir);
                if (!$dirHandle) {
                    return;
                }

                // 遍历目录中的所有项
                while (false !== ($file = readdir($dirHandle))) {
                    // 跳过.和..目录
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $fullPath = $dir . DIRECTORY_SEPARATOR . $file;

                    // 如果是目录，递归遍历子目录
                    if (is_dir($fullPath)) {
                        $scanFiles($fullPath);
                        continue;
                    }

                    // 只处理PHP文件（可根据需要扩展其他后缀，如.php,.html等）
                    if (pathinfo($fullPath, PATHINFO_EXTENSION) !== 'php') {
                        continue;
                    }

                    // 获取文件修改时间，加@屏蔽不存在文件的警告
                    $fileMtime = @filemtime($fullPath);
                    if ($fileMtime && $fileMtime > $currentMtime) {
                        $currentMtime = $fileMtime;
                        echo "【调试】检测到修改文件：{$fullPath}，修改时间：" . date('Y-m-d H:i:s', $fileMtime) . "\n";
                    }
                }
                closedir($dirHandle);
            };

            // 执行递归扫描
            foreach ($watchDirs as $watchDir) {
                $scanFiles($watchDir);
            }

            // 检测到文件修改，执行平滑重载
            if ($currentMtime > $lastMtime) {
                echo "[" . date('Y-m-d H:i:s') . "] 代码变更，平滑重载Workerman\n";
                $lastMtime = $currentMtime;
                $entryFile = ROOT_PATH . 'public/worker.php';
                if (is_file($entryFile)) {
                    $reloadCmd = PHP_BINARY . ' ' . escapeshellarg($entryFile) . ' reload -g';
                    exec($reloadCmd, $output, $returnVar);
                    if ($returnVar !== 0) {
                        echo "【错误】平滑重载失败：" . implode("\n", $output) . "\n";
                    }
                } else {
                    echo "【调试】未找到 public/worker.php，跳过平滑重载，请手动重启\n";
                }
            }
        });
    }
}
