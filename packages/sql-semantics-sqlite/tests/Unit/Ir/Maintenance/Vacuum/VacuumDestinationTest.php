<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Vacuum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumDestination;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;

#[CoversClass(VacuumDestination::class)]
#[Small]
final class VacuumDestinationTest extends TestCase
{
    public function testToStringWritesOneTypedFilenameExpression(): void
    {
        $operand = new ValueRow([new TextLiteral("it's.sqlite")]);

        self::assertSame("('it''s.sqlite')", (new VacuumDestination($operand))->toString());
    }
}
