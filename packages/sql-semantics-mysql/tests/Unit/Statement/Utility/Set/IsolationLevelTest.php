<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel;

#[CoversClass(IsolationLevel::class)]
#[Small]
final class IsolationLevelTest extends TestCase
{
    public function testCasesSpellTheLevels(): void
    {
        self::assertSame(['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], array_column(IsolationLevel::cases(), 'value'));
    }
}
