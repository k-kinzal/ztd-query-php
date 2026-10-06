<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\RandomColumnName;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(RandomColumnName::class)]
#[Medium]
final class RandomColumnNameTest extends TestCase
{
    public function testDescribeNamesTheRandomChoice(): void
    {
        self::assertSame('the name SQLite picks at random for a column whose name repeats five earlier names', (new RandomColumnName())->describe());
    }

    public function testDescribeIsTheMissingInputOfALookupThatOnlyARandomlyNamedColumnCouldAnswer(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT x, "a:4", * FROM (SELECT 1 AS a, 2 AS a, 3 AS a, 4 AS a, 5 AS a, 6 AS a)', []);
        $resolution = $query->field(0)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
        self::assertInstanceOf(RandomColumnName::class, $resolution->missing[0]);
        self::assertInstanceOf(ResolvedColumn::class, $query->field(1)->resolution);
        self::assertSame(['x', 'a:4', 'a', 'a:1', 'a:2', 'a:3', 'a:4', null], array_map(static fn (Field $field): ?string => $field->name?->value, $query->fields()->items ?? []));
    }
}
