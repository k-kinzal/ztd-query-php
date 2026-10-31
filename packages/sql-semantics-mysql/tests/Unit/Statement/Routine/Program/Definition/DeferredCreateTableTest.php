<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(DeferredCreateTable::class)]
#[Medium]
final class DeferredCreateTableTest extends TestCase
{
    public function testDeriveProgramRetainsADefinitionTheRoutineRefusesWhenItRuns(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, a INT, PRIMARY KEY (b))');

        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredCreateTable::class, $operation->body);
        self::assertCount(3, $operation->body->elements);
        self::assertSame([], $operation->diagnostics());
    }

    public function testDeriveProgramKeepsTheReadingTimeChecksOfTheBody(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, a INT, b DECIMAL(70))');

        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredCreateTable::class, $operation->body);
        self::assertNotSame([], $operation->diagnostics());
    }

    public function testRenderWritesTheStoredDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE IF NOT EXISTS t (a INT, PRIMARY KEY (a), PRIMARY KEY (a))');

        self::assertSame('CREATE PROCEDURE p() CREATE TABLE IF NOT EXISTS t (a INT, PRIMARY KEY (a), PRIMARY KEY (a))', $operation->toString());
    }

    public function testAnOrdinaryBodyDefinitionStaysExecutable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, PRIMARY KEY (a))');

        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(CreateTable::class, $operation->body);
    }

    public function testTemporaryTellsWhetherTheKeywordIsWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TEMPORARY TABLE t (a INT, a INT)');

        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredCreateTable::class, $operation->body);
        self::assertTrue($operation->body->temporary());
    }
}
