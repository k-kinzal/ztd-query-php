<?php

declare(strict_types=1);

namespace Tests\Unit\Sql\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Sql\Dml\AssignmentExpression;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
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
