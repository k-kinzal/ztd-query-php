<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind;

#[CoversClass(LogFileKind::class)]
#[Small]
final class LogFileKindTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['UNDOFILE', 'REDOFILE'], array_column(LogFileKind::cases(), 'value'));
    }
}
