<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Position;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

#[CoversClass(Position::class)]
#[Small]
final class PositionTest extends TestCase
{
    public function testDeriveScalarAnswersAnInteger(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new Position(new StringLiteral(['x']), new StringLiteral(['x'])), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Integer->descriptor()), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesIn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new Position(new StringLiteral(['x']), new ColumnUse(new Name('a'))))->render($out);

        self::assertSame("POSITION('x' IN a)", (new Lexical())->join($out->pieces()));
    }
}
