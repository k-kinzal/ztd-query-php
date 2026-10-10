<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;

#[CoversClass(InvalidPartitionExpression::class)]
#[Small]
final class InvalidPartitionExpressionTest extends TestCase
{
    public function testMessageDescribesTheEarlyPartitionRestriction(): void
    {
        $expression = new KeywordCall(KeywordFunction::User, []);
        $problem = new InvalidPartitionExpression($expression);
        self::assertSame($expression, $problem->expression);
        self::assertSame('Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed', $problem->message());
    }
}
