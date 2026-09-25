<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Schema\Constraint\PostgreSqlIndexNames;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlIndexNames::class)]
#[Medium]
final class PostgreSqlIndexNamesTest extends TestCase
{
    public function testAssignNamesUnnamedIndexesAsTheServerDoes(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build(
            'CREATE TABLE t (a int, b int, c text)',
            'CREATE INDEX ON t (a); CREATE INDEX ON t (a); CREATE INDEX ON t (a, b); CREATE INDEX ON t ((a + 1)); CREATE INDEX ON t ((a + 1), b)',
            'CREATE INDEX ON t (lower(c)); CREATE INDEX ON t (b) INCLUDE (a); CREATE UNIQUE INDEX ON t (a); CREATE INDEX ON t (a) WHERE b > 0',
            'CREATE TABLE t_a_idx4 (x int); CREATE INDEX ON t (a); CREATE INDEX ON t ((a + 1), (b + 1)); CREATE INDEX named ON t (a)',
        )->tables[0];
        self::assertSame(['t_a_idx', 't_a_idx1', 't_a_b_idx', 't_expr_idx', 't_expr_b_idx', 't_lower_idx', 't_b_a_idx', 't_a_idx2', 't_a_idx3', 't_a_idx5', 't_expr_expr1_idx', 'named'], array_map(static fn (IndexDefinition $index): ?string => $index->name, $table->indexes));
    }

    public function testAssignLetsDropIndexFindTheIndex(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build('CREATE TABLE app.t (a int)', 'CREATE INDEX ON app.t (a)', 'CREATE INDEX ON app.t ((a * 2))', 'DROP INDEX app.t_a_idx');
        self::assertSame(['t_expr_idx'], array_map(static fn (IndexDefinition $index): ?string => $index->name, $schema->tables[0]->indexes));
    }

    public function testRelationsListsTablesIndexesAndKeysOfOneNamespace(): void
    {
        $tables = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int PRIMARY KEY, b int UNIQUE CHECK (b > 0))', 'CREATE INDEX i ON t (b)', 'CREATE TABLE app.u (a int)')->tables;
        self::assertSame(['t', 'i', 't_pkey', 't_b_key'], PostgreSqlIndexNames::relations($tables, 'public'));
        self::assertSame(['u'], PostgreSqlIndexNames::relations($tables, 'app'));
    }

    #[TestWith(['a', 'a'])]
    #[TestWith(['(a)', 'a'])]
    #[TestWith(['t.a', 'a'])]
    #[TestWith(['a + 1', 'expr'])]
    #[TestWith(['lower(c)', 'lower'])]
    #[TestWith(['pg_catalog.lower(c)', 'lower'])]
    #[TestWith(['a::text', 'a'])]
    #[TestWith(['(a + 1)::text', 'text'])]
    #[TestWith(['1::double precision', 'float8'])]
    #[TestWith(['CAST(a AS text)', 'a'])]
    #[TestWith(['c COLLATE "C"', 'c'])]
    #[TestWith(['CASE WHEN a > 0 THEN 1 END', 'case'])]
    #[TestWith(['coalesce(a, b)', 'coalesce'])]
    #[TestWith(['trim(both from c)', 'btrim'])]
    #[TestWith(['trim(leading from c)', 'ltrim'])]
    #[TestWith(['current_date', 'current_date'])]
    #[TestWith(['ARRAY[a]', 'array'])]
    #[TestWith(['ROW(a, b)', 'row'])]
    public function testFigureNamesAnExpressionAsTheServerDoes(string $expression, string $expected): void
    {
        self::assertSame($expected, PostgreSqlIndexNames::figure(Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('SELECT ' . $expression), ['a_expr'])[0]));
    }

    public function testColumnGivesAFallbackNameAWeakerStrength(): void
    {
        $parse = static fn (string $expression): Node => Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('SELECT ' . $expression), ['a_expr'])[0];
        self::assertSame([['a', 2], ['case', 1], [null, 0]], [PostgreSqlIndexNames::column($parse('a')), PostgreSqlIndexNames::column($parse('CASE WHEN true THEN 1 END')), PostgreSqlIndexNames::column($parse('1 + 1'))]);
    }

    public function testCastPrefersAStrongOperandName(): void
    {
        $casts = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('SELECT a::int, 1::int'), ['a_expr']);
        $operands = array_map(static fn (Node $cast): ?Node => Tree::child($cast, ['a_expr']), $casts);
        $types = array_map(static fn (Node $cast): ?Node => Tree::child($cast, ['Typename']), $casts);
        self::assertInstanceOf(Node::class, $operands[0]);
        self::assertInstanceOf(Node::class, $types[0]);
        self::assertInstanceOf(Node::class, $operands[1]);
        self::assertInstanceOf(Node::class, $types[1]);
        self::assertSame([['a', 2], ['int4', 1]], [PostgreSqlIndexNames::cast($operands[0], $types[0]), PostgreSqlIndexNames::cast($operands[1], $types[1])]);
    }

    public function testOperandFiguresThroughParenthesesAndCollations(): void
    {
        $expressions = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('SELECT (a), b COLLATE "C", 1 + 2'), ['a_expr']);
        self::assertSame([['a', 2], ['b', 2]], [PostgreSqlIndexNames::column($expressions[0]), PostgreSqlIndexNames::column(array_values(array_filter($expressions, static fn (Node $node): bool => str_contains(Tree::text($node), 'COLLATE')))[0])]);
        self::assertSame([null, 0], PostgreSqlIndexNames::operand([]));
    }

    public function testKeywordFunctionNamesKeywordSpelledFunctions(): void
    {
        $functions = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("SELECT trim(trailing from 'x'), collation for ('x'), current_user"), ['func_expr_common_subexpr']);
        self::assertSame([['rtrim', 2], ['pg_collation_for', 2], ['current_user', 2]], [PostgreSqlIndexNames::keywordFunction($functions[0], 'TRIM'), PostgreSqlIndexNames::keywordFunction($functions[1], 'COLLATION'), PostgreSqlIndexNames::keywordFunction($functions[2], 'CURRENT_USER')]);
    }

    #[TestWith(['int', 'int4'])]
    #[TestWith(['bigint', 'int8'])]
    #[TestWith(['float(3)', 'float4'])]
    #[TestWith(['float(40)', 'float8'])]
    #[TestWith(['character varying(3)', 'varchar'])]
    #[TestWith(['timestamp(3) with time zone', 'timestamptz'])]
    #[TestWith(['interval day', 'interval'])]
    #[TestWith(['text', 'text'])]
    #[TestWith(['pg_catalog.int4', 'int4'])]
    #[TestWith(['numeric(5, 2)', 'numeric'])]
    public function testTypeNameGivesTheInternalName(string $type, string $expected): void
    {
        self::assertSame($expected, PostgreSqlIndexNames::typeName(Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('SELECT 1::' . $type), ['Typename'])[0]));
    }

    public function testLastSkipsTheStarOfAColumnReference(): void
    {
        $references = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('SELECT s.t.a, t.*'), ['columnref']);
        self::assertSame(['a', 't'], [PostgreSqlIndexNames::last($references[0]), PostgreSqlIndexNames::last($references[1])]);
    }
}
