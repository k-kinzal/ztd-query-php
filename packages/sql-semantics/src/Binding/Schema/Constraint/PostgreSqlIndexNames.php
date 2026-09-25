<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Constraint;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * Gives an unnamed PostgreSQL index the name the server chooses (ChooseIndexName): the table name, the names of its
 * key and included columns joined with underscores, and idx, numbered (idx1, idx2, ...) while a relation of the
 * namespace has the name. An expression key is named as the server names a result column (FigureColname): a column
 * reference by its column, a function call by its function, a cast by its operand or else its type, and any other
 * expression expr; a name repeated among the parts is numbered (expr, expr1).
 *
 * @visibility SqlSemantics
 */
final class PostgreSqlIndexNames
{
    /**
     * The internal names of the type keywords a cast can name.
     */
    public const TYPE_NAMES = [
        'INT' => 'int4', 'INTEGER' => 'int4', 'SMALLINT' => 'int2', 'BIGINT' => 'int8', 'REAL' => 'float4', 'FLOAT' => 'float8',
        'DOUBLE PRECISION' => 'float8', 'DECIMAL' => 'numeric', 'DEC' => 'numeric', 'NUMERIC' => 'numeric', 'BOOLEAN' => 'bool',
        'CHARACTER' => 'bpchar', 'CHAR' => 'bpchar', 'NCHAR' => 'bpchar', 'NATIONAL CHARACTER' => 'bpchar', 'NATIONAL CHAR' => 'bpchar',
        'VARCHAR' => 'varchar', 'CHARACTER VARYING' => 'varchar', 'CHAR VARYING' => 'varchar', 'BIT' => 'bit', 'BIT VARYING' => 'varbit',
        'TIMESTAMP' => 'timestamp', 'TIMESTAMP WITHOUT TIME ZONE' => 'timestamp', 'TIMESTAMP WITH TIME ZONE' => 'timestamptz',
        'TIME' => 'time', 'TIME WITHOUT TIME ZONE' => 'time', 'TIME WITH TIME ZONE' => 'timetz', 'INTERVAL' => 'interval', 'JSON' => 'json',
    ];

    /**
     * Returns the index with the name the server gives it in its table's namespace.
     *
     * @param list<TableDefinition> $tables Every table of the schema
     */
    public static function assign(IndexDefinition $index, TableDefinition $table, array $tables): IndexDefinition
    {
        if ($index->name !== null) {
            return $index;
        }
        $parts = array_map(static fn (\SqlSemantics\Schema\IndexElement $key): string => $key instanceof \SqlSemantics\Schema\Index\ColumnKey ? MySqlCounterKeys::name($key) : ($key instanceof \SqlSemantics\Schema\Index\ExpressionKey ? self::figure($key->source) : 'expr'), $index->elements);
        $addition = PostgreSqlConstraintNames::addition(PostgreSqlConstraintNames::indexColumns([...$parts, ...$index->include]));
        $name = PostgreSqlConstraintNames::choose($table->name, $addition, 'idx', self::relations($tables, $index->schema));
        return MySqlKeyNames::index($index, $name);
    }

    /**
     * Returns the relation names of one namespace: its tables, indexes, and the indexes of its keys.
     *
     * @param list<TableDefinition> $tables
     * @return list<string>
     */
    public static function relations(array $tables, string $schema): array
    {
        $names = [];
        foreach ($tables as $table) {
            if ($table->schema !== $schema) {
                continue;
            }
            $names[] = $table->name;
            foreach ($table->indexes as $index) {
                if ($index->name !== null) {
                    $names[] = $index->name;
                }
            }
            foreach ($table->constraints as $constraint) {
                if (($constraint instanceof Constraint\PrimaryKey || $constraint instanceof Constraint\UniqueKey) && ($constraint->index->name ?? $constraint->name) !== null) {
                    $names[] = $constraint->index->name ?? $constraint->name;
                }
            }
        }
        return array_values(array_filter($names, is_string(...)));
    }

    /**
     * Returns the name the server figures for an index expression, expr when it figures none.
     */
    public static function figure(Node|Token $source): string
    {
        return $source instanceof Node ? self::column($source)[0] ?? 'expr' : 'expr';
    }

