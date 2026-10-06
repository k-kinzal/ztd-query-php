<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(OrderItem::class)]
#[Small]
final class OrderItemTest extends TestCase
{
    public function testRenderWritesTheExpressionAndTheDirection(): void
    {
        $item = new OrderItem(new ColumnUse(new Name('a'), new QualifiedName(new Name('t'))), Direction::Descending);
        $out = new Output(new Codec(GrammarRelease::MySql847));

        $item->render($out);

        self::assertSame('t.a DESC', (new Lexical())->join($out->pieces()));
        self::assertSame(Direction::Descending, $item->direction);
    }

    public function testRenderKeepsAnAscendingDirectionThatWasWritten(): void
    {
        $item = new OrderItem(new ColumnUse(new Name('a')), Direction::Ascending);
        $out = new Output(new Codec(GrammarRelease::MySql847));

        $item->render($out);

        self::assertSame('a ASC', (new Lexical())->join($out->pieces()));
    }

    public function testRenderOmitsTheDirectionWhenNoneWasWritten(): void
    {
        $item = new OrderItem(new ColumnUse(new Name('order')));
        $out = new Output(new Codec(GrammarRelease::MySql847));

        $item->render($out);

        self::assertSame('`order`', (new Lexical())->join($out->pieces()));
        self::assertNull($item->direction);
    }
}
