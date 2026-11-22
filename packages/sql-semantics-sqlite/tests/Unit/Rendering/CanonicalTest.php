<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Spelled;
use SqlSemantics\Platform\Sqlite\Rendering\Canonical;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Canonical::class)]
#[Medium]
final class CanonicalTest extends TestCase
{
    public function testLayoutSpellsTheRenderedPiecesWithTheirGaps(): void
    {
        $layout = (new Canonical())->layout(new FunctionCall(new Name('max'), [new IntegerLiteral('1'), new IntegerLiteral('2')]));

        self::assertSame(['', '', '', '', ' ', ''], array_map(static fn (Spelled $token): string => $token->gap, $layout->tokens));
        self::assertSame('max(1, 2)', $layout->text());
    }

    public function testLayoutKeepsTheLayoutsNestedInTheNode(): void
    {
        $query = (new Inspection())->inspect('SELECT (SELECT 1+1)');
        self::assertInstanceOf(Select::class, $query->statement);
        $column = $query->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);

        self::assertNull($column->layout);
        self::assertSame('(SELECT 1+1)', (new Canonical())->layout($column->expression)->text());
    }

    public function testSameComparesTheTokensAndTheGapsAfterTheFirst(): void
    {
        $canonical = new Canonical();
        $one = new Layout([new Spelled('', '1'), new Spelled(' ', '+'), new Spelled(' ', '1')]);

        self::assertTrue($canonical->same($one, new Layout([new Spelled('  ', '1'), new Spelled(' ', '+'), new Spelled(' ', '1')])));
        self::assertFalse($canonical->same($one, new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled(' ', '1')])));
        self::assertFalse($canonical->same($one, new Layout([new Spelled('', '1'), new Spelled(' ', '-'), new Spelled(' ', '1')])));
        self::assertFalse($canonical->same($one, new Layout([new Spelled('', '1')])));
        self::assertFalse($canonical->same($one, new Layout([new Spelled('', '1'), new Spelled(' ', '+'), new Spelled(' ', '1')], ' /* c */')));
    }

    public function testTrailKeepsTheTriviaOnlyWhenItHoldsAComment(): void
    {
        $canonical = new Canonical();

        self::assertSame('', $canonical->trail(" \n\t\f\r"));
        self::assertSame(" /* c */\n ", $canonical->trail(" /* c */\n "));
        self::assertSame(' -- c', $canonical->trail(' -- c'));
    }

    public function testSpanEndsBeforeTheWhitespaceAfterTheLastComment(): void
    {
        $canonical = new Canonical();

        self::assertSame('1 + /* a */ 1 -- b', $canonical->span(new Layout([new Spelled('', '1'), new Spelled(' ', '+'), new Spelled(' /* a */ ', '1')], " -- b\n")));
        self::assertSame('1', $canonical->span(new Layout([new Spelled('', '1')])));
    }
}
