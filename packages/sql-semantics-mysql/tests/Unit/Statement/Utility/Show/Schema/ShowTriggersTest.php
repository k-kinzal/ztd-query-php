<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowTriggers::class)]
#[Medium]
final class ShowTriggersTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.6.51'))->inspect("SHOW TRIGGERS LIKE 't%'");
        self::assertInstanceOf(ShowTriggers::class, $show->statement);
        self::assertSame('Trigger', $show->field(0)->name?->value);
        self::assertFalse($show->statement->full);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Inspection('mysql-5.6.51'))->inspect("SHOW TRIGGERS LIKE 't%'");
        self::assertInstanceOf(ShowTriggers::class, $show->statement);
        self::assertSame('Event', $show->facts->relation($show->statement)->shape->slots[1]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame("SHOW TRIGGERS LIKE 't%'", (new Inspection('mysql-5.6.51'))->inspect("SHOW TRIGGERS LIKE 't%'")->toString());
    }
}
