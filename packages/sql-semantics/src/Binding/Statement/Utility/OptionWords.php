<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Scalar\Intrinsic\FieldSpelling;
use SqlSemantics\Binding\Statement\UnclassifiedSql;

/**
 * Decodes PostgreSQL utility option arguments the way the server's option readers see them.
 * @visibility SqlSemantics
 */
final class OptionWords
{
    /**
     * Reads a numeric argument as an integer when it fits a 32-bit integer and every other argument as its decoded text.
     * @throws UnclassifiedSql
     */
    public static function raw(Node $argument, Identifiers $identifiers): string|int
    {
        $tokens = $argument->tokens();
        $last = $tokens[count($tokens) - 1] ?? throw new UnclassifiedSql('An option argument requires a value.');
        if (in_array($last->name, ['ICONST', 'FCONST'], true)) {
            $sign = count($tokens) > 1 ? $tokens[0]->text : '';
            $integer = self::integer($last->text);
            if ($last->name === 'ICONST' && $integer !== null && $integer <= 2147483647) {
                return $sign === '-' ? -$integer : $integer;
            }
            return ($sign === '-' ? '-' : '') . str_replace('_', '', $last->text);
        }
        if (count($tokens) !== 1) {
            throw new UnclassifiedSql('Unclassified option argument: ' . $argument->toString());
        }
        return self::word($last, $identifiers);
    }

    /**
     * Decodes one word or string constant, folding the boolean keywords to the text the server stores.
     * @throws UnclassifiedSql
     */
    public static function word(Token $token, Identifiers $identifiers): string
    {
        return match ($token->name) {
            'TRUE_P' => 'true',
            'FALSE_P' => 'false',
            'ON' => 'on',
            default => FieldSpelling::read($token, $identifiers),
        };
    }

    /**
     * Reads an unsigned integer constant in decimal, hexadecimal, octal or binary notation.
     */
    public static function integer(string $text): ?int
    {
        $digits = strtolower(str_replace('_', '', $text));
        $value = match (true) {
            preg_match('/^[0-9]{1,18}$/D', $digits) === 1 => (int) $digits,
            preg_match('/^0x[0-9a-f]{1,15}$/D', $digits) === 1 => (int) hexdec(substr($digits, 2)),
            preg_match('/^0o[0-7]{1,20}$/D', $digits) === 1 => (int) octdec(substr($digits, 2)),
            preg_match('/^0b[01]{1,62}$/D', $digits) === 1 => (int) bindec(substr($digits, 2)),
            default => null,
        };
        return $value;
    }

    /**
     * Interprets an argument as the server's Boolean option reader does, or returns null when it is not Boolean.
     */
    public static function boolean(string|int $raw): ?bool
    {
        if (is_int($raw)) {
            return $raw === 0 ? false : ($raw === 1 ? true : null);
        }
        return match (strtolower($raw)) {
            'true', 'on' => true,
            'false', 'off' => false,
            default => null,
        };
    }
}
