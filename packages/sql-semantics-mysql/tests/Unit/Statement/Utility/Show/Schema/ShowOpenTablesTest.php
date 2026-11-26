<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowOpenTables::class)]
#[Medium]
final class ShowOpenTablesTest extends TestCase
{
    public function testDeriveStatementResolvesTheCondition(): void
    {
        $show = (new Inspection())->inspect('SHOW OPEN TABLES WHERE In_use > 0');
        self::assertInstanceOf(ShowOpenTables::class, $show->statement);
        self::assertSame([], $show->facts->diagnostics);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Inspection())->inspect('SHOW OPEN TABLES WHERE In_use > 0');
        self::assertInstanceOf(ShowOpenTables::class, $show->statement);
        self::assertSame('Table', $show->facts->relation($show->statement)->shape->slots[1]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW OPEN TABLES WHERE In_use > 0', (new Inspection())->inspect('SHOW OPEN TABLES WHERE In_use > 0')->toString());
    }
}
