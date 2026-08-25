<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Token;
use SqlFixture\Schema\SchemaParseException;

/**
 * Reads the parts of a parsed MySQL CREATE TABLE statement.
 *
 * The upstream parser is forgiving in a way that is dangerous here: on a
 * syntax error it abandons the rest of the definition list rather than
 * refusing the statement, so a stray keyword silently takes every column after
 * it. What comes back looks like a complete table and is not, and the damage
 * surfaces much later as a column no fixture ever fills. Checking that is part
 * of reading the statement, so it lives here.
 */
final class MySqlCreateStatement
{
    /**
     * Refuses a statement the parser only read part of.
     *
     * Not every reported error loses something: DEC and FIXED are valid
     * synonyms the parser calls unrecognised while still reading the column.
     * The test is therefore what came out, not whether anything was said.
     *
     * @param Parser $parser Parser that read the statement
     * @param CreateStatement $statement Statement it produced
     * @param string $sql Statement as it was written
     *
     * @throws SchemaParseException When fewer columns came out than the statement declares
     */
    public function assertNothingWasLost(Parser $parser, CreateStatement $statement, string $sql): void
    {
        if ($parser->errors === []) {
            return;
        }

        $declared = $this->declaredDefinitions($parser);
        $fields = $statement->fields;
        if ($declared !== null && is_array($fields) && count($fields) >= $declared) {
            return;
        }

        throw SchemaParseException::invalidSql($sql, $parser->errors[0]->getMessage());
    }

    /**
     * Counts how many definitions the parenthesized body separates.
     *
     * Counting uses the parser's own tokens, which already know that a comma
     * inside a string or an ENUM is not a separator.
     *
     * @param Parser $parser Parser that read the statement
     *
     * @return int|null How many definitions were declared, or null when the body is never closed
     */
    public function declaredDefinitions(Parser $parser): ?int
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
            } elseif ($token->value === ')') {
                $depth--;
                if ($depth === 0) {
                    return $definitions;
                }
            } elseif ($depth === 1 && $token->value === ',') {
                $definitions++;
            }
        }

        return null;
    }

    /**
     * Answers the name of the table the statement creates.
     *
     * @param CreateStatement $statement Statement the parser produced
     * @param string $sql Statement as it was written
     *
     * @return string The table name, with its backticks removed
     *
     * @throws SchemaParseException When the statement names no table
     */
    public function tableName(CreateStatement $statement, string $sql): string
    {
        if ($statement->name === null) {
            throw SchemaParseException::invalidSql($sql, 'Table name not found');
        }

        return str_replace('`', '', $statement->name->table ?? '');
    }

    /**
     * Answers the columns the primary key is made of.
     *
     * A key may be declared beside its column or on its own line, and only the
     * second form can name more than one column, so both are read.
     *
     * @param CreateStatement $statement Statement the parser produced
     *
     * @return list<string> Column names the key is made of
     */
    public function primaryKeys(CreateStatement $statement): array
    {
        if (!is_iterable($statement->fields)) {
            return [];
        }

        $primaryKeys = [];
        foreach ($statement->fields as $field) {
            if ($field->options instanceof OptionsArray && $field->options->has('PRIMARY KEY') !== false) {
                $name = $field->name;
                if (is_string($name) && $name !== '') {
                    $primaryKeys[] = str_replace('`', '', $name);
                }
            }
            if ($field->key !== null && $field->key->type === 'PRIMARY KEY') {
                foreach ($field->key->columns as $column) {
                    $name = $column['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $primaryKeys[] = str_replace('`', '', $name);
                    }
                }
            }
        }

        return $primaryKeys;
    }
}
