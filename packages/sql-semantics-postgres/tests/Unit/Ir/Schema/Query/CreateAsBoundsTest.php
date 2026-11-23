<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateAsBounds;

#[CoversClass(CreateAsBounds::class)]
#[Small]
final class CreateAsBoundsTest extends TestCase
{
    public function testWidthReadsTheCheckedConstantTableHeading(): void
    {
        $body = new ValuesQuery([new ValuesRow([new ValuesCell(new IntegerLiteral('5')), new ValuesCell(new IntegerLiteral('6'))])]);

        self::assertSame(2, (new CreateAsBounds())->width($body));
    }
}
