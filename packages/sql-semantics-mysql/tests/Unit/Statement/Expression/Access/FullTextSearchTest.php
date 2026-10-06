<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Interval;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(FullTextSearch::class)]
#[Medium]
final class FullTextSearchTest extends TestCase
{
    public function testDeriveScalarIsADoubleRelevance(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $fact = $derivation->scalar(new FullTextSearch([new ColumnUse(new Name('a'))], new StringLiteral(['x'])), $derivation->environment());

        self::assertEquals(new Known(new Floating(FloatingKind::Double)), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheColumnsInParenthesesAndTheMode(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new FullTextSearch([new ColumnUse(new Name('a')), new ColumnUse(new Name('b'))], new StringLiteral(['x']), FullTextMode::Boolean))->render($out);

        self::assertSame("MATCH (a, b) AGAINST ('x' IN BOOLEAN MODE)", (new Lexical())->join($out->pieces()));
    }

    public function testAPredicateSearchStringIsRejected(): void
    {
        $this->expectExceptionMessage('The search string of AGAINST needs a grouping to keep its place.');

        new FullTextSearch([new ColumnUse(new Name('a'))], new InList(new NumberLiteral('1'), [new NumberLiteral('2')]));
    }

    public function testASearchStringThatTakesTheBooleanModeIsRejected(): void
    {
        $this->expectExceptionMessage('A search string followed by IN ... MODE needs a grouping to keep its place.');

        new FullTextSearch([new ColumnUse(new Name('a'))], new IntervalAddition(new Interval(new NumberLiteral('1'), IntervalUnit::Day), new NumberLiteral('2')), FullTextMode::Boolean);
    }
}
