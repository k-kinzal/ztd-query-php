<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Special\Vectors;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Vectors::class)]
#[Small]
final class VectorsTest extends TestCase
{
    public function testRoutinesNamesTheVectorFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Vectors())->routines());

        self::assertSame(['STRING_TO_VECTOR', 'TO_VECTOR', 'VECTOR_TO_STRING', 'FROM_VECTOR', 'VECTOR_DIM'], $names);
    }

    public function testVectorWritesTheFloatsOfTheText(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $result = $session->query("SELECT HEX(STRING_TO_VECTOR('[1,2,3]')), HEX(TO_VECTOR(' [ +.5 , 0x10, -0 ] ')), STRING_TO_VECTOR(NULL), VECTOR_DIM(TO_VECTOR('[1,2]'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0000803F0000004000004040', '0000003F0000804100000080', null, '2']], $result->rows);
        self::assertSame([Field::Vector, 65532, 0], [$result->columns[2]->type, $result->columns[2]->length, $result->columns[2]->flags & 1]);
    }

    public function testVectorRefusesATextThatIsNoVector(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Data cannot be converted to a valid vector: '[1,]'");

        (new Instance('9.1.0'))->connect()->query("SELECT STRING_TO_VECTOR('[1,]')");
    }

    public function testVectorRefusesANumber(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to to_vector');

        (new Instance('9.1.0'))->connect()->query('SELECT STRING_TO_VECTOR(1)');
    }

    public function testVectorRefusesTooManyDimensions(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("[1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1...  value is out of range in 'to_vector'");

        (new Instance('9.1.0'))->connect()->query("SELECT STRING_TO_VECTOR(CONCAT('[', REPEAT('1,', 16383), '1]'))");
    }

    public function testNumbersReadsTheBracketedList(): void
    {
        self::assertSame([[1.0, 2.5], null, null, null], [(new Vectors())->numbers(' [1, 2.5] '), (new Vectors())->numbers('[]'), (new Vectors())->numbers('[1 2]'), (new Vectors())->numbers('1,2')]);
    }

    public function testNumberReadsAFiniteNormalFloat(): void
    {
        self::assertSame([8.0, 0.5, null, null, null, null], [(new Vectors())->number('0x1p3'), (new Vectors())->number('.5'), (new Vectors())->number('1e-40'), (new Vectors())->number('3.5e38'), (new Vectors())->number('inf'), (new Vectors())->number('1e')]);
    }

    public function testTextWritesSixSignificantDigits(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $result = $session->query("SELECT VECTOR_TO_STRING(STRING_TO_VECTOR('[0.1, 99999.95, 0.000015, 123456]')), FROM_VECTOR(0x0000C07F0000807F00000080), VECTOR_TO_STRING(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[1.00000e-01,1.00000e+05,1.50000e-05,1.23456e+05]', '[nan,inf,-0.00000e+00]', null]], $result->rows);
        self::assertSame([Field::MediumBlob, 4194048], [$result->columns[0]->type, $result->columns[0]->length]);
    }

    public function testTextRefusesTooManyDimensions(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("A value is out of range in 'from_vector'");

        (new Instance('9.1.0'))->connect()->query("SELECT VECTOR_TO_STRING(UNHEX(REPEAT('4100803F', 16384)))");
    }

    public function testFormatWritesAFloatAsCDoes(): void
    {
        self::assertSame(['1.00000e+00', '-0.00000e+00', '3.40282e+38', '-inf', 'nan'], [(new Vectors())->format(1.0), (new Vectors())->format(-0.0), (new Vectors())->format(3.4028234663852886E+38), (new Vectors())->format(-INF), (new Vectors())->format(NAN)]);
    }

    public function testBytesRefusesAStringThatIsNotBinary(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to vector_dim');

        (new Instance('9.1.0'))->connect()->query("SELECT VECTOR_DIM('abcd')");
    }

    public function testBytesRefusesALengthThatIsNoMultipleOfFour(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Data cannot be converted to a valid vector: 'A'");

        (new Instance('9.1.0'))->connect()->query('SELECT VECTOR_DIM(0x41)');
    }

    public function testIncompatibleQuotesTheTextUpToAZeroByte(): void
    {
        self::assertSame(["Data cannot be converted to a valid vector: 'a'", 511], [(new Vectors())->incompatible("a\0b", Charset::known('binary'))->getMessage(), strlen((new Vectors())->incompatible(str_repeat('x', 600), Charset::known('utf8mb4'))->getMessage())]);
    }

    public function testRoutinesAreAbsentBefore90(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('FUNCTION d.VECTOR_DIM does not exist');

        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('SELECT VECTOR_DIM(0x00000000)');
    }
}
