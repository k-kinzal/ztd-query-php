<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;

#[CoversClass(WriteRule::class)]
#[Small]
final class WriteRuleTest extends TestCase
{
    public function testCasesHoldServerMessages(): void
    {
        self::assertSame('Incorrect usage of UPDATE and LIMIT', WriteRule::LimitedMultipleUpdate->value);
    }
}
