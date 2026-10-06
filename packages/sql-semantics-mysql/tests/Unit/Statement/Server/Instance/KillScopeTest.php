<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope;

#[CoversClass(KillScope::class)]
#[Small]
final class KillScopeTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['CONNECTION', 'QUERY'], array_column(KillScope::cases(), 'value'));
    }
}
