<?php

namespace xjryanse\phplite\logic;

class ServiceRuntime
{
    public static function name(): string
    {
        $env = getenv('SERVICE_NAME');
        if ($env !== false && $env !== '') {
            return (string) $env;
        }

        if (defined('ROOT_PATH') && ROOT_PATH !== '') {
            $base = basename(rtrim((string) ROOT_PATH, '/\\'));
            if ($base !== '') {
                return $base;
            }
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $parent = basename(dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')));
            if ($parent !== '') {
                return $parent;
            }
        }

        return 'unknown';
    }

    public static function prefixMessage($message): string
    {
        $message = (string) $message;
        $service = static::name();
        if ($service === '' || $service === 'unknown') {
            return $message;
        }

        $prefix = '[' . $service . ']';
        if (strpos($message, $prefix) === 0) {
            return $message;
        }

        return $prefix . $message;
    }
}
