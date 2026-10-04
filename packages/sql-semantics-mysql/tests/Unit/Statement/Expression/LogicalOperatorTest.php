<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;

#[CoversClass(LogicalOperator::class)]
#[Small]
final class LogicalOperatorTest extends TestCase
{
    public function testLevelOrdersOrBelowXorBelowAnd(): void
    {
        self::assertSame([Precedence::DISJUNCTION, Precedence::EXCLUSION, Precedence::CONJUNCTION], [LogicalOperator::Or->level(), LogicalOperator::Xor->level(), LogicalOperator::And->level()]);
    }

    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['OR', 'XOR', 'AND'], array_column(LogicalOperator::cases(), 'value'));
    }
}
