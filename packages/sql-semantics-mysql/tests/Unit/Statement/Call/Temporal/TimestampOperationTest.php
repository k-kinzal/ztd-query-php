<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation;

#[CoversClass(TimestampOperation::class)]
#[Small]
final class TimestampOperationTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['TIMESTAMPADD', 'TIMESTAMPDIFF'], array_column(TimestampOperation::cases(), 'value'));
    }
}
