<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Database;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Decodes the fixed operand domains used by database defaults without evaluating expressions.
 * @visibility SqlSemantics
 */
final class DatabaseOperands
{
    /**
     * Interprets identifier-or-text names with MySQL's lexical string escaping.
     */
    public static function name(Token $token, Identifiers $identifiers): string
    {
        return \SqlSemantics\Ast\MySqlNames::read($token, $identifiers);
    }

    /**
     * Follows the grammar's unsigned-integer prefix conversion for numeric terminals.
     * @throws InvalidSql
     */
    public static function readOnly(Node $source): DatabaseReadOnly
    {
        $token = $source->tokens()[0];
        $text = strtoupper($token->text);
        if ($text === 'DEFAULT') {
            return DatabaseReadOnly::Disabled;
        }
        if ($token->name === 'HEX_NUM') {
            $digits = str_starts_with($text, '0X') ? substr($text, 2) : substr($text, 2, -1);
        } else {
            preg_match('/^[0-9]+/', $text, $prefix);
            $digits = $prefix[0] ?? '0';
        }
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return DatabaseReadOnly::Disabled;
        }
        if ($digits === '1') {
            return DatabaseReadOnly::Enabled;
        }
        throw new InvalidSql(InputViolation::DatabaseReadOnly, $source);
    }

    /**
     * Requires the encryption switches defined by the database language.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function encryption(Node $source, Identifiers $identifiers): DatabaseEncryption
    {
        $literal = Tree::child($source, ['TEXT_STRING_sys']) ?? throw new UnclassifiedSql('Database encryption requires its literal option.');
        return match (strtoupper(self::name($literal->tokens()[0], $identifiers))) {
            'Y' => DatabaseEncryption::Enabled,
            'N' => DatabaseEncryption::Disabled,
            default => throw new InvalidSql(InputViolation::DatabaseEncryption, $source),
        };
    }
}
