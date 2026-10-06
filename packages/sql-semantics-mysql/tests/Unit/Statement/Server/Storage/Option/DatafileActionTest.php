<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction;

#[CoversClass(DatafileAction::class)]
#[Small]
final class DatafileActionTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['ADD', 'DROP', 'CHANGE'], array_column(DatafileAction::cases(), 'value'));
    }
}
