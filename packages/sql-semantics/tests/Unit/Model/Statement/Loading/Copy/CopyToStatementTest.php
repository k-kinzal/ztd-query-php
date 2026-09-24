<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptions;
use SqlSemantics\Model\Statement\Loading\Copy\CopyToStatement;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyClient;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyToStatement::class)]
#[Medium]
final class CopyToStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t (a) TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        self::assertSame(['a'], $statement->withOrigin($statement->origin)->columns);
        self::assertSame(StatementKind::Copy, $statement->kind);
    }

    public function testWithTableWritesAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)'));
        $statement = $binder->bind('COPY t TO STDOUT');
        $other = $binder->bind('COPY u TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        self::assertInstanceOf(CopyToStatement::class, $other);
        self::assertSame('COPY "public"."u" TO STDOUT', $statement->withTable($other->table)->toString());
    }

    public function testWithColumnsReplacesTheWrittenColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t (a) TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        self::assertSame('COPY "public"."t" TO STDOUT', $statement->withColumns([])->toString());
    }

    public function testWithDestinationWritesToTheClient(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("COPY t TO '/tmp/t'");
        self::assertInstanceOf(CopyToStatement::class, $statement);
        self::assertSame('COPY "public"."t" TO STDOUT', $statement->withDestination(new CopyClient())->toString());
    }

    public function testWithOptionsRejectsFreezeWhenWriting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t TO STDOUT');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        self::assertSame('COPY "public"."t" TO STDOUT WITH (DELIMITER \'|\')', $statement->withOptions(new CopyOptions(delimiter: '|'))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(new CopyOptions(freeze: true));
    }
}
