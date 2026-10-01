<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\BinaryOperator::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class BinaryOperatorTest extends TestCase
{
    public function testOperatorsAreAFiniteSemanticVocabulary(): void
    {
        self::assertSame(\SqlSemantics\Semantic\Expression\BinaryOperator::Add, \SqlSemantics\Semantic\Expression\BinaryOperator::from('+'));
    }
}
