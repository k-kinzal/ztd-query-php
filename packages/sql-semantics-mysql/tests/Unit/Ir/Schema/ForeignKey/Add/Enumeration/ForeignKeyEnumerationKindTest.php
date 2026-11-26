<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationKind;

#[CoversClass(ForeignKeyEnumerationKind::class)]
final class ForeignKeyEnumerationKindTest extends TestCase
{
    public function testPhysicalRolesDistinguishOrdinalAndMask(): void
    {
        self::assertSame(['ENUM', 'SET'], array_column(ForeignKeyEnumerationKind::cases(), 'value'));
    }
}
