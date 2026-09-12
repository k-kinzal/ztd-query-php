<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\AssignmentExpression;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(AssignmentExpression::class)]
final class AssignmentExpressionTest extends TestCase
{
    public function testValueSkipsNestedComparisons(): void
    {
        $reader = new AssignmentExpression();
        self::assertSame('IF(a = 1, 2, 3)', $reader->value('score = IF(a = 1, 2, 3)'));
        self::assertNull($reader->value('IF(a = 1, 2, 3)'));
    }

}
