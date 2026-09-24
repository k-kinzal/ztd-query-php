<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Maintenance\ReindexBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReindexBinder::class)]
#[Medium]
final class ReindexBinderTest extends TestCase
{
    public function testBindSeparatesSqliteAllFromNamedRebuilds(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexAllStatement::class, $binder->bind('REINDEX'));
        $named = $binder->bind('REINDEX main.t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexNamedStatement::class, $named);
        self::assertSame(['main', 't'], $named->target->parts);
        self::assertSame('REINDEX "main"."t"', $named->toString());
    }

    public function testBindReadsPostgreSqlObjectTargetsWithOptions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $table = $binder->bind('REINDEX (CONCURRENTLY, VERBOSE off, TABLESPACE ts) TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement::class, $table);
        self::assertSame(\SqlSemantics\Model\Maintenance\ReindexObjectKind::Table, $table->targetKind);
        self::assertSame(['t'], $table->target->parts);
        self::assertTrue($table->options->concurrently);
        self::assertFalse($table->options->verbose);
        self::assertSame('ts', $table->options->tablespace);
        $schema = $binder->bind('REINDEX SCHEMA s');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement::class, $schema);
        self::assertSame(\SqlSemantics\Model\Maintenance\ReindexObjectKind::Schema, $schema->targetKind);
        self::assertSame('REINDEX SCHEMA "s"', $schema->toString());
    }

    public function testBindReadsDatabaseSelections(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REINDEX DATABASE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\ReindexDatabaseStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Maintenance\DatabaseIndexScope::UserTables, $statement->selection);
        self::assertNull($statement->database);
        self::assertFalse($statement->options->concurrently);
        self::assertSame('REINDEX DATABASE', $statement->toString());
    }

    public function testBindRejectsConcurrentSystemRebuilds(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ConcurrentSystemReindex->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REINDEX SYSTEM CONCURRENTLY db');
    }
}
