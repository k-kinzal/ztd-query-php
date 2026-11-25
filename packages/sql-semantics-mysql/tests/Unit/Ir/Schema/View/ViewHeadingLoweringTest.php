<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewHeadingLowering;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ViewHeadingLowering::class)]
#[Medium]
final class ViewHeadingLoweringTest extends TestCase
{
    public function testLowerKeepsTheExplicitCatalogHeading(): void
    {
        $inspection = (new Inspection())->inspect('CREATE VIEW app.constant_view (catalog_value) AS SELECT 42 AS body_value');
        self::assertInstanceOf(CreateView::class, $inspection->statement);
        $query = new Select([new ResultColumn(new IntegerLiteral('42'), new Identifier('body_value'))]);
        $columns = (new ViewHeadingLowering())->lower($inspection->binding(), $inspection->statement->definition, $query);

        self::assertSame('catalog_value', $columns[0]->name->value);
    }
}
