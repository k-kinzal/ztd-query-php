<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(NamedDesignation::class)]
#[Small]
final class NamedDesignationTest extends TestCase
{
    public function testTypeFactLooksTheNameUpAndAppliesTheModifiers(): void
    {
        $fact = (new NamedDesignation(new DottedName([new Name('pg_catalog'), new Name('varchar')]), [new Constant(new IntegerConstant('5'))]))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false), false);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame('character varying(5)', $fact->descriptor->name());
        self::assertInstanceOf(Dependent::class, (new NamedDesignation(new DottedName([new Name('app'), new Name('t')])))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false));
    }

    public function testCatalogNameIsTheLastPart(): void
    {
        self::assertSame('int4', (new NamedDesignation(new DottedName([new Name('pg_catalog'), new Name('int4')])))->catalogName()->value);
    }

    public function testDeriveClauseDerivesTheModifiers(): void
    {
        $modifier = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new NamedDesignation(new DottedName([new Name('t')]), [$modifier]))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderQuotesANameTheGrammarReadsAsAKeywordType(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NamedDesignation(new DottedName([new Name('integer')]), [new Constant(new IntegerConstant('1'))]))->render($out);
        self::assertSame('"integer" (1)', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NamedDesignation(new DottedName([new Name('left')])))->render($second);
        self::assertSame('left', (new Lexical())->join($second->pieces()));
    }
}
