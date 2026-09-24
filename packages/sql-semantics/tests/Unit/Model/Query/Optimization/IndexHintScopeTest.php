<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Optimization\IndexHintScope;

#[CoversClass(IndexHintScope::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class IndexHintScopeTest extends TestCase
{
    public function testCasesAreBackedByTheirForClauseKeywords(): void
    {
        self::assertSame(['JOIN', 'ORDER BY', 'GROUP BY'], array_map(static fn (IndexHintScope $scope): string => $scope->value, IndexHintScope::cases()));
    }
}
