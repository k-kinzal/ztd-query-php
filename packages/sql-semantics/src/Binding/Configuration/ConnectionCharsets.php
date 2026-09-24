<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet;
use SqlSemantics\Model\Configuration\Connection\ConnectionNames;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds the MySQL SET items NAMES and CHARACTER SET (or CHARSET), which name character sets rather than assign a variable.
 * @visibility SqlSemantics
 */
final class ConnectionCharsets
{
    /**
     * Returns null for an item that is not NAMES or CHARACTER SET; NAMES = value is rejected as the server does.
     * @param list<Token> $tokens One SET item
     * @throws InvalidSql
     */
    public static function bind(array $tokens, Node $source, Scope $scope): ConnectionNames|ConnectionCharacterSet|null
    {
        $words = SettingTokens::words($tokens);
        $length = self::keyword($words);
        if ($length === 0) {
            return null;
        }
        $operands = array_slice($tokens, $length);
        $collate = array_search('COLLATE', SettingTokens::words($operands), true);
        $name = $operands[0] ?? null;
        if ($name === null || in_array($name->text, ['=', ':='], true) || count($operands) !== ($collate === false ? 1 : 3) || ($collate !== false && ($collate !== 1 || $words[0] !== 'NAMES'))) {
            throw new InvalidSql(InputViolation::SessionSetting, $source);
        }
        try {
            return $words[0] === 'NAMES' ? new ConnectionNames(self::name($name, $scope), $collate === false ? null : self::name($operands[2], $scope)) : new ConnectionCharacterSet(self::name($name, $scope));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SessionSetting, $source, $error);
        }
    }

    /**
     * Counts the words spelling NAMES, CHARSET, CHAR SET or CHARACTER SET at the start of an item; zero for any other item.
     * @param list<string> $words Uppercase item words
     */
    public static function keyword(array $words): int
    {
        return match (true) {
            ($words[0] ?? '') === 'NAMES', ($words[0] ?? '') === 'CHARSET' => 1,
            in_array($words[0] ?? '', ['CHAR', 'CHARACTER'], true) && ($words[1] ?? '') === 'SET' => 2,
            default => 0,
        };
    }

    /**
     * Decodes a character set or collation name; the DEFAULT keyword yields null and the BINARY keyword names the binary character set.
     */
    public static function name(Token $token, Scope $scope): ?string
    {
        return match ($token->name) {
            'DEFAULT', 'DEFAULT_SYM' => null,
            'BINARY', 'BINARY_SYM' => 'binary',
            default => MySqlNames::read($token, $scope->identifiers),
        };
    }
}
