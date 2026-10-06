<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\Treat;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(Treat::class)]
#[Small]
final class TreatTest extends TestCase
{
    public function testOutputNameIsTheCatalogNameOfTheType(): void
    {
        self::assertSame('int4', (new Treat(new NullLiteral(), new TypeName(new KeywordDesignation(TypeKeyword::Int))))->outputName()->value);
    }

    public function testDeriveScalarDependsOnTheConversionFunction(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Treat(new Constant(new IntegerConstant('1')), new TypeName(new KeywordDesignation(TypeKeyword::Int))), $derivation->environment());
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('int4'), new Name('pg_catalog')))]), $fact->type);
    }

    public function testRenderWritesTheValueAndTheType(): void
    {
        $treat = new Treat(new Constant(new IntegerConstant('1')), new TypeName(new KeywordDesignation(TypeKeyword::Integer)));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $treat->render($out);
        self::assertSame('TREAT(1 AS INTEGER)', (new Lexical())->join($out->pieces()));
    }
}
