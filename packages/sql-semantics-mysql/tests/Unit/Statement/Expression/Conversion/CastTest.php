<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Cast::class)]
#[Medium]
final class CastTest extends TestCase
{
    /**
     * @return iterable<string, array{CastTarget}>
     */
    public static function providerInvalidBounds(): iterable
    {
        yield 'precision' => [new CastTarget(CastKind::Decimal, '66', '2')];
        yield 'scale' => [new CastTarget(CastKind::Decimal, '65', '31')];
        yield 'scale relationship' => [new CastTarget(CastKind::Decimal, '2', '3')];
        yield 'zero precision with scale' => [new CastTarget(CastKind::Decimal, '0', '1')];
        yield 'character width' => [new CastTarget(CastKind::Char, '4294967296')];
        yield 'binary width' => [new CastTarget(CastKind::Binary, '18446744073709551616')];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidBounds')]
    public function testBoundsExcludeContradictoryTargetsBeforeAnOperationExists(CastTarget $target): void
    {
        $this->expectExceptionMessage('A CAST width, precision and scale must fit its target type.');
        new Cast(new NumberLiteral('1'), $target);
    }

    public function testDeriveScalarHasTheTargetTypeAndADateCanBeNull(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new Cast(new StringLiteral(['x']), new CastTarget(CastKind::DateTime, '3')), $derivation->environment());

        self::assertEquals(new Known(new Domain(Kind::DateTime, Field::DateTime, 23, 3)), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarIsJsonForAnArray(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.0.44', null, ParameterStyle::Native), null, [], true));

        self::assertEquals(new Known(new Elementary(ElementaryKind::Json)), $derivation->scalar(new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Unsigned), true), $derivation->environment())->type);
    }

    public function testDeriveScalarRejectsAnArrayBeforeMySql80(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.7.44', null, ParameterStyle::Native), null, [], true));

        $this->expectExceptionMessage('A cast to an array needs MySQL 8.0 or later.');

        $derivation->scalar(new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Unsigned), true), $derivation->environment());
    }

    public function testRenderGluesTheParenthesisToCast(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Char, '2'), true))->render($out);

        self::assertSame('CAST(1 AS CHAR(2) ARRAY)', (new Lexical())->join($out->pieces()));
    }

    public function testDeriveScalarWarnsAboutAUtf8mb3Target(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT CAST(1 AS CHAR CHARACTER SET utf8mb3)');

        self::assertSame(["'utf8mb3' is deprecated and will be removed in a future release. Please use utf8mb4 instead"], array_map(static fn ($warning): string => $warning->message(), $operation->warnings()));
    }

    public function testDeriveScalarWarnsThatACastToNcharIsUtf8mb3(): void
    {
        self::assertSame([Deprecated::National->value], array_map(static fn (Warning $warning): string => $warning->message(), (new Semantics(Dialect::MySql))->analyze("SELECT CAST('a' AS NCHAR(2))")->warnings()));
    }
}
