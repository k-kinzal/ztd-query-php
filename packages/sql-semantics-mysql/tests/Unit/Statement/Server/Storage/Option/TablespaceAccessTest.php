<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess;

#[CoversClass(TablespaceAccess::class)]
#[Small]
final class TablespaceAccessTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['READ_ONLY', 'READ_WRITE', 'NOT ACCESSIBLE'], array_column(TablespaceAccess::cases(), 'value'));
    }
}
