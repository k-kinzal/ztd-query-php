<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFormat;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptions;
use SqlSemantics\Model\Statement\Loading\Copy\CopyQueryStatement;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyClient;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyQueryStatement::class)]
#[Medium]
final class CopyQueryStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('COPY (SELECT 1 AS x) TO STDOUT');
        self::assertInstanceOf(CopyQueryStatement::class, $statement);
        self::assertSame('x', $statement->withOrigin($statement->origin)->query->resultColumns()[0]->name);
        self::assertSame(StatementKind::Copy, $statement->kind);
    }

    public function testWithQueryCopiesAnotherQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('COPY (SELECT 1 AS x) TO STDOUT');
        $query = $binder->bind('VALUES (2)');
        self::assertInstanceOf(CopyQueryStatement::class, $statement);
        self::assertInstanceOf(ResultStatement::class, $query);
        self::assertSame('COPY(VALUES (2)) TO STDOUT', $statement->withQuery($query)->toString());
    }

    public function testWithDestinationWritesToTheClient(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COPY (SELECT 1 AS x) TO PROGRAM 'cat'");
        self::assertInstanceOf(CopyQueryStatement::class, $statement);
        self::assertSame('COPY(SELECT 1 AS "x") TO STDOUT', $statement->withDestination(new CopyClient())->toString());
    }

    public function testWithOptionsReplacesTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('COPY (SELECT 1 AS x) TO STDOUT');
        self::assertInstanceOf(CopyQueryStatement::class, $statement);
        self::assertSame('COPY(SELECT 1 AS "x") TO STDOUT WITH (FORMAT \'binary\')', $statement->withOptions(new CopyOptions(CopyFormat::Binary))->toString());
    }

    public function testRejectsAQueryWithoutColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('COPY (SELECT 1 AS x) TO STDOUT');
        $delete = $binder->bind('DELETE FROM t');
        self::assertInstanceOf(CopyQueryStatement::class, $statement);
        self::assertInstanceOf(ResultStatement::class, $delete);
        $this->expectException(InvalidStructure::class);
        $statement->withQuery($delete);
    }
}
