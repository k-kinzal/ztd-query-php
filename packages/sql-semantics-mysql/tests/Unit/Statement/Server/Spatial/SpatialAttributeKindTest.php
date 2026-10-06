<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;

#[CoversClass(SpatialAttributeKind::class)]
#[Small]
final class SpatialAttributeKindTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['NAME', 'DEFINITION', 'ORGANIZATION', 'DESCRIPTION'], array_column(SpatialAttributeKind::cases(), 'value'));
    }
}
