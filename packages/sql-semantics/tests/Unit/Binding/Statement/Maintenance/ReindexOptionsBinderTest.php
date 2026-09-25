<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Maintenance\ReindexOptionsBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReindexOptionsBinder::class)]
#[Medium]
final class ReindexOptionsBinderTest extends TestCase
{
    public function testBindReadsBooleanSpellingsAndTheTablespaceName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('REINDEX (CONCURRENTLY, VERBOSE off, TABLESPACE ts) TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement::class, $statement);
        self::assertTrue($statement->options->concurrently);
        self::assertFalse($statement->options->verbose);
        self::assertSame('ts', $statement->options->tablespace);
        self::assertSame('REINDEX(CONCURRENTLY, TABLESPACE "ts") TABLE "t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindCombinesTheKeywordFormWithQuotedOptionValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("REINDEX (VERBOSE 'on') INDEX CONCURRENTLY ix");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement::class, $statement);
        self::assertTrue($statement->options->concurrently);
        self::assertTrue($statement->options->verbose);
        self::assertNull($statement->options->tablespace);
        self::assertSame('REINDEX(CONCURRENTLY, VERBOSE) INDEX "ix"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindLeavesEveryOptionOffByDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('REINDEX TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement::class, $statement);
        self::assertFalse($statement->options->concurrently);
        self::assertFalse($statement->options->verbose);
        self::assertNull($statement->options->tablespace);
    }

    #[TestWith(['REINDEX (FOO) TABLE t'])]
    #[TestWith(['REINDEX (VERBOSE maybe) TABLE t'])]
    public function testBindRejectsUnknownOptionsAndValuesOutsideTheirDomain(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ReindexOption->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql);
    }

    #[TestWith(['reindex (verbose true, concurrently 1) table t', 'REINDEX(CONCURRENTLY, VERBOSE) TABLE "t"'])]
    #[TestWith(['REINDEX (VERBOSE FALSE, CONCURRENTLY 0) TABLE t', 'REINDEX TABLE "t"'])]
    #[TestWith(['REINDEX (VERBOSE on, CONCURRENTLY off) TABLE t', 'REINDEX(VERBOSE) TABLE "t"'])]
    #[TestWith(['REINDEX (tablespace x, verbose) TABLE t', 'REINDEX(VERBOSE, TABLESPACE "x") TABLE "t"'])]
    public function testBindReadsEveryBooleanSpelling(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql)));
    }
}
