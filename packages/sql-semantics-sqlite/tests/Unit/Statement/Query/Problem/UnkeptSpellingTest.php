<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\UnkeptSpelling;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(UnkeptSpelling::class)]
#[Medium]
final class UnkeptSpellingTest extends TestCase
{
    public function testDescribeNamesTheSourceText(): void
    {
        self::assertSame('the source text SQLite names an unaliased result expression after', (new UnkeptSpelling())->describe());
    }

    public function testDescribeIsTheMissingInputOfALookupThroughAnUnaliasedExpressionColumn(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 + 1)', []);
        $resolution = $query->field(0)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
        self::assertInstanceOf(UnkeptSpelling::class, $resolution->missing[0]);
        self::assertInstanceOf(Dependent::class, $query->field(0)->type);
        self::assertSame($resolution->missing, $query->field(0)->type->missing);
        self::assertSame(Nullability::Dependent, $query->field(0)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDescribeIsNotNeededWhenANamedColumnMatches(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 + 1, 2 AS x)', []);

        self::assertInstanceOf(ResolvedColumn::class, $query->field(0)->resolution);
    }
}
