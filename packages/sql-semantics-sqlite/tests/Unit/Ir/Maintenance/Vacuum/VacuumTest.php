<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Vacuum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumDestination;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;

#[CoversClass(Vacuum::class)]
#[Small]
final class VacuumTest extends TestCase
{
    public function testProfileIdIdentifiesThePinnedSqliteProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new Vacuum())->profileId());
    }

    public function testToStringEmitsTheBoundDatabaseAndFilename(): void
    {
        $expression = new VacuumDestination(new ValueRow([new Parameter('02')]));
        $operation = new Vacuum(new Identifier('archive "slot"'), $expression);

        self::assertSame('VACUUM "main"', (new Vacuum())->toString());
        self::assertSame('VACUUM "archive ""slot""" INTO (?02)', $operation->toString());
    }
}
