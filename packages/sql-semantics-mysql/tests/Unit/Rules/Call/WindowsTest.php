<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Windows::class)]
#[Small]
final class WindowsTest extends TestCase
{
    public function testDeriveDerivesTheExpressionsOfASpecification(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $order = new NumberLiteral('1');
        (new Windows())->derive(new WindowSpec(null, [], [new OrderItem($order)]), $derivation, $derivation->environment());
        (new Windows())->derive(new Name('w'), $derivation, $derivation->environment());

        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $derivation->facts()->scalar($order)->nullability);
    }

    public function testRenderWritesTheWindowAfterOver(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $named = new Output($platform->codec($profile));
        (new Windows())->render(new Name('w'), $named);
        $specified = new Output($platform->codec($profile));
        (new Windows())->render(new WindowSpec(new Name('w')), $specified);
        $none = new Output($platform->codec($profile));
        (new Windows())->render(null, $none);

        self::assertSame('OVER w', (new Lexical())->join($named->pieces()));
        self::assertSame('OVER (w)', (new Lexical())->join($specified->pieces()));
        self::assertSame([], $none->pieces());
    }
}
