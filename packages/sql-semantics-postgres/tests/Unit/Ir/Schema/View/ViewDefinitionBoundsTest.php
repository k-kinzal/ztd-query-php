<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewCheckOption;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewDefinitionBounds;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName;

#[CoversClass(ViewDefinitionBounds::class)]
#[Small]
final class ViewDefinitionBoundsTest extends TestCase
{
    public function testWidthUsesAllColumnsOfTheCheckedConstantTable(): void
    {
        $name = new ViewName(new Identifier('public'), new Identifier('queries'));
        $row = new ValuesRow([new ValuesCell(new BooleanLiteral(false)), new ValuesCell(new NullLiteral())]);

        self::assertSame(2, (new ViewDefinitionBounds())->width(new ValuesQuery([$row]), $name, ViewCheckOption::None));
    }
}
