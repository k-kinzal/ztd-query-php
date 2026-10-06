<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule::class)]
#[Small]
final class ManipulationMisuseRuleTest extends TestCase
{
    public function testCasesHoldTheMessagesOfTheServer(): void
    {
        self::assertSame('INSERT has more expressions than target columns', \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule::MoreExpressions->value);
    }
}
