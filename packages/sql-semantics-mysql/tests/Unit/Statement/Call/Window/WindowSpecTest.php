<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(WindowSpec::class)]
#[Small]
final class WindowSpecTest extends TestCase
{
    public function testDeriveWindowReportsWhatTheServerDoesNotSupport(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $offset = new NumberLiteral('1');
        (new WindowSpec(null, [], [], new Frame(FrameUnit::Groups, new FrameBound(FrameBoundKind::Preceding, $offset), null, FrameExclusion::Ties)))->deriveWindow($derivation, $derivation->environment());

        self::assertSame(Nullability::NotNull, $derivation->facts()->scalar($offset)->nullability);
        self::assertEquals([new UnsupportedWindowing(WindowingLimit::GroupsUnit), new UnsupportedWindowing(WindowingLimit::Exclusion)], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheParts(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new WindowSpec(new Name('w'), [new OrderItem(new ColumnUse(new Name('a')))], [new OrderItem(new ColumnUse(new Name('a')), Direction::Ascending)], new Frame(FrameUnit::Rows, new FrameBound(FrameBoundKind::UnboundedPreceding))))->render($out);

        self::assertSame('(w PARTITION BY a ORDER BY a ASC ROWS UNBOUNDED PRECEDING)', (new Lexical())->join($out->pieces()));
    }
}
