<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SortItem::class)]
#[Small]
final class SortItemTest extends TestCase
{
    public function testDeriveClauseDerivesTheSortKey(): void
    {
        $scalar = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new SortItem($scalar, SortDirection::Descending))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($scalar));
    }

    public function testRenderWritesDirectionAndNulls(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new SortItem(new Constant(new IntegerConstant('1')), SortDirection::Descending, null, NullsOrder::Last))->render($out);
        self::assertSame('1 DESC NULLS LAST', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheOrderingOperator(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new SortItem(new Constant(new IntegerConstant('1')), null, new OperatorName(new Name('<'), [new Name('pg_catalog')], true)))->render($out);
        self::assertSame('1 USING OPERATOR (pg_catalog.<)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsADirectionNextToAnOperator(): void
    {
        $this->expectExceptionMessage('A sort key has a direction or an ordering operator, not both.');
        new SortItem(new Constant(new IntegerConstant('1')), SortDirection::Ascending, new OperatorName(new Name('<')));
    }
}
