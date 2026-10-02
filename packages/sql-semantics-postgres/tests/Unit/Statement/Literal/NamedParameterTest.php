<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NamedParameter;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(NamedParameter::class)]
#[Small]
final class NamedParameterTest extends TestCase
{
    public function testOutputNameIsNone(): void
    {
        self::assertNull((new NamedParameter('id'))->outputName());
    }

    public function testDeriveScalarDependsOnTheBoundValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new NamedParameter('id'), $derivation->environment());
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the value bound to parameter :id', $fact->type->missing[0]->describe());
    }

    public function testRenderWritesThePlaceholder(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NamedParameter('user_id'))->render($out);
        self::assertSame(':user_id', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANameWithOtherCharacters(): void
    {
        $this->expectExceptionMessage('A placeholder name is letters, digits and underscores.');
        new NamedParameter('a-b');
    }
}
