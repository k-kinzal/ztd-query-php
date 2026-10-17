<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneOperands;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TimeZoneOperands::class)]
#[Small]
final class TimeZoneOperandsTest extends TestCase
{
    public function testLiteralLeavesAColumnDependentWhileGroupingRetainsLiteralRoles(): void
    {
        $rule = new TimeZoneOperands();
        self::assertNull($rule->literal(new ColumnUse(new Name('t'))));
        self::assertFalse($rule->literal(new Grouped(new NumberLiteral('1'))));
        self::assertTrue($rule->literal(new Grouped(new TemporalLiteral(TemporalForm::Timestamp, '2024-01-01 01:02:03'))));
    }
}
