<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange;

#[CoversClass(SridRange::class)]
#[Small]
final class SridRangeTest extends TestCase
{
    public function testMessageNamesTheColumnRestrictionRatherThanAnSrsDefinition(): void
    {
        $problem = new SridRange(new Numeral('4294967296'));
        self::assertSame("SRID value is out of range in 'SRID'", $problem->message());
        self::assertSame('4294967296', $problem->srid->text);
    }
}
