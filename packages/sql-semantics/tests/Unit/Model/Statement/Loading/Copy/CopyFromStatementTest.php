<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFormat;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFromStatement;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptions;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyClient;
use SqlSemantics\Model\Statement\Loading\Copy\EveryColumn;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyFromStatement::class)]
#[Medium]
final class CopyFromStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("COPY t (a) FROM '/tmp/t' WHERE a > 0");
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame(['a'], $copy->columns);
        self::assertNotNull($copy->where);
        self::assertSame(StatementKind::Copy, $copy->kind);
    }

    public function testWithTableLoadsAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)'));
        $statement = $binder->bind('COPY t FROM STDIN');
        $other = $binder->bind('COPY u FROM STDIN');
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        self::assertInstanceOf(CopyFromStatement::class, $other);
        self::assertSame('COPY "public"."u" FROM STDIN', $statement->withTable($other->table)->toString());
    }

    public function testWithColumnsReplacesTheLoadedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('COPY t FROM STDIN');
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        self::assertSame('COPY "public"."t"("b") FROM STDIN', $statement->withColumns(['b'])->toString());
        self::assertSame([], $statement->columns);
    }

    public function testWithInputReadsFromTheClient(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("COPY t FROM PROGRAM 'cat x'");
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        self::assertSame('COPY "public"."t" FROM STDIN', $statement->withInput(new CopyClient())->toString());
    }

    public function testWithOptionsReplacesTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t FROM STDIN');
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        self::assertSame('COPY "public"."t" FROM STDIN WITH (FORMAT \'csv\', FREEZE)', $statement->withOptions(new CopyOptions(CopyFormat::Csv, true))->toString());
    }

    public function testWithWhereReplacesTheFilter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t FROM STDIN WHERE a > 1');
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        self::assertSame('COPY "public"."t" FROM STDIN', $statement->withWhere(null)->toString());
    }

    public function testRejectsForceQuoteWhenReading(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t FROM STDIN');
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(new CopyOptions(CopyFormat::Csv, forceQuote: new EveryColumn()));
    }
}
