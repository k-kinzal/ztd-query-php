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
use SqlSemantics\Platform\Sqlite\Casts;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Declaration\TypeName;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Casts::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Expressions::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[Medium]
final class CastsTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeDescriptor, string, string|null}>
     */
    public static function providerTargets(): iterable
    {
        yield 'integer' => [new TypeDescriptor(Builtin::Integer, affinity: Affinity::Integer), 'CAST( NULL AS INTEGER )', null];
        yield 'varchar' => [new TypeDescriptor(Builtin::VarChar, length: 20), 'CAST( NULL AS VARCHAR ( 20 ) )', null];
        yield 'numeric' => [new TypeDescriptor(Builtin::Numeric, precision: 10, scale: 2), 'CAST( NULL AS NUMERIC ( 10 , 2 ) )', null];
        yield 'double precision' => [new TypeDescriptor(Builtin::DoublePrecision, affinity: Affinity::Real), 'CAST( NULL AS DOUBLE PRECISION )', null];
        yield 'named' => [new TypeDescriptor(new TypeName(['UNSIGNED', 'BIG', 'INT']), affinity: Affinity::Integer), 'CAST( NULL AS UNSIGNED BIG INT )', null];
    }

    #[DataProvider('providerTargets')]
    public function testCastTypeSpellsTheTargetThatNamesExactlyTheType(TypeDescriptor $type, string $expected, ?string $version): void
    {
        $semantics = new Semantics(Dialect::Sqlite, $version);
        $cast = $semantics->builder()->cast($semantics->builder()->null(), $type);
        self::assertSame($expected, Writer::render($cast));
        Composed::assertExpressionRoundTrips($semantics, $cast);
    }

    /**
     * @return iterable<string, array{TypeDescriptor, string, string|null}>
     */
    public static function providerRejected(): iterable
    {
        yield 'no declared type' => [new TypeDescriptor(Builtin::Dynamic, affinity: Affinity::Blob), 'needs a type name', null];
        yield 'strict any' => [new TypeDescriptor(Builtin::Any, affinity: Affinity::Blob), 'reads ANY with Numeric affinity, not Blob', null];
        yield 'array' => [new TypeDescriptor(Builtin::Integer, arrayDimensions: 1), 'cannot state its arrayDimensions', null];
    }

    #[DataProvider('providerRejected')]
    public function testCastTypeRejectsATypeTheTargetCannotName(TypeDescriptor $type, string $message, ?string $version): void
    {
        $semantics = new Semantics(Dialect::Sqlite, $version);
        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage($message);
        $semantics->builder()->cast($semantics->builder()->null(), $type);
    }
}
