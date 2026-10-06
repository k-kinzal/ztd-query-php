<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;

#[CoversClass(WriteMisuse::class)]
#[Small]
final class WriteMisuseTest extends TestCase
{
    public function testMessageAnswersTheRuleMessage(): void
    {
        self::assertSame('Incorrect usage of UPDATE and ORDER BY', (new WriteMisuse(WriteRule::OrderedMultipleUpdate))->message());
    }
}
