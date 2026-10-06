<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(GroupConcat::class)]
#[Small]
final class GroupConcatTest extends TestCase
{
    public function testAggregatesTellsWhetherTheCallHasNoWindow(): void
    {
        self::assertTrue((new GroupConcat([new NumberLiteral('1')]))->aggregates());
    }

    public function testDeriveScalarReportsAWindow(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $fact = $derivation->scalar(new GroupConcat([new StringLiteral(['x'])], false, [], null, new Name('w')), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Character->descriptor()), $fact->type);
        self::assertEquals([new UnsupportedWindowing(WindowingLimit::GroupConcat)], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheOrderingAndTheSeparator(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new GroupConcat([new ColumnUse(new Name('a'))], true, [new OrderItem(new ColumnUse(new Name('a')), Direction::Descending)], new Text(';')))->render($out);

        self::assertSame("GROUP_CONCAT(DISTINCT a ORDER BY a DESC SEPARATOR ';')", (new Lexical())->join($out->pieces()));
    }
}
