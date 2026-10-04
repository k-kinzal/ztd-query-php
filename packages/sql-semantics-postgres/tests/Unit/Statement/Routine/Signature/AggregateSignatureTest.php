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
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AggregateSignature::class)]
#[Small]
final class AggregateSignatureTest extends TestCase
{
    public function testDeriveClauseDerivesTheArguments(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new AggregateSignature(new DottedName([new Name('a')]), new AggregateArguments([new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), null, ParameterMode::Variadic)])))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesNameAndArguments(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AggregateSignature(new DottedName([new Name('a')]), new AggregateArguments([])))->render($out);
        self::assertSame('a (*)', (new Lexical())->join($out->pieces()));
    }
}
