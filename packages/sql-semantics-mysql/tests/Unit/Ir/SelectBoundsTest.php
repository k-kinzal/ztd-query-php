<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\SelectBounds;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(SelectBounds::class)]
#[Small]
final class SelectBoundsTest extends TestCase
{
    public function testCheckAcceptsTheLastAvailableSlot(): void
    {
        $name = new Identifier('id');
        (new SelectBounds())->check(
            [new ResultColumn(new ColumnReference(0), $name)],
            new TableSource(new Identifier('main'), new Identifier('t'), [$name]),
        );

        $this->addToAssertionCount(1);
    }

    public function testCheckLabelRejectsAnotherPhysicalName(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('items'), [new Identifier('id')]);
        $result = new ResultColumn(new ColumnReference(0), new Identifier(' different'));
        $this->expectExceptionMessage('An intrinsic MySQL result name must equal its physical input field.');

        (new SelectBounds())->checkLabel($result, [$source]);
    }

    public function testCheckResultKeepsTheCountsForFollowingResults(): void
    {
        $field = new ResultColumn(new IntegerLiteral('2'), new Identifier('n'));
        $bounds = new SelectBounds();

        self::assertSame([4, 2], $bounds->checkResult($field, [], new OccurrenceScope(), 3, 2));
    }
}
