<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Grouping\GroupingConstruct;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GroupingConstruct::class)]
#[Medium]
final class GroupingConstructTest extends TestCase
{
    public function testSharesTheGroupingSetForms(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t GROUP BY ROLLUP(a), CUBE(a), GROUPING SETS(a), ()');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([true, true, true, true], array_map(static fn (object $element): bool => $element instanceof GroupingConstruct, $statement->groupBy));
    }
}
