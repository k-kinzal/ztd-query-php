<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Visibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SecondaryIndexKind;

#[CoversClass(SecondaryIndexKind::class)]
#[Small]
final class SecondaryIndexKindTest extends TestCase
{
    public function testPhysicalKindsAreDistinct(): void
    {
        self::assertSame(['ordinary','unique','fulltext','spatial'], array_column(SecondaryIndexKind::cases(), 'value'));
    }
}
