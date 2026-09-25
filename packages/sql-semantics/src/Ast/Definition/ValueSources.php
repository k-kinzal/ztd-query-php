<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ColumnReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads which DEFAULT clause gives a column its default, as each server decides among repeated DEFAULT, identity and
 * generation clauses: PostgreSQL rejects any second value source, SQLite takes the last DEFAULT but rejects a DEFAULT
 * or a second generation clause beside a generation clause, and MySQL takes the last literal DEFAULT and the last
 * DEFAULT expression, rejecting the two together unless the literal is NULL, which the expression then replaces.
 *
 * @visibility SqlSemantics
 */
final class ValueSources
{
    /**
     * Returns the DEFAULT attribute that decides the column default, or null when none is written.
     *
     * @param list<Node> $attributes Column attributes in SQL order
     * @param bool $generated Whether a MySQL generation expression follows the column type
     * @param bool $counter Whether the MySQL SERIAL type declares the column AUTO_INCREMENT
     * @throws InvalidSql
     */
    public static function default(Dialect $dialect, array $attributes, bool $generated = false, bool $counter = false): ?Node
    {
        $defaults = [];
        $sources = [];
        $generations = [];
        foreach ($attributes as $attribute) {
            $words = ColumnReader::attributeWords($attribute);
            if (($words[0] ?? '') === 'DEFAULT') {
                $defaults[] = $attribute;
                $sources[] = $attribute;
            } elseif (in_array('GENERATED', $words, true) || in_array('IDENTITY', $words, true) || ($words[0] ?? '') === 'AS') {
                $generations[] = $attribute;
                $sources[] = $attribute;
            }
            $counter = $counter || in_array($words, [['AUTO_INCREMENT'], ['SERIAL', 'DEFAULT', 'VALUE']], true);
        }
        if ($dialect === Dialect::PostgreSql && isset($sources[1]) || $dialect === Dialect::Sqlite && (isset($generations[1]) || $generations !== [] && $defaults !== [])) {
            throw new InvalidSql(InputViolation::ValueSources, $sources[1] ?? $attributes[0]);
        }
        return $dialect === Dialect::MySql ? self::mySql($defaults, $generated, $counter) : ($defaults[count($defaults) - 1] ?? null);
    }

    /**
     * Returns the MySQL default: the last DEFAULT expression, or else the last literal DEFAULT.
     *
     * @param list<Node> $defaults DEFAULT attributes in SQL order
     * @throws InvalidSql
     */
    public static function mySql(array $defaults, bool $generated, bool $counter): ?Node
    {
        if ($generated && $defaults !== []) {
            throw new InvalidSql(InputViolation::ValueSources, $defaults[0]);
        }
        $literal = null;
        $expression = null;
        foreach ($defaults as $default) {
            if (Tree::child($default, ['expr']) !== null) {
                $expression = $default;
            } else {
                $literal = $default;
            }
        }
        $valued = $literal !== null && ColumnReader::attributeWords($literal) !== ['DEFAULT', 'NULL'] ? $literal : null;
        if ($counter && ($expression ?? $valued) !== null || $expression !== null && $valued !== null) {
            throw new InvalidSql(InputViolation::ValueSources, $expression ?? $valued ?? $defaults[0]);
        }
        return $expression ?? $literal;
    }
}
