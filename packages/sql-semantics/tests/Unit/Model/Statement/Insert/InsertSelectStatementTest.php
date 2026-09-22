<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Insert\InsertSelectStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertSelectStatement::class)]
final class InsertSelectStatementTest extends TestCase
{
    public function testKeepsTheQuerySeparateFromReturning(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) SELECT 1 RETURNING id');
        self::assertInstanceOf(InsertSelectStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $statement->query);
        self::assertSame('1', $statement->query->outputs[0]->expression->spelling());
        self::assertSame('id', $statement->outputs[0]->name);
        self::assertFalse(property_exists($statement, 'rows'));
    }

    public function testWithQueryPreservesTheOriginalAndItsDestination(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('INSERT INTO t(id) SELECT 1');
        self::assertInstanceOf(InsertSelectStatement::class, $statement);
        $query = $binder->bind('SELECT 2');
        self::assertInstanceOf(BoundSelect::class, $query);


        $changed = $statement->withQuery($query);
        self::assertSame('1', $statement->query->resultColumns()[0]->expression->spelling());
        self::assertSame('2', $changed->query->resultColumns()[0]->expression->spelling());
        self::assertSame('t', $changed->insertion->target->declaration->name);
        self::assertSame('INSERT INTO "public"."t"("id") SELECT 2', $changed->toString());
    }
}
