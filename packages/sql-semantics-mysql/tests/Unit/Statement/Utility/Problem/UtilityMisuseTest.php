<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;

#[CoversClass(UtilityMisuse::class)]
#[Small]
final class UtilityMisuseTest extends TestCase
{
    public function testMessageAnswersTheRuleMessage(): void
    {
        self::assertSame('Unknown EXPLAIN format name', (new UtilityMisuse(UtilityRule::UnknownExplainFormat))->message());
    }
}
