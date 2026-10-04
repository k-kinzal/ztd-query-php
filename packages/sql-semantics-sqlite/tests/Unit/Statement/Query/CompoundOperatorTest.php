<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator;

#[CoversClass(CompoundOperator::class)]
#[Small]
final class CompoundOperatorTest extends TestCase
{
    public function testCasesCarryTheKeywordsSqliteWrites(): void
    {
        self::assertSame(['UNION', 'UNION ALL', 'EXCEPT', 'INTERSECT'], array_map(static fn (CompoundOperator $operator): string => $operator->value, CompoundOperator::cases()));
        self::assertSame(CompoundOperator::UnionAll, CompoundOperator::from('UNION ALL'));
    }
}
