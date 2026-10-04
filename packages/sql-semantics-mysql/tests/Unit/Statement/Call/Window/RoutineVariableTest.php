<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RoutineVariable::class)]
#[Small]
final class RoutineVariableTest extends TestCase
{
    public function testDeriveScalarDependsOnTheProgramState(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new RoutineVariable(new Name('n')), $derivation->environment());

        self::assertEquals(new Dependent([new SessionState('routine variable n')]), $fact->type);
        self::assertSame(Nullability::Dependent, $fact->nullability);
    }

    public function testRenderWritesTheName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new RoutineVariable(new Name('n')))->render($out);

        self::assertSame('n', (new Lexical())->join($out->pieces()));
    }
}
