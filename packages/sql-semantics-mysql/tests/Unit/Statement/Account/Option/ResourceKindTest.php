<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceKind;

#[CoversClass(ResourceKind::class)]
#[Small]
final class ResourceKindTest extends TestCase
{
    public function testCasesSpellTheLimits(): void
    {
        self::assertSame(['MAX_QUERIES_PER_HOUR', 'MAX_UPDATES_PER_HOUR', 'MAX_CONNECTIONS_PER_HOUR', 'MAX_USER_CONNECTIONS'], array_column(ResourceKind::cases(), 'value'));
    }
}
