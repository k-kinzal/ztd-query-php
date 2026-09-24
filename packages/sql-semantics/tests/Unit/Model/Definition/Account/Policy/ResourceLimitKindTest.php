<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;

#[CoversClass(ResourceLimitKind::class)]
#[Medium]
final class ResourceLimitKindTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['MAX_QUERIES_PER_HOUR', 'MAX_UPDATES_PER_HOUR', 'MAX_CONNECTIONS_PER_HOUR', 'MAX_USER_CONNECTIONS'], array_column(ResourceLimitKind::cases(), 'value'));
    }
}
