<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DefinitionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(DefinitionLowering::class)]
#[Small]
final class DefinitionLoweringTest extends TestCase
{
    public function testLowerSelectsEachNativeDefinitionRole(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id INTEGER)', 'CREATE TABLE audit (id INTEGER)']);

        self::assertInstanceOf(CreateTable::class, $dialect->analyze('CREATE TABLE derived (id INTEGER)', $context));
        self::assertInstanceOf(CreateTableAs::class, $dialect->analyze('CREATE TABLE derived AS SELECT 17 AS id', $context));
        self::assertInstanceOf(CreateIndex::class, $dialect->analyze('CREATE INDEX lookup_id ON t(id)', $context));
        self::assertInstanceOf(CreateView::class, $dialect->analyze('CREATE VIEW derived AS SELECT id FROM t', $context));
        self::assertInstanceOf(CreateTrigger::class, $dialect->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO audit VALUES (NEW.id); END', $context));
    }
}
