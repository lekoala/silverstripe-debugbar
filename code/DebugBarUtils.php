<?php

namespace LeKoala\DebugBar;

class DebugBarUtils
{

    /**
     * Format a sql query string using available formatters.
     * If no formatters are available, simply return the string as is.
     * Highlighting is used if not in cli context
     */
    public static function formatSql(string $query): string
    {
        if (class_exists(\Doctrine\SqlFormatter\SqlFormatter::class)) {
            return (new \Doctrine\SqlFormatter\SqlFormatter())->format($query);
        }
        if (class_exists(\SqlFormatter::class)) {
            return \SqlFormatter::format($query, !self::isCli());
        }
        if (class_exists(\SilverStripe\View\Parsers\SQLFormatter::class)) {
            $method = self::isCli() ? 'formatPlain' : 'formatHTML';
            $formatter = new \SilverStripe\View\Parsers\SQLFormatter;
            return $formatter->$method($query);
        }
        return $query;
    }

    public static function isCli(): bool
    {
        return in_array(PHP_SAPI ?: '', ['cli', 'phpdbg']) || !http_response_code();
    }
}
