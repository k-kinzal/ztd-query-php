<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads MySQL unsigned number tokens the way the server grammar converts them.
 * @visibility SqlSemantics
 */
final class MySqlNumbers
{
    /**
     * Reads the last token of a number production; decimal and float spellings keep their integer part unless integers are required.
     * @throws InvalidSql
     */
    public static function read(Node|Token $source, InputViolation $violation, bool $integerOnly = false): int
    {
        $token = $source instanceof Token ? $source : ($source->tokens()[count($source->tokens()) - 1] ?? null);
        $text = $token === null ? '' : strtolower($token->text);
        if (str_starts_with($text, '0x')) {
            $digits = ltrim(substr($text, 2), '0');
            return self::bounded($digits === '' ? '0' : (string) hexdec($digits), strlen($digits) > 15, $source, $violation);
        }
        if (preg_match('/^[0-9]+$/D', $text) !== 1 && ($integerOnly || preg_match('/^[0-9]*[.e]/', $text) !== 1)) {
            throw new InvalidSql($violation, $source);
        }
        preg_match('/^[0-9]*/', $text, $match);
        $digits = ltrim($match[0] ?? '', '0');
        return self::bounded($digits === '' ? '0' : $digits, strlen($digits) > 19, $source, $violation);
    }

    /**
     * Converts decimal digits that fit the signed 64-bit range.
     * @throws InvalidSql
     */
    public static function bounded(string $digits, bool $long, Node|Token $source, InputViolation $violation): int
    {
        $number = filter_var($digits, FILTER_VALIDATE_INT);
        if ($long || $number === false) {
            throw new InvalidSql($violation, $source);
        }
        return $number;
    }
}
