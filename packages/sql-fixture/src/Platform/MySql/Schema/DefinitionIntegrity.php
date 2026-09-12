<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Token;
use SqlFixture\Schema\SchemaParseException;

/**
 * Checks that the native parser retained every declared column.
 *
 * @visibility root
 */
final class DefinitionIntegrity
{
    /**
     * Stop if the parser abandoned part of the definition list.
     *
     * On a syntax error the parser gives up on the rest of the definitions,
     * so a stray keyword takes every column after it. The result is a schema
     * that looks complete and is not, and the damage only shows up much later
     * as a column that generates no value.
     *
     * Not every error loses something. DEC and FIXED are valid synonyms the
     * parser reports as unrecognised while still reading the column, so the
     * test is what came out rather than whether anything was said.
     * @throws SchemaParseException
     */
    public function assertNothingWasLost(Parser $parser, CreateStatement $stmt, string $sql): void
    {
        if ($parser->errors === []) {
            return;
        }

        $fields = $stmt->fields;
        $declared = $this->countDeclaredDefinitions($parser);

        if ($declared !== null && is_array($fields) && count($fields) >= $declared) {
            return;
        }

        throw SchemaParseException::invalidSql($sql, $parser->errors[0]->getMessage());
    }

    /**
     * How many definitions the parenthesised body separates, or null when it
     * is never closed.
     *
     * Counting uses the parser's own tokens, which already know that a comma
     * inside a string or an ENUM is not a separator.
     */
    public function countDeclaredDefinitions(Parser $parser): ?int
    {
        $list = $parser->list;
        if ($list === null) {
            return null;
        }

        $depth = 0;
        $definitions = 1;

        foreach ($list->tokens as $token) {
            if ($token->type !== Token::TYPE_OPERATOR) {
                continue;
            }

            if ($token->value === '(') {
                $depth++;
                continue;
            }

            if ($token->value === ')') {
                $depth--;

                if ($depth === 0) {
                    return $definitions;
                }

                continue;
            }

            if ($depth === 1 && $token->value === ',') {
                $definitions++;
            }
        }

        return null;
    }
}
