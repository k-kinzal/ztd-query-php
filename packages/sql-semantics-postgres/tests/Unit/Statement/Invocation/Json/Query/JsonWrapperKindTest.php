<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapperKind;

#[CoversClass(JsonWrapperKind::class)]
#[Small]
final class JsonWrapperKindTest extends TestCase
{
    public function testWrapsTellsWhetherItemsAreWrapped(): void
    {
        self::assertSame([false, true, true], [JsonWrapperKind::Without->wraps(), JsonWrapperKind::Unconditional->wraps(), JsonWrapperKind::Conditional->wraps()]);
    }
}
