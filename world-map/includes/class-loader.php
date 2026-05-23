<?php

namespace WorldMap;

class Loader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(static function (string $class) use ($baseDir): void {
            $prefix = __NAMESPACE__ . '\\';

            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativePath  = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass);
            $file          = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'class-' . strtolower(str_replace(DIRECTORY_SEPARATOR, '-class-', $relativePath)) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
