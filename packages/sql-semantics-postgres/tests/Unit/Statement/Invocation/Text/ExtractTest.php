<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Extract;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\ExtractUnit;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Extract::class)]
#[Small]
final class ExtractTest extends TestCase
{
    public function testFieldNameAnswersTheFieldPassed(): void
    {
        self::assertSame(['year', 'dow', 'x'], [(new Extract(ExtractUnit::Year, new NullLiteral()))->fieldName(), (new Extract(new Name('dow'), new NullLiteral()))->fieldName(), (new Extract(new StringConstant('x'), new NullLiteral()))->fieldName()]);
    }

    public function testOutputNameIsExtract(): void
    {
        self::assertSame('extract', (new Extract(ExtractUnit::Day, new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarIsNumericForADate(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Extract(ExtractUnit::Year, new ValueFunction(ValueFunctionKind::CurrentDate)), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Numeric), Nullability::NotNull), $fact);
    }

    public function testDeriveScalarDependsForAnAmbiguousSource(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Extract(ExtractUnit::Year, new Constant(new StringConstant('2020-01-01'))), $derivation->environment());
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('extract'), new Name('pg_catalog')))]), $fact->type);
    }

    public function testRenderQuotesAnIdentifierSpelledLikeAKeyword(): void
    {
        $extract = new Extract(new Name('year'), new NullLiteral());
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $extract->render($out);
        self::assertSame('EXTRACT("year" FROM NULL)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesAPlainIdentifierAndAString(): void
    {
        $extract = new Extract(new Name('epoch'), new NullLiteral());
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $extract->render($out);
        (new Extract(new StringConstant('dow'), new NullLiteral()))->render($out);
        self::assertSame('EXTRACT(epoch FROM NULL) EXTRACT(\'dow\' FROM NULL)', (new Lexical())->join($out->pieces()));
    }
}
