<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternOperator;

#[CoversClass(PatternOperator::class)]
#[Small]
final class PatternOperatorTest extends TestCase
{
    public function testCasesAreTheThreeKeywords(): void
    {
        self::assertSame([PatternOperator::Like, PatternOperator::ILike, PatternOperator::SimilarTo], PatternOperator::cases());
    }
}
