<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;

#[CoversClass(ExternalRelationKind::class)]
#[Small]
final class ExternalRelationKindTest extends TestCase
{
    public function testCasesIdentifyTheSupportedExternalRelations(): void
    {
        self::assertSame(['table', 'view'], array_column(ExternalRelationKind::cases(), 'value'));
    }
}
