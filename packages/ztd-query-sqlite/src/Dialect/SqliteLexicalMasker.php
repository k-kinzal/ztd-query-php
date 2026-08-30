<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Dialect;

/**
 * The sqlite lexical masker.
 */
final class SqliteLexicalMasker
{
    /**
     * Mask comments.
     *
     * @param string $sql
     * @return string
     */
    public static function maskComments(string $sql): string
    {
        $result = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];

            if ($char === '\'' || $char === '"' || $char === '`') {
                $tail = substr($sql, $i);
                $quotedLength = self::quotedLength($tail, $char);
                $result .= substr($tail, 0, $quotedLength);
                $i += $quotedLength;
                continue;
            }

            if ($char === '[') {
                $tail = substr($sql, $i);
                $quotedLength = self::bracketQuotedLength($tail);
                $result .= substr($tail, 0, $quotedLength);
                $i += $quotedLength;
                continue;
            }

            $pair = substr($sql, $i, 2);
            if ($pair === '--' || $char === '#') {
                $commentLength = strcspn($sql, "\r\n", $i);
                $result .= ' ';
                $i += $commentLength;
                continue;
            }

            if ($pair === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                $result .= ' ';
                $i = $end === false ? $length : $end + 2;
                continue;
            }

            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * Answers how long a quoted run is, from where it opens.
     *
     * @param string $sql Statement being read, as written
     * @param string $quote The quote
     *
     * @return int What it answers
     */
    public static function quotedLength(string $sql, string $quote): int
    {
        $length = strlen($sql);
        $i = 1;

        while (true) {
            $end = strpos($sql, $quote, $i);
            if ($end === false) {
                return $length;
            }
            $quoteCount = strspn($sql, $quote, $end);
            $i = $end + $quoteCount;
            if ($quoteCount % 2 === 0) {
                continue;
            }

            return $i;
        }
    }

    /**
     * Answers how long a bracketed name is, from where it opens.
     *
     * @param string $sql Statement being read, as written
     *
     * @return int What it answers
     */
    public static function bracketQuotedLength(string $sql): int
    {
        $end = strpos($sql, ']');
        if ($end === false) {
            return strlen($sql);
        }

        return $end;
    }
}
