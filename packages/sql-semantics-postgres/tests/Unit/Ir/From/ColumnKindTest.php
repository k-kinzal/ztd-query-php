<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;

#[CoversClass(ColumnKind::class)]
#[Small]
final class ColumnKindTest extends TestCase
{
    public function testIdentifiesTheNativeTypes(): void
    {
        self::assertSame(['int4', 'text', 'bool', 'int8'], array_column(ColumnKind::cases(), 'value'));
    }
}
