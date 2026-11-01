<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RefusedFilter::class)]
#[Small]
final class RefusedFilterTest extends TestCase
{
    public function testMessageRetainsTheInspectionAndItsNonScalarOperand(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $problem = new RefusedFilter(new ShowTableStatus(new Name('d')), new RefusedScalar($row));
        self::assertSame('d', $problem->header->database?->value);
        self::assertSame($row, $problem->condition->operand);
        self::assertSame((new RefusedScalar($row))->message(), $problem->message());
    }
}
