<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;

#[CoversClass(QueryWidth::class)]
#[Medium]
final class QueryWidthTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function providerQueries(): iterable
    {
        yield 'explicit projection' => ['SELECT 1,2', 2];
        yield 'wildcard' => ['SELECT * FROM t', null];
        yield 'table' => ['TABLE t', null];
        yield 'values' => ['VALUES ROW(1,2,3)', 3];
        yield 'set' => ['(SELECT 1,2) UNION SELECT 3,4', 2];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerQueries')]
    public function testOfDistinguishesFixedOutputFromCatalogDependentOutput(string $sql, ?int $width): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze($sql)->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
        self::assertSame($width, (new QueryWidth())->of($query->query));
    }
}
