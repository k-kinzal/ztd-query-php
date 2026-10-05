<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\Query\QueryTails;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\Piece;

#[CoversClass(QueryTails::class)]
#[Small]
final class QueryTailsTest extends TestCase
{
    public function testOperationWritesTheOperatorTheQuantifierAndTheRightOperand(): void
    {
        $left = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $right = new Select([], [new SelectExpression(new NumberLiteral('2'))]);
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new QueryTails())->operation(new SetOperation($left, SetOperator::Union, SetQuantifier::All, $right), $out);

        self::assertSame(['UNION', 'ALL', 'SELECT', '2'], array_map(static fn (Piece $piece): string => $piece->text, $out->pieces()));
    }

    public function testExpressionWritesTheOrderingAndTheLimitOnly(): void
    {
        $body = new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new QueryTails())->expression(new QueryExpression(null, $body, [new OrderItem(new OutputOrdinal(new NumberLiteral('1')))], new RowLimit(new NumberLiteral('3'))), $out);

        self::assertSame(['ORDER', 'BY', '1', 'LIMIT', '3'], array_map(static fn (Piece $piece): string => $piece->text, $out->pieces()));
    }

    public function testLeadingWritesUnionTheQuantifierAndTheLastOperand(): void
    {
        $limited = new Select([], [new SelectExpression(new NumberLiteral('2'))], null, null, null, null, [], null, [], new RowLimit(new NumberLiteral('1')));
        $out = new Output(new Codec(GrammarRelease::MySql5651));
        (new QueryTails())->leading(new LeadingUnion(new Select([], [new SelectExpression(new NumberLiteral('1'))]), SetQuantifier::Distinct, $limited), $out);

        self::assertSame(['UNION', 'DISTINCT', 'SELECT', '2', 'LIMIT', '1'], array_map(static fn (Piece $piece): string => $piece->text, $out->pieces()));
    }
}
