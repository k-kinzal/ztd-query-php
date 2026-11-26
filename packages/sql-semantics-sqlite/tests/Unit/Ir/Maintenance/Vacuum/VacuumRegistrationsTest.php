<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Vacuum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumRegistrations;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;

#[CoversClass(VacuumRegistrations::class)]
#[Small]
final class VacuumRegistrationsTest extends TestCase
{
    public function testToStringRegistersAliasLabelsInOrderFromOwnedValues(): void
    {
        $source = new Parameter('002');
        $bindings = [&$source, new Parameter('02'), new Parameter('09')];
        $registrations = new VacuumRegistrations($bindings);
        $source = new Parameter('8');

        self::assertSame('(0 IN (?002, ?02, ?09))', $registrations->toString());
    }
}
