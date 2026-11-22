<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\DefaultRow;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflictHandler;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertLowering;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertLowering
 */
final class InsertLoweringTest extends TestCase
{
    public function testLowerResolvesTheWriteToTheDeclaredSlots(): void
    {
        $semantics = new Semantics();
        $schema = (new Inspection())->inspect('CREATE TABLE records (Item INTEGER, Cost INTEGER)');
        $sql = 'INSERT OR IGNORE INTO records (COST, item) VALUES (2, 3)';
        $operation = (new Inspection())->inspect($sql, [$schema]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        $insert = (new InsertLowering())->lower($operation->binding(), $operation->statement);

        self::assertSame(InsertConflict::Ignore, $insert->conflict);
        self::assertSame('Cost', $insert->target->columns[0]->value);
        self::assertSame('Item', $insert->target->columns[1]->value);
        $published = $semantics->analyze($sql, $semantics->context(['CREATE TABLE records (Item INTEGER, Cost INTEGER)']));
        self::assertInstanceOf(Insert::class, $published);
        self::assertEquals($insert, $published);
    }

    public function testLowerUsesTheSameConflictRoleForReplaceAndInsertOrReplace(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE entries (id INTEGER PRIMARY KEY)']);
        $replace = $semantics->analyze('REPLACE INTO entries (id) VALUES (1)', $context);
        $insert = $semantics->analyze('INSERT OR REPLACE INTO entries (id) VALUES (1)', $context);

        self::assertInstanceOf(Insert::class, $replace);
        self::assertSame(InsertConflict::Replace, $replace->conflict);
        self::assertEquals($insert, $replace);
        self::assertStringStartsWith('INSERT OR REPLACE INTO ', $replace->toString());
    }

    public function testLowerRetainsTheCheckedSourceProjection(): void
    {
        $inspection = new Inspection();
        $target = $inspection->inspect('CREATE TABLE main.received (id INTEGER, payload BLOB)');
        $scan = $inspection->inspect('CREATE TABLE main.source_rows (id INTEGER, payload BLOB)');
        $operation = $inspection->inspect(
            'INSERT INTO main.received (payload, id) SELECT payload AS value, id AS key FROM main.source_rows',
            [$target, $scan],
        );
        self::assertInstanceOf(InsertSelect::class, $operation->statement);
        $statement = (new InsertLowering())->lower($operation->binding(), $operation->statement);
        self::assertInstanceOf(Select::class, $statement->input);

        self::assertSame('payload', $statement->target->columns[0]->value);
        self::assertSame('value', $statement->input->columns[0]->name->value);
    }

    public function testHandlerKeepsTheTargetlessActionIndependentOfTheStatementPolicy(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE entries (id INTEGER PRIMARY KEY)']);
        $sql = 'INSERT OR REPLACE INTO entries (id) VALUES (1) ON CONFLICT DO NOTHING RETURNING id';
        $operation = (new Inspection())->inspect($sql, $context);
        self::assertInstanceOf(InsertRows::class, $operation->statement);

        $lowering = new InsertLowering();
        self::assertSame(InsertConflictHandler::DoNothing, $lowering->handler($operation->statement));
        $insert = $lowering->lower($operation->binding(), $operation->statement);
        self::assertSame(InsertConflict::Replace, $insert->conflict);
        self::assertStringContainsString('ON CONFLICT DO NOTHING RETURNING', $insert->toString());
    }

    public function testInputIdentifiesTheDefaultRowRole(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE entries (id INTEGER DEFAULT 7)']);
        $operation = (new Inspection())->inspect('INSERT INTO entries DEFAULT VALUES', $context);
        self::assertInstanceOf(InsertDefaults::class, $operation->statement);

        $input = (new InsertLowering())->input($operation->binding(), $operation->statement);

        self::assertInstanceOf(DefaultRow::class, $input);
    }
}
