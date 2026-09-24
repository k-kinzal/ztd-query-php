<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;

#[CoversClass(\SqlSemantics\Ast\Type\ParameterDomains::class)]
#[Medium]
final class ParameterDomainsTest extends TestCase
{
    #[TestWith(['numeric(10, 2, 3)'])]
    #[TestWith(['bit(1, 2)'])]
    #[TestWith(['varbit(1, 2)'])]
    #[TestWith(['timetz(1, 2)'])]
    #[TestWith(['int4(1)'])]
    #[TestWith(['float8(1)'])]
    #[TestWith(['text(1)'])]
    public function testArityDiagnosesEveryExtraBuiltInOperand(string $declaration): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('number of modifier operands');
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (x ' . $declaration . ')');
    }

    public function testNumberRejectsAnIdentifierAtANumericOnlyBoundary(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1');
        $this->expectException(\SqlSemantics\InvalidSql::class);
        \SqlSemantics\Ast\Type\ParameterDomains::number(new IdentifierParameter('width'), $source);
    }

    public function testNumberRetainsTheLiteralWithoutEvaluatingItsSpelling(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1');
        $number = new NumericParameter('00012');
        self::assertSame($number, \SqlSemantics\Ast\Type\ParameterDomains::number($number, $source));
        self::assertNull(\SqlSemantics\Ast\Type\ParameterDomains::number(null, $source));
    }

    /**
     * @return array<string, array{BuiltinIdentity, Dialect, int}>
     */
    public static function providerMaximumOperands(): array
    {
        return [
            'Numeric mysql' => [BuiltinIdentity::Numeric, Dialect::MySql, 2],
            'Numeric postgresql' => [BuiltinIdentity::Numeric, Dialect::PostgreSql, 2],
            'Real mysql' => [BuiltinIdentity::Real, Dialect::MySql, 2],
            'Real postgresql' => [BuiltinIdentity::Real, Dialect::PostgreSql, 0],
            'DoublePrecision mysql' => [BuiltinIdentity::DoublePrecision, Dialect::MySql, 2],
            'DoublePrecision postgresql' => [BuiltinIdentity::DoublePrecision, Dialect::PostgreSql, 0],
            'Float mysql' => [BuiltinIdentity::Float, Dialect::MySql, 2],
            'Float postgresql' => [BuiltinIdentity::Float, Dialect::PostgreSql, 1],
            'TinyInt mysql' => [BuiltinIdentity::TinyInt, Dialect::MySql, 1],
            'TinyInt postgresql' => [BuiltinIdentity::TinyInt, Dialect::PostgreSql, 0],
            'SmallInt mysql' => [BuiltinIdentity::SmallInt, Dialect::MySql, 1],
            'SmallInt postgresql' => [BuiltinIdentity::SmallInt, Dialect::PostgreSql, 0],
            'MediumInt mysql' => [BuiltinIdentity::MediumInt, Dialect::MySql, 1],
            'MediumInt postgresql' => [BuiltinIdentity::MediumInt, Dialect::PostgreSql, 0],
            'Integer mysql' => [BuiltinIdentity::Integer, Dialect::MySql, 1],
            'Integer postgresql' => [BuiltinIdentity::Integer, Dialect::PostgreSql, 0],
            'BigInt mysql' => [BuiltinIdentity::BigInt, Dialect::MySql, 1],
            'BigInt postgresql' => [BuiltinIdentity::BigInt, Dialect::PostgreSql, 0],
            'Year mysql' => [BuiltinIdentity::Year, Dialect::MySql, 1],
            'Year postgresql' => [BuiltinIdentity::Year, Dialect::PostgreSql, 0],
            'Time mysql' => [BuiltinIdentity::Time, Dialect::MySql, 1],
            'Time postgresql' => [BuiltinIdentity::Time, Dialect::PostgreSql, 1],
            'Timestamp mysql' => [BuiltinIdentity::Timestamp, Dialect::MySql, 1],
            'Timestamp postgresql' => [BuiltinIdentity::Timestamp, Dialect::PostgreSql, 1],
            'Datetime mysql' => [BuiltinIdentity::Datetime, Dialect::MySql, 1],
            'Datetime postgresql' => [BuiltinIdentity::Datetime, Dialect::PostgreSql, 1],
            'Timetz mysql' => [BuiltinIdentity::Timetz, Dialect::MySql, 1],
            'Timetz postgresql' => [BuiltinIdentity::Timetz, Dialect::PostgreSql, 1],
            'Timestamptz mysql' => [BuiltinIdentity::Timestamptz, Dialect::MySql, 1],
            'Timestamptz postgresql' => [BuiltinIdentity::Timestamptz, Dialect::PostgreSql, 1],
            'Char mysql' => [BuiltinIdentity::Char, Dialect::MySql, 1],
            'Char postgresql' => [BuiltinIdentity::Char, Dialect::PostgreSql, 1],
            'Varchar mysql' => [BuiltinIdentity::Varchar, Dialect::MySql, 1],
            'Varchar postgresql' => [BuiltinIdentity::Varchar, Dialect::PostgreSql, 1],
            'Bit mysql' => [BuiltinIdentity::Bit, Dialect::MySql, 1],
            'Bit postgresql' => [BuiltinIdentity::Bit, Dialect::PostgreSql, 1],
            'Varbit mysql' => [BuiltinIdentity::Varbit, Dialect::MySql, 1],
            'Varbit postgresql' => [BuiltinIdentity::Varbit, Dialect::PostgreSql, 1],
            'Text mysql' => [BuiltinIdentity::Text, Dialect::MySql, 1],
            'Text postgresql' => [BuiltinIdentity::Text, Dialect::PostgreSql, 0],
            'TinyText mysql' => [BuiltinIdentity::TinyText, Dialect::MySql, 1],
            'TinyText postgresql' => [BuiltinIdentity::TinyText, Dialect::PostgreSql, 0],
            'MediumText mysql' => [BuiltinIdentity::MediumText, Dialect::MySql, 1],
            'MediumText postgresql' => [BuiltinIdentity::MediumText, Dialect::PostgreSql, 0],
            'LongText mysql' => [BuiltinIdentity::LongText, Dialect::MySql, 1],
            'LongText postgresql' => [BuiltinIdentity::LongText, Dialect::PostgreSql, 0],
            'Binary mysql' => [BuiltinIdentity::Binary, Dialect::MySql, 1],
            'Binary postgresql' => [BuiltinIdentity::Binary, Dialect::PostgreSql, 0],
            'Varbinary mysql' => [BuiltinIdentity::Varbinary, Dialect::MySql, 1],
            'Varbinary postgresql' => [BuiltinIdentity::Varbinary, Dialect::PostgreSql, 0],
            'Blob mysql' => [BuiltinIdentity::Blob, Dialect::MySql, 1],
            'Blob postgresql' => [BuiltinIdentity::Blob, Dialect::PostgreSql, 0],
            'TinyBlob mysql' => [BuiltinIdentity::TinyBlob, Dialect::MySql, 1],
            'TinyBlob postgresql' => [BuiltinIdentity::TinyBlob, Dialect::PostgreSql, 0],
            'MediumBlob mysql' => [BuiltinIdentity::MediumBlob, Dialect::MySql, 1],
            'MediumBlob postgresql' => [BuiltinIdentity::MediumBlob, Dialect::PostgreSql, 0],
            'LongBlob mysql' => [BuiltinIdentity::LongBlob, Dialect::MySql, 1],
            'LongBlob postgresql' => [BuiltinIdentity::LongBlob, Dialect::PostgreSql, 0],
            'Vector mysql' => [BuiltinIdentity::Vector, Dialect::MySql, 1],
            'Vector postgresql' => [BuiltinIdentity::Vector, Dialect::PostgreSql, 0],
            'Boolean mysql' => [BuiltinIdentity::Boolean, Dialect::MySql, 0],
            'Boolean postgresql' => [BuiltinIdentity::Boolean, Dialect::PostgreSql, 0],
        ];
    }

    #[DataProvider('providerMaximumOperands')]
    public function testArityAcceptsTheMaximumOperandCount(BuiltinIdentity $base, Dialect $dialect, int $maximum): void
    {
        $source = (new DialectParser($dialect))->parse('SELECT 1');
        $this->expectNotToPerformAssertions();
        \SqlSemantics\Ast\Type\ParameterDomains::arity($base, $maximum, $dialect, $source);
    }

    #[DataProvider('providerMaximumOperands')]
    public function testArityRejectsOneOperandPastTheMaximum(BuiltinIdentity $base, Dialect $dialect, int $maximum): void
    {
        $source = (new DialectParser($dialect))->parse('SELECT 1');
        $this->expectException(\SqlSemantics\InvalidSql::class);
        \SqlSemantics\Ast\Type\ParameterDomains::arity($base, $maximum + 1, $dialect, $source);
    }
}
