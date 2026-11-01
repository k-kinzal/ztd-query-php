<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\RetainedTails;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;

#[CoversClass(RetainedTails::class)]
#[Small]
final class RetainedTailsTest extends TestCase
{
    public function testDetachRetainsTheProjectionAndTransfersTheLimit(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $limit = new RowLimit(new NumberLiteral('3'));
        $body = new DeferredBody(new DeferredSelection([new DeferredProjection($row)], limit: $limit));
        [$bare, $tail] = (new RetainedTails())->detach($body);

        self::assertInstanceOf(DeferredBody::class, $bare);
        self::assertInstanceOf(DeferredProjection::class, $bare->selection->items[0]);
        self::assertSame($row, $bare->selection->items[0]->expression);
        self::assertNull($bare->selection->limit);
        self::assertSame($limit, $tail->limit);
    }

    public function testUnionKeepsSeparateOperandAndResultLimits(): void
    {
        $body = new DeferredBody(new DeferredSelection([new DeferredProjection(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))], limit: new RowLimit(new NumberLiteral('3'))));
        $left = new Select([], [new SelectExpression(new NumberLiteral('4'))]);
        $tail = new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer(limit: new RowLimit(new NumberLiteral('5')));
        $query = (new RetainedTails())->union($left, null, $body, $tail);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation::class, $query);
        self::assertSame($body, $query->right);
        self::assertSame($tail->limit, $query->limit);
    }

    public function testSubqueryRejectsResultAnalysisBeforeAssigningAQueryRole(): void
    {
        $block = new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block([], [new SelectExpression(new NumberLiteral('1'))], trailer: new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer(procedure: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse()));
        $this->expectException(\SqlSemantics\Diagnostic\AnalysisException::class);
        $this->expectExceptionMessage('Incorrect usage of PROCEDURE and subquery');
        (new RetainedTails())->subquery($block);
    }

    public function testEarlierKeepsTheLegacyLimitRoleOnlyOnTheReleaseThatDefinesIt(): void
    {
        $body = new DeferredBody(new DeferredSelection([new DeferredProjection(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))], limit: new RowLimit(new NumberLiteral('3'))));
        (new RetainedTails())->earlier($body, GrammarRelease::MySql5651);
        $this->expectException(RejectedInput::class);
        $this->expectExceptionMessage('Incorrect usage of UNION and LIMIT');
        (new RetainedTails())->earlier($body, GrammarRelease::MySql5744);
    }
}
