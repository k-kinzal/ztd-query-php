<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\CastSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Cast::class)]
#[Small]
final class CastTest extends TestCase
{
    public function testOutputNameIsTheOperandNameWhenFirm(): void
    {
        self::assertSame('a', (new Cast(new ColumnReference([new Name('a')]), new TypeName(new KeywordDesignation(TypeKeyword::Bigint)), CastSpelling::Operator))->outputName()?->value);
    }

    public function testOutputNameIsTheTypeNameOtherwise(): void
    {
        self::assertSame('int8', (new Cast(new NullLiteral(), new TypeName(new KeywordDesignation(TypeKeyword::Bigint)), CastSpelling::Function))->outputName()?->value);
    }

    public function testDeriveScalarHasTheTargetTypeAndTheOperandNullability(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Cast(new NullLiteral(), new TypeName(new KeywordDesignation(TypeKeyword::Bigint)), CastSpelling::Operator), $derivation->environment());
        self::assertEquals(new Known(Builtin::Int8), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesBothSpellings(): void
    {
        $operator = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Cast(new Constant(new IntegerConstant('1')), new TypeName(new KeywordDesignation(TypeKeyword::Bigint)), CastSpelling::Operator))->render($operator);
        $function = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Cast(new Constant(new IntegerConstant('1')), new TypeName(new KeywordDesignation(TypeKeyword::Bigint)), CastSpelling::Function))->render($function);
        self::assertSame(['1 :: BIGINT', 'CAST (1 AS BIGINT)'], [(new Lexical())->join($operator->pieces()), (new Lexical())->join($function->pieces())]);
    }

    public function testRejectsAnOperandThatBindsWeaker(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2')));
        $this->expectExceptionMessage('The operand of :: needs parentheses to keep its place.');
        new Cast($sum, new TypeName(new KeywordDesignation(TypeKeyword::Bigint)), CastSpelling::Operator);
    }
}
