<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Trim;
use SqlSemantics\Platform\MySql\Statement\Call\TrimSide;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Trim::class)]
#[Small]
final class TrimTest extends TestCase
{
    public function testDeriveScalarAnswersAString(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new Trim(new StringLiteral(['x']), TrimSide::Both, new StringLiteral(['x'])), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Character->descriptor()), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesEachForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $plain = new Output($platform->codec($profile));
        (new Trim(new ColumnUse(new Name('a'))))->render($plain);
        $side = new Output($platform->codec($profile));
        (new Trim(new ColumnUse(new Name('a')), TrimSide::Trailing))->render($side);
        $removed = new Output($platform->codec($profile));
        (new Trim(new ColumnUse(new Name('a')), null, new StringLiteral(['x'])))->render($removed);

        self::assertSame('TRIM(a)', (new Lexical())->join($plain->pieces()));
        self::assertSame('TRIM(TRAILING FROM a)', (new Lexical())->join($side->pieces()));
        self::assertSame("TRIM('x' FROM a)", (new Lexical())->join($removed->pieces()));
    }
}
