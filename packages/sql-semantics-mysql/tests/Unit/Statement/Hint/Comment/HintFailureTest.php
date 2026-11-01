<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Comment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure;

#[CoversClass(HintFailure::class)]
#[Small]
final class HintFailureTest extends TestCase
{
    public function testCasesHoldTheTextsOfTheServer(): void
    {
        self::assertSame(['Optimizer hint syntax error', 'Unsupported MAX_EXECUTION_TIME', 'A size parameter was incorrectly specified, either number or on the form 10M'], array_column(HintFailure::cases(), 'value'));
    }
}
