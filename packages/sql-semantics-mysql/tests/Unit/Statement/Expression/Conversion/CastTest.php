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
     * @return iterable<string, array{CastKind, ?string, ?int, int}>
     */
    public static function providerPrecisions(): iterable
    {
        yield 'float omitted' => [CastKind::Float, null, null, 53];
        yield 'float boundary' => [CastKind::Float, '53', null, 53];
        yield 'float excess' => [CastKind::Float, '54', 54, 53];
        yield 'signed overflow' => [CastKind::Float, '2147483648', -2147483648, 53];
        yield 'unsigned overflow' => [CastKind::Float, '4294967296', 0, 53];
        yield 'unsigned maximum' => [CastKind::Float, '18446744073709551615', -1, 53];
        yield 'time boundary' => [CastKind::Time, '6', null, 6];
        yield 'time excess' => [CastKind::Time, '7', 7, 6];
        yield 'datetime excess' => [CastKind::DateTime, '7', 7, 6];
        yield 'character length' => [CastKind::Char, '54', null, 6];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerPrecisions')]
    public function testPrecisionProblemPreservesTheLimitAndReportedPrecision(CastKind $kind, ?string $length, ?int $reported, int $maximum): void
    {
        $cast = new Cast(new NumberLiteral('1'), new CastTarget($kind, $length));

        self::assertEquals($reported === null ? null : new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision($reported, 'CAST', $maximum), $cast->precisionProblem());
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

    public function testArrayRefusalNamesTheTypesNoMultiValuedIndexTakes(): void
    {
        $refusals = array_map(static fn (CastTarget $target): ?string => (new Cast(new NumberLiteral('1'), $target, true))->arrayRefusal(), [new CastTarget(CastKind::Json), new CastTarget(CastKind::Real), new CastTarget(CastKind::MultiLineString), new CastTarget(CastKind::Char), new CastTarget(CastKind::NationalChar, '3'), new CastTarget(CastKind::Char, '3'), new CastTarget(CastKind::Signed)]);

        self::assertSame(['CAST-ing data to array of JSON', 'CAST-ing data to array of DOUBLE', 'CAST-ing data to array of MULTILINESTRING>', 'CAST-ing data to array of char/binary BLOBs', 'specifying charset for multi-valued index', null, null], $refusals);
    }

    public function testDeriveScalarRefusesAnArrayOfJsonWhileParsing(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT CAST(1 AS JSON ARRAY)');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $operation->facts->warnings[0]);
        self::assertTrue($operation->facts->warnings[0]->aborts);
        self::assertSame("This version of MySQL doesn't yet support 'CAST-ing data to array of JSON'.", $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarWarnsAboutAUtf8mb3Target(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT CAST(1 AS CHAR CHARACTER SET utf8mb3)');

        self::assertSame(["'utf8mb3' is deprecated and will be removed in a future release. Please use utf8mb4 instead"], array_map(static fn ($warning): string => $warning->message(), $operation->facts->warnings));
    }

    public function testDeriveScalarRefusesATooBigPrecisionWhileParsing(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT CAST(1 AS DATETIME(7))');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $operation->facts->warnings[0]);
        self::assertTrue($operation->facts->warnings[0]->aborts);
        self::assertSame("Too-big precision 7 specified for 'CAST'. Maximum is 6.", $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarWarnsThatACastToNcharIsUtf8mb3(): void
    {
        self::assertSame([Deprecated::National->value], array_map(static fn (Warning $warning): string => $warning->message(), (new Semantics(Dialect::MySql))->analyze("SELECT CAST('a' AS NCHAR(2))")->facts->warnings));
    }
}
