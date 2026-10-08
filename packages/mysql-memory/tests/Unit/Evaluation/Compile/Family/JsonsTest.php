<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Jsons::class)]
#[Small]
final class JsonsTest extends TestCase
{
    public function testExtractionSelectsAndUnquotesWhatThePathSelects(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT x.j -> \'$.a[1]\', x.j ->> \'$.b\', x.j -> \'$[*]\', x.j -> \'$.c\' FROM (SELECT \'{"a": [1, 2.50], "b": "x\\\\ny"}\' AS j) AS x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2.5', "x\ny", null, null]], $result->rows);
        self::assertSame([Field::Json, Field::LongBlob], [$result->columns[0]->type, $result->columns[1]->type]);
    }

    public function testExtractionRefusesADocumentOfAnotherTypeEvenWhenItIsNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3146);
        $this->expectExceptionMessage('Invalid data type for JSON data in argument 1 to function json_extract; a JSON string or JSON type is required.');

        $session->query('SELECT a -> \'$\' FROM t');
    }

    public function testExtractionReadsThePathOnceTheDocumentIsRead(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (b TEXT)');
        $session->query('INSERT INTO t VALUES (NULL)');
        $result = $session->query('SELECT b -> \'bad\' FROM t')[0];
        $session->query("INSERT INTO t VALUES ('[1]')");

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Invalid JSON path expression. The error is around character position 1.');
        $session->query('SELECT b -> \'bad\' FROM t');
    }

    public function testMemberComparesTheValueWithEachElement(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 1 MEMBER OF ('[1.0, 2]'), '1' MEMBER OF ('[1]'), (1 = 1) MEMBER OF ('[true]'), 1 MEMBER OF ('1'), NULL MEMBER OF ('abc'), X'61' MEMBER OF ('[\"a\"]')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '1', null, '0']], $result->rows);
    }

    public function testMemberRefusesAnArrayThatIsNoJsonText(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3141);
        $this->expectExceptionMessage('Invalid JSON text in argument 2 to function member of: "Invalid value." at position 0.');

        $session->query("SELECT 1 MEMBER OF ('abc')");
    }

    public function testBooleanHoldsForAPredicate(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT ((1 IS NULL)), 1');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $test = $operation->field(0)->expression;
        $number = $operation->field(1)->expression;
        self::assertNotNull($test);
        self::assertNotNull($number);

        self::assertSame([true, false], [$planner->compiler->jsons->boolean($test), $planner->compiler->jsons->boolean($number)]);
    }

    public function testDocumentRefusesABinaryString(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3144);

        Jsons::document('[1]', Domain::string(3, Collation::binary()), 2, 'member of');
    }

    public function testValueKeepsTheTypeOfTheSqlValue(): void
    {
        self::assertSame(
            [JsonKind::Integer, JsonKind::Double, JsonKind::String, JsonKind::Opaque, JsonKind::Boolean, JsonKind::Array],
            [
                Jsons::value(1, Domain::integer(), false)->type,
                Jsons::value(1.5, Domain::double(), false)->type,
                Jsons::value('a', Domain::string(1, Collation::known('utf8mb4_bin')), false)->type,
                Jsons::value('a', Domain::string(1, Collation::binary()), false)->type,
                Jsons::value(1, Domain::integer(), true)->type,
                Jsons::value('[1]', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), false)->type,
            ],
        );
    }
}
