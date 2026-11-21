<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
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
}
