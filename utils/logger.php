<?php

class Logger
{
    private static string $logFile = __DIR__ . '/../storage_app.log';

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            empty($context) ? '' : json_encode($context)
        );
        @file_put_contents(self::$logFile, $line, FILE_APPEND);
    }
}
