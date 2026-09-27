<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Casts;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\IntervalFields;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Declaration\TypeName;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Casts::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Expressions::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[Medium]
final class CastsTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeDescriptor, string, string|null}>
     */
    public static function providerTargets(): iterable
    {
        yield 'integer' => [new TypeDescriptor(Builtin::Integer), 'CAST( NULL AS INTEGER )', null];
        yield 'varchar' => [new TypeDescriptor(Builtin::VarChar, length: 20), 'CAST( NULL AS VARCHAR( 20 ) )', null];
        yield 'numeric' => [new TypeDescriptor(Builtin::Numeric, precision: 10, scale: 2), 'CAST( NULL AS NUMERIC( 10 , 2 ) )', null];
        yield 'text array' => [new TypeDescriptor(Builtin::Text, arrayDimensions: 2), 'CAST( NULL AS text [ ] [ ] )', null];
        yield 'timestamptz' => [new TypeDescriptor(Builtin::TimestampTz, precision: 3), 'CAST( NULL AS TIMESTAMP( 3 ) with TIME ZONE )', null];
        yield 'time' => [new TypeDescriptor(Builtin::Time), 'CAST( NULL AS TIME )', null];
        yield 'interval fields' => [new TypeDescriptor(Builtin::Interval, precision: 3, intervalFields: IntervalFields::DayToSecond), 'CAST( NULL AS INTERVAL DAY TO SECOND( 3 ) )', null];
        yield 'interval precision' => [new TypeDescriptor(Builtin::Interval, precision: 2), 'CAST( NULL AS INTERVAL( 2 ) )', null];
        yield 'double precision' => [new TypeDescriptor(Builtin::DoublePrecision), 'CAST( NULL AS DOUBLE PRECISION )', null];
        yield 'quoted char' => [new TypeDescriptor(Builtin::QuotedChar), 'CAST( NULL AS "char" )', null];
        yield 'uuid' => [new TypeDescriptor(Builtin::Uuid), 'CAST( NULL AS uuid )', null];
        yield 'named' => [new TypeDescriptor(new TypeName(['app', 'Money'])), 'CAST( NULL AS app."Money" )', null];
    }

    #[DataProvider('providerTargets')]
    public function testCastTypeSpellsTheTargetThatNamesExactlyTheType(TypeDescriptor $type, string $expected, ?string $version): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, $version);
        $cast = $semantics->builder()->cast($semantics->builder()->null(), $type);
        self::assertSame($expected, Writer::render($cast));
        Composed::assertExpressionRoundTrips($semantics, $cast);
    }

    /**
     * @return iterable<string, array{TypeDescriptor, string, string|null}>
     */
    public static function providerRejected(): iterable
    {
        yield 'tinyint' => [new TypeDescriptor(Builtin::TinyInt), 'PostgreSQL has no type tinyint', null];
        yield 'unsigned' => [new TypeDescriptor(Builtin::Integer, unsigned: true), 'cannot state its unsigned', null];
        yield 'interval precision without seconds' => [new TypeDescriptor(Builtin::Interval, precision: 2, intervalFields: IntervalFields::Day), 'An interval of day has no precision', null];
        yield 'named with a length' => [new TypeDescriptor(new TypeName(['money_amount']), length: 3), 'cannot state its length', null];
    }

    #[DataProvider('providerRejected')]
    public function testCastTypeRejectsATypeTheTargetCannotName(TypeDescriptor $type, string $message, ?string $version): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, $version);
        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage($message);
        $semantics->builder()->cast($semantics->builder()->null(), $type);
    }
}
