<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind;

#[CoversClass(ForeignKeyRemovalKind::class)]
#[Small]
final class ForeignKeyRemovalKindTest extends TestCase
{
    public function testNativeActionKindsAreDistinct(): void
    {
        self::assertSame(['constraint', 'ignored-on-temporary'], array_column(ForeignKeyRemovalKind::cases(), 'value'));
    }
}
