<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(WindowFunction::class)]
#[Small]
final class WindowFunctionTest extends TestCase
{
    public function testDeriveScalarDerivesTheArgumentsAndTheWindow(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new WindowFunction(WindowFunctionKind::Lag, [new Parameter('?'), new Parameter('?')], new WindowSpec()), $derivation->environment());

        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheOptionsBeforeTheWindow(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new WindowFunction(WindowFunctionKind::NthValue, [new ColumnUse(new Name('a')), new NumberLiteral('2')], new Name('w'), NullTreatment::Respect, CountingEdge::First))->render($out);

        self::assertSame('NTH_VALUE(a, 2) FROM FIRST RESPECT NULLS OVER w', (new Lexical())->join($out->pieces()));
    }


}
