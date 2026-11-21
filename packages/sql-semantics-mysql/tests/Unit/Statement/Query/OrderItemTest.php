<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

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
