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
use SqlSemantics\Platform\MySql\Casts;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Casts::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Expressions::class)]
#[Medium]
final class CastsTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeDescriptor, string, string|null}>
     */
    public static function providerTargets(): iterable
    {
        yield 'signed' => [new TypeDescriptor(Builtin::BigInt), 'CAST( NULL AS SIGNED )', null];
        yield 'unsigned' => [new TypeDescriptor(Builtin::BigInt, unsigned: true), 'CAST( NULL AS UNSIGNED )', null];
        yield 'varchar of a quoted character set' => [new TypeDescriptor(Builtin::VarChar, length: 10, characterSet: 'utf8mb4) , x('), 'CAST( NULL AS CHAR( 10 ) CHARACTER SET `utf8mb4) , x(` )', null];
        yield 'varchar' => [new TypeDescriptor(Builtin::VarChar, length: 10, characterSet: 'utf8mb4'), 'CAST( NULL AS CHAR( 10 ) CHARACTER SET utf8mb4 )', null];
        yield 'varchar as long as the value' => [new TypeDescriptor(Builtin::VarChar, binaryCollation: true), 'CAST( NULL AS CHAR BINARY )', null];
        yield 'varbinary as long as the value' => [new TypeDescriptor(Builtin::VarBinary), 'CAST( NULL AS BINARY )', null];
        yield 'decimal' => [new TypeDescriptor(Builtin::Numeric, precision: 10, scale: 2), 'CAST( NULL AS DECIMAL( 10 , 2 ) )', null];
        yield 'double' => [new TypeDescriptor(Builtin::DoublePrecision), 'CAST( NULL AS DOUBLE )', null];
        yield 'float' => [new TypeDescriptor(Builtin::Real), 'CAST( NULL AS FLOAT )', null];
        yield 'date' => [new TypeDescriptor(Builtin::Date), 'CAST( NULL AS DATE )', null];
        yield 'time' => [new TypeDescriptor(Builtin::Time, precision: 3), 'CAST( NULL AS TIME( 3 ) )', null];
        yield 'datetime' => [new TypeDescriptor(Builtin::DateTime, precision: 6), 'CAST( NULL AS DATETIME( 6 ) )', null];
        yield 'year' => [new TypeDescriptor(Builtin::Year), 'CAST( NULL AS YEAR )', null];
        yield 'json' => [new TypeDescriptor(Builtin::Json), 'CAST( NULL AS JSON )', null];
        yield 'point' => [new TypeDescriptor(Builtin::Point), 'CAST( NULL AS POINT )', null];
        yield 'signed in 5.6' => [new TypeDescriptor(Builtin::BigInt), 'CAST( NULL AS SIGNED )', 'mysql-5.6.51'];
    }

    #[DataProvider('providerTargets')]
    public function testCastTypeSpellsTheTargetThatNamesExactlyTheType(TypeDescriptor $type, string $expected, ?string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $cast = $semantics->builder()->cast($semantics->builder()->null(), $type);
        self::assertSame($expected, Writer::render($cast));
        Composed::assertExpressionRoundTrips($semantics, $cast);
    }

    /**
     * @return iterable<string, array{TypeDescriptor, string, string|null}>
     */
    public static function providerRejected(): iterable
    {
        yield 'int' => [new TypeDescriptor(Builtin::Integer), 'no target of type integer', null];
        yield 'char' => [new TypeDescriptor(Builtin::Char, length: 1), 'no target of type char', null];
        yield 'binary' => [new TypeDescriptor(Builtin::Binary, length: 4), 'no target of type binary', null];
        yield 'varbinary of a length' => [new TypeDescriptor(Builtin::VarBinary, length: 4), 'no target of type varbinary', null];
        yield 'text' => [new TypeDescriptor(Builtin::Text), 'no target of type text', null];
        yield 'enum' => [new TypeDescriptor(Builtin::Enum, members: [(new Semantics(Dialect::MySql))->builder()->string('a')]), 'no target of type enum', null];
        yield 'display width' => [new TypeDescriptor(Builtin::BigInt, length: 20), 'cannot state its length', null];
        yield 'zero fill' => [new TypeDescriptor(Builtin::BigInt, unsigned: true, zerofill: true), 'cannot state its zerofill', null];
        yield 'float before 8.0.17' => [new TypeDescriptor(Builtin::Real), 'No form for SELECT CAST(slot0 AS FLOAT)', 'mysql-5.7.44'];
        yield 'year before 8.0' => [new TypeDescriptor(Builtin::Year), 'No form for SELECT CAST(slot0 AS YEAR)', 'mysql-5.7.44'];
    }

    #[DataProvider('providerRejected')]
    public function testCastTypeRejectsATypeTheTargetCannotName(TypeDescriptor $type, string $message, ?string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage($message);
        $semantics->builder()->cast($semantics->builder()->null(), $type);
    }
}
