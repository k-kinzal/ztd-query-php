<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Conditional\MembershipBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(MembershipBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MembershipBinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT ROW(1,2) IN (ROW(3))'])]
    #[TestWith([Dialect::MySql, 'SELECT (1,2) IN (3)'])]
    #[TestWith([Dialect::Sqlite, 'SELECT (1,2) IN (3)'])]
    public function testBindDiagnosesIncompatibleRowWidths(Dialect $dialect, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('Compared row operands must have equal widths.');
        (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql, strict: false);
    }

    #[TestWith([Dialect::PostgreSql, 'SELECT 1 IN (1, NULL)', Nullability::MaybeNull])]
    #[TestWith([Dialect::MySql, 'SELECT 1 IN (1, NULL)', Nullability::MaybeNull])]
    #[TestWith([Dialect::Sqlite, 'SELECT 1 IN (1, NULL)', Nullability::MaybeNull])]
    #[TestWith([Dialect::Sqlite, 'SELECT NULL IN ()', Nullability::NotNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT NULL IN (1,2)', Nullability::AlwaysNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 IN (NULL,NULL)', Nullability::AlwaysNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT ROW(1,NULL) IN (ROW(1,2))', Nullability::MaybeNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 IN (2,3)', Nullability::NotNull])]
    public function testNullabilityDescribesPossibleMatchesWithoutEvaluatingThem(Dialect $dialect, string $sql, Nullability $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, $statement->outputs[0]->expression->nullability);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindDerivesMembershipNullFacts')]
    public function testBindDerivesMembershipNullFacts(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, $statement->outputs[0]->expression::class . ' ' . $statement->outputs[0]->expression->nullability->name . ' ' . count($statement->outputs[0]->expression->nullExtendedBy));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindDerivesMembershipNullFacts(): iterable
    {
        return [
            'SELECT i IN (1, 2) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT i IN (1, 2) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList NotNull 0'],
            'SELECT i IN (n) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT i IN (n) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList MaybeNull 0'],
            'SELECT NULL IN (1) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT NULL IN (1) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList AlwaysNull 0'],
            'SELECT i IN (NULL, NULL) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT i IN (NULL, NULL) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList AlwaysNull 0'],
            'SELECT i NOT IN (1, NULL) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT i NOT IN (1, NULL) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList MaybeNull 0'],
            'SELECT u.b IN (1) FROM t LEFT JOIN u ON true (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT u.b IN (1) FROM t LEFT JOIN u ON true', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList MaybeNull 1'],
            'SELECT 1 IN (u.b) FROM t LEFT JOIN u ON true (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT 1 IN (u.b) FROM t LEFT JOIN u ON true', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList MaybeNull 1'],
            'SELECT i IN ((SELECT 1)) FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT i IN ((SELECT 1)) FROM t', 'SqlSemantics\\Model\\Scalar\\Query\\InSubquery MaybeNull 0'],
            'SELECT i IN (1, 2) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT i IN (1, 2) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList NotNull 0'],
            'SELECT (i, n) IN ((1, 2), (3, 4)) FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT (i, n) IN ((1, 2), (3, 4)) FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList MaybeNull 0'],
            'SELECT i IN () FROM t (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (i INTEGER NOT NULL, n INTEGER, s TEXT)', 'CREATE TABLE u (b INTEGER NOT NULL)'], 'SELECT i IN () FROM t', 'SqlSemantics\\Model\\Scalar\\Conditional\\InList NotNull 0'],
        ];
    }

    public function testBindRejectsCandidatesWithoutACommonType(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (i INTEGER NOT NULL, s TEXT)'));
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage('Cannot establish a common type for: integer, text');
        $binder->bind('SELECT i IN (s) FROM t');
    }
}
