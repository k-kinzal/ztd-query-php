<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewName;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewQueryBounds;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(ViewQueryBounds::class)]
#[Small]
final class ViewQueryBoundsTest extends TestCase
{
    public function testCheckAcceptsAnIndependentLiteral(): void
    {
        $name = new ViewName(new Identifier('app'), new Identifier('answer'));
        $query = new Select([new ResultColumn(new IntegerLiteral('42'), new Identifier('answer'))]);

        (new ViewQueryBounds())->check($query, $name);
        $this->addToAssertionCount(1);
    }

    public function testHasWritableFieldDistinguishesAFieldFromAConstant(): void
    {
        $id = new Identifier('id');
        $source = new TableSource(new Identifier('app'), new Identifier('events'), [$id]);
        $constant = new ResultColumn(new IntegerLiteral('42'), new Identifier('value'));
        $field = new ResultColumn(new ColumnReference(0), $id);
        $bounds = new ViewQueryBounds();

        self::assertFalse($bounds->hasWritableField(new Select([$constant], $source)));
        self::assertTrue($bounds->hasWritableField(new Select([$constant, $field], $source)));
    }
}
