<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Lexical;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Piece;
use SqlSemantics\Platform\Sqlite\Model\Rendering\PieceKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Node::class)]
#[Medium]
final class NodeTest extends TestCase
{
    public function testRenderWritesTypedPiecesOnly(): void
    {
        $semantics = new Inspection();
        $output = new Output(Platform::shared()->codec($semantics->profile()));

        $semantics->inspect("select a, 'x' from t")->statement->render($output);

        self::assertSame([PieceKind::Keyword, PieceKind::Name, PieceKind::Symbol, PieceKind::Literal, PieceKind::Keyword, PieceKind::Name], array_map(static fn (Piece $piece): PieceKind => $piece->kind, $output->pieces()));
        self::assertSame("SELECT a, 'x' FROM t", (new Lexical())->join($output->pieces()));
    }

    public function testRenderOfAChildIsReachedThroughTheOutput(): void
    {
        $semantics = new Inspection();
        $output = new Output(Platform::shared()->codec($semantics->profile()));

        $statement = $semantics->inspect('SELECT a FROM t WHERE a > 1')->statement;

        self::assertInstanceOf(Select::class, $statement);
        $output->node($statement->where);

        self::assertSame('a > 1', (new Lexical())->join($output->pieces()));
    }
}
