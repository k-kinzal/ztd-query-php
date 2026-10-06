<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(OrdinalityColumn::class)]
#[Small]
final class OrdinalityColumnTest extends TestCase
{
    public function testDeriveColumnsAnswersACounter(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $slots = (new OrdinalityColumn(new Name('n')))->deriveColumns($derivation, $derivation->environment());

        self::assertCount(1, $slots);
        self::assertSame(Nullability::NotNull, $slots[0]->nullability);
        self::assertEquals(new Known(new Integral(IntegralKind::Int, null, [\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier::Unsigned])), $slots[0]->type);
    }

    public function testRenderWritesForOrdinality(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new OrdinalityColumn(new Name('n')))->render($out);

        self::assertSame('n FOR ORDINALITY', (new Lexical())->join($out->pieces()));
    }
}