    /**
     * Figures the name of an expression and its strength: 2 for a column, function or keyword function name, 1 for a
     * fallback name (a cast's type, case, array, row), 0 for none.
     *
     * @return array{?string, int}
     */
    public static function column(Node $node): array
    {
        $children = Tree::significant($node);
        $word = ($children[0] ?? null) instanceof Token ? strtoupper($children[0]->text) : '';
        return match (true) {
            $node->name === 'index_elem' => self::column(Tree::child($node, ['a_expr', 'func_expr_windowless']) ?? new Node('expr', 0, [])),
            $node->name === 'columnref' => [self::last($node), 2],
            $node->name === 'func_application' => [self::last(Tree::child($node, ['func_name']) ?? $node), 2],
            $node->name === 'func_expr_common_subexpr' => self::keywordFunction($node, $word),
            $node->name === 'case_expr' => ['case', 1],
            $node->name === 'explicit_row' || $node->name === 'implicit_row' => ['row', 1],
            $word === 'ARRAY' => ['array', 1],
            default => self::operand($children),
        };
    }

    /**
     * Figures an expression through what it wraps: a cast, a COLLATE clause, parentheses, or a single inner node.
     *
     * @param list<Node|Token> $children The significant children of the expression
     * @return array{?string, int}
     */
    public static function operand(array $children): array
    {
        $first = $children[0] ?? null;
        $second = $children[1] ?? null;
        $operator = $second instanceof Token ? strtoupper($second->text) : '';
        return match (true) {
            count($children) === 3 && $operator === '::' && $first instanceof Node && $children[2] instanceof Node => self::cast($first, $children[2]),
            count($children) === 3 && $operator === 'COLLATE' && $first instanceof Node => self::column($first),
            $first instanceof Token && $first->text === '(' && $second instanceof Node && count(array_filter($children, static fn ($child): bool => $child instanceof Node && Tree::hasTokens($child))) === 1 => self::column($second),
            count($children) === 1 && $first instanceof Node => self::column($first),
            default => [null, 0],
        };
    }

    /**
     * Figures a cast: its operand's name when that is a column or function name, or else its type's name.
     *
     * @return array{?string, int}
     */
    public static function cast(Node $operand, Node $type): array
    {
        $figured = self::column($operand);
        return $figured[1] > 1 ? $figured : [self::typeName($type), 1];
    }

    /**
     * Figures a function the grammar spells with keywords: CAST as a cast, TRIM as btrim, ltrim or rtrim, COLLATION FOR
     * as pg_collation_for, and any other by its leading keyword.
     *
     * @return array{?string, int}
     */
    public static function keywordFunction(Node $node, string $word): array
    {
        $words = Tree::keywords($node);
        return match ($word) {
            'CAST', 'TREAT' => self::cast(Tree::child($node, ['a_expr']) ?? $node, Tree::child($node, ['Typename']) ?? $node),
            'TRIM' => [in_array('LEADING', $words, true) ? 'ltrim' : (in_array('TRAILING', $words, true) ? 'rtrim' : 'btrim'), 2],
            'COLLATION' => ['pg_collation_for', 2],
            default => [strtolower($word), 2],
        };
    }

    /**
     * Returns the last name a type is written with, as the server keeps it: the internal name of a keyword type.
     */
    public static function typeName(Node $type): string
    {
        $generic = Tree::outer($type, ['GenericType'])[0] ?? null;
        if ($generic !== null) {
            return self::last($generic);
        }
        $words = array_values(array_filter(Tree::keywords(Tree::child($type, ['SimpleTypename']) ?? $type), static fn (string $word): bool => !in_array($word, ['(', ')', '[', ']', ','], true) && !is_numeric($word)));
        $spelling = implode(' ', $words);
        if (str_starts_with($spelling, 'FLOAT')) {
            $precision = Tree::outer($type, ['Iconst'])[0] ?? null;
            return $precision !== null && (int) Tree::text($precision) <= 24 ? 'float4' : 'float8';
        }
        return self::TYPE_NAMES[$spelling] ?? self::TYPE_NAMES[preg_replace('/^(INTERVAL|TIME|TIMESTAMP) .*$/', '$1', $spelling) ?? ''] ?? strtolower($spelling);
    }

    /**
     * Returns the last identifier of a name that is not the star of a column reference.
     */
    public static function last(Node $name): string
    {
        $identifiers = new Identifiers(Dialect::PostgreSql);
        $parts = array_values(array_filter($identifiers->parts($name), static fn (string $part): bool => !in_array($part, ['.', '*'], true)));
        return $parts[count($parts) - 1] ?? 'expr';
    }
}
