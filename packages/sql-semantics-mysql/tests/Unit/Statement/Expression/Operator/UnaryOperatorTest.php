<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;

#[CoversClass(UnaryOperator::class)]
#[Small]
final class UnaryOperatorTest extends TestCase
{
    public function testCasesHoldTheWrittenOperators(): void
    {
        self::assertSame(['+', '-', '~', '!'], array_column(UnaryOperator::cases(), 'value'));
    }
}
