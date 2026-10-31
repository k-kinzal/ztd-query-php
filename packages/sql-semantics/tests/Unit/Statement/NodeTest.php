<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\Piece;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Node;

#[CoversClass(Node::class)]
#[Medium]
final class NodeTest extends TestCase
{
    public function testRenderWritesTypedPiecesOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $output = new Output(Platforms::of('sqlite')->codec($semantics->profile()));

        $semantics->analyze("select a, 'x' from t")->render($output);

        self::assertSame([PieceKind::Keyword, PieceKind::Name, PieceKind::Symbol, PieceKind::Literal, PieceKind::Keyword, PieceKind::Name], array_map(static fn (Piece $piece): PieceKind => $piece->kind, $output->pieces()));
        self::assertSame("SELECT a, 'x' FROM t", (new Lexical())->join($output->pieces()));
    }

    public function testRenderOfAChildIsReachedThroughTheOutput(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $output = new Output(Platforms::of('sqlite')->codec($semantics->profile()));

        $statement = $semantics->analyze('SELECT a FROM t WHERE a > 1');

        self::assertInstanceOf(Select::class, $statement);
        $output->node($statement->where);

        self::assertSame('a > 1', (new Lexical())->join($output->pieces()));
    }

    public function testSourceBelongsToTheNestedExpression(): void
    {
        $statement = (new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 7');
        self::assertEquals(new \SqlSemantics\Statement\Source\Span(7, 1), $statement->field(0)->expression?->source());
    }

    public function testHasBindingDistinguishesUnresolvedOperands(): void
    {
        $value = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('7');
        self::assertFalse($value->hasBinding());
        $statement = (new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 7');
        self::assertTrue($statement->field(0)->expression?->hasBinding());
    }

}
