<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\DefinitionCommands;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\Explain;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateVirtualTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(DefinitionCommands::class)]
#[Medium]
final class DefinitionCommandsTest extends TestCase
{
    public function testCommandLowersATransactionCommand(): void
    {
        self::assertInstanceOf(Begin::class, (new Inspection())->inspect('BEGIN')->statement);
    }

    public function testCommandLowersACommandOfEveryFamily(): void
    {
        $semantics = new Inspection();

        self::assertInstanceOf(Pragma::class, $semantics->inspect('PRAGMA a')->statement);
        self::assertInstanceOf(Detach::class, $semantics->inspect('DETACH a')->statement);
        self::assertInstanceOf(CreateTable::class, $semantics->inspect('CREATE TABLE t (a)')->statement);
        self::assertInstanceOf(Drop::class, $semantics->inspect('DROP TABLE t')->statement);
        self::assertInstanceOf(CreateVirtualTable::class, $semantics->inspect('CREATE VIRTUAL TABLE t USING m')->statement);
    }

    public function testExplainedWrapsTheCommandInTheWrittenRequest(): void
    {
        $semantics = new Inspection();
        $program = $semantics->inspect('EXPLAIN BEGIN')->statement;
        $plan = $semantics->inspect('EXPLAIN QUERY PLAN BEGIN')->statement;

        self::assertInstanceOf(Explain::class, $program);
        self::assertInstanceOf(Explain::class, $plan);
        self::assertSame(ExplainMode::Program, $program->mode);
        self::assertSame(ExplainMode::QueryPlan, $plan->mode);
        self::assertInstanceOf(Begin::class, $plan->statement);
    }
}
