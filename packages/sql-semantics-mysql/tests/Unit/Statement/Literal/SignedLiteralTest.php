<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SignedLiteral::class)]
#[Small]
final class SignedLiteralTest extends TestCase
{
    public function testDeriveScalarKeepsTheTypeOfTheNumber(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false));
        $integer = (new SignedLiteral(true, new NumberLiteral('5')))->deriveScalar($derivation, $derivation->environment());
        $decimal = (new SignedLiteral(false, new NumberLiteral('1.5')))->deriveScalar($derivation, $derivation->environment());
        $float = (new SignedLiteral(true, new NumberLiteral('1e3')))->deriveScalar($derivation, $derivation->environment());

        self::assertInstanceOf(Known::class, $integer->type);
        self::assertInstanceOf(Integral::class, $integer->type->descriptor);
        self::assertSame(IntegralKind::BigInt, $integer->type->descriptor->kind);
        self::assertFalse($integer->type->descriptor->unsigned());
        self::assertSame(Nullability::NotNull, $integer->nullability);
        self::assertInstanceOf(Known::class, $decimal->type);
        self::assertInstanceOf(Decimal::class, $decimal->type->descriptor);
        self::assertInstanceOf(Known::class, $float->type);
        self::assertInstanceOf(Floating::class, $float->type->descriptor);
        self::assertSame(FloatingKind::Double, $float->type->descriptor->kind);
    }

    public function testDeriveScalarAnswersDecimalForANegatedIntegerBeyondTheSignedRange(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false));
        $smallest = (new SignedLiteral(true, new NumberLiteral('9223372036854775808')))->deriveScalar($derivation, $derivation->environment());
        $beyond = (new SignedLiteral(true, new NumberLiteral('9223372036854775809')))->deriveScalar($derivation, $derivation->environment());
        $padded = (new SignedLiteral(true, new NumberLiteral('0009223372036854775808')))->deriveScalar($derivation, $derivation->environment());

        self::assertInstanceOf(Known::class, $smallest->type);
        self::assertInstanceOf(Decimal::class, $smallest->type->descriptor);
        self::assertSame(Nullability::NotNull, $smallest->nullability);
        self::assertInstanceOf(Known::class, $beyond->type);
        self::assertInstanceOf(Decimal::class, $beyond->type->descriptor);
        self::assertInstanceOf(Known::class, $padded->type);
        self::assertInstanceOf(Decimal::class, $padded->type->descriptor);
    }

    public function testDeriveScalarKeepsBigIntForTheLargestNegativeIntegerThatFits(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false));
        $fact = (new SignedLiteral(true, new NumberLiteral('9223372036854775807')))->deriveScalar($derivation, $derivation->environment());

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Integral::class, $fact->type->descriptor);
        self::assertSame(IntegralKind::BigInt, $fact->type->descriptor->kind);
        self::assertFalse($fact->type->descriptor->unsigned());
    }

    public function testDeriveScalarKeepsTheUnsignedTypeOfAPositiveIntegerBeyondTheSignedRange(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false));
        $fact = (new SignedLiteral(false, new NumberLiteral('9223372036854775808')))->deriveScalar($derivation, $derivation->environment());

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Integral::class, $fact->type->descriptor);
        self::assertSame(IntegralKind::BigInt, $fact->type->descriptor->kind);
        self::assertTrue($fact->type->descriptor->unsigned());
    }

    public function testRenderWritesTheSignAndTheNumber(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new SignedLiteral(true, new NumberLiteral('5')))->render($out);
        (new SignedLiteral(false, new NumberLiteral('1.5')))->render($out);
        $pieces = $out->pieces();

        self::assertSame([PieceKind::Symbol, PieceKind::Literal, PieceKind::Symbol, PieceKind::Literal], array_column($pieces, 'kind'));
        self::assertSame(['-', '5', '+', '1.5'], array_column($pieces, 'text'));
    }

    public function testLoweredDefaultKeepsTheSignAndTheUnsignedText(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $negative = $lowering->literals->literal($parser->parse('CREATE TABLE t (c INT DEFAULT -5)')->find('signed_literal')[0]);
        $positive = $lowering->literals->literal($parser->parse('CREATE TABLE t (c INT DEFAULT +1.5)')->find('signed_literal')[0]);

        self::assertInstanceOf(SignedLiteral::class, $negative);
        self::assertTrue($negative->negative);
        self::assertSame('5', $negative->number->text);
        self::assertInstanceOf(SignedLiteral::class, $positive);
        self::assertFalse($positive->negative);
        self::assertSame('1.5', $positive->number->text);
    }
}
