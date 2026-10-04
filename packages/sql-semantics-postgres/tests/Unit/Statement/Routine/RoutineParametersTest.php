<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\RoutineParameters;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(RoutineParameters::class)]
#[Small]
final class RoutineParametersTest extends TestCase
{
    public function testDeriveRelationHoldsTheInputParameters(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $parameters = new RoutineParameters([
            new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('a')),
            new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('text')]))), new Name('b'), ParameterMode::Out),
            new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int8')])))),
        ]);
        $shape = $derivation->relation($parameters, $derivation->environment())->shape;
        self::assertSame(['a', null], [$shape->slots[0]->name?->value, $shape->slots[1]->name]);
        self::assertEquals([new Known(Builtin::Int4), new Known(Builtin::Int8)], [$shape->slots[0]->type, $shape->slots[1]->type]);
    }

    public function testRenderWritesTheParenthesizedList(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineParameters([]))->render($out);
        self::assertSame('()', (new Lexical())->join($out->pieces()));
    }

}
