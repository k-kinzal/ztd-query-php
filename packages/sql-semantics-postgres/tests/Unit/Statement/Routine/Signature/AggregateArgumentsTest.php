<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Signature;

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
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AggregateArguments::class)]
#[Small]
final class AggregateArgumentsTest extends TestCase
{
    public function testStarIsTheEmptyListWithoutOrderBy(): void
    {
        self::assertSame([true, false], [(new AggregateArguments([]))->star(), (new AggregateArguments([], [new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))]))->star()]);
    }

    public function testDeriveClauseReportsOutputArguments(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new AggregateArguments([new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), null, ParameterMode::Out)]))->deriveClause($derivation, $derivation->environment());
        self::assertEquals([new RoutineProblem(RoutineProblemKind::AggregateOutput)], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheFourForms(): void
    {
        $star = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AggregateArguments([]))->render($star);
        $ordered = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AggregateArguments([], [new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))]))->render($ordered);
        $both = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AggregateArguments([new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('text')]))))], [new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))]))->render($both);
        self::assertSame(['(*)', '(ORDER BY int4)', '(text ORDER BY int4)'], [(new Lexical())->join($star->pieces()), (new Lexical())->join($ordered->pieces()), (new Lexical())->join($both->pieces())]);
    }

    public function testRejectsAnEmptyOrderBy(): void
    {
        $this->expectExceptionMessage('ORDER BY is followed by at least one aggregate argument.');
        new AggregateArguments([], []);
    }
}
