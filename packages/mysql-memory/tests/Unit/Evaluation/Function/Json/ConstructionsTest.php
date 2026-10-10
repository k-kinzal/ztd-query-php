<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Json\Constructions;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Constructions::class)]
#[Small]
final class ConstructionsTest extends TestCase
{
    public function testRoutinesNamesTheFunctions(): void
    {
        self::assertSame(['JSON_ARRAY', 'JSON_OBJECT', 'JSON_QUOTE', 'JSON_UNQUOTE', 'JSON_TYPE', 'JSON_VALID', 'JSON_LENGTH', 'JSON_DEPTH', 'JSON_KEYS'], array_map(static fn ($routine): string => $routine->name, (new Constructions())->routines()));
    }

    public function testArrayKeepsTheTypesOfTheValues(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_ARRAY(1, 'a', NULL, 1.50, 1=1, DATE'2020-01-01'), JSON_TYPE(JSON_EXTRACT(JSON_ARRAY(1.50), '$[0]'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[1, "a", null, 1.50, true, "2020-01-01"]', 'DECIMAL']], $result->rows);
    }

    public function testObjectKeepsTheLastMemberOfAName(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_OBJECT('b', 1, 'a', 2, 'b', 3), JSON_OBJECT(1.5, 2), JSON_OBJECT()")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['{"a": 2, "b": 3}', '{"1.5": 2}', '{}']], $result->rows);
    }

    public function testObjectRefusesANullName(): void
    {
        $this->expectExceptionObject(new SqlError(\MySqlMemory\Error\Family\DataError::JsonDocumentNullKey, 'JSON documents may not contain NULL member names.'));

        (new Instance())->connect()->query('SELECT JSON_OBJECT(NULL, 1)');
    }

    public function testDeepRefusesADocumentNestingTooDeeply(): void
    {
        $this->expectExceptionMessage('The JSON document exceeds the maximum depth.');

        self::assertSame(100, Constructions::deep(new JsonNode(JsonKind::Array, [JsonNode::parse(str_repeat('[', 99) . str_repeat(']', 99))]))->depth());
        Constructions::deep(new JsonNode(JsonKind::Array, [JsonNode::parse(str_repeat('[', 100) . str_repeat(']', 100))]));
    }

    public function testNameWritesTheTextOfAValue(): void
    {
        self::assertSame(['12', '[1]'], [Constructions::name(12, new Constant(Domain::integer(), 12)), Constructions::name('[1]', new Constant(new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Json, 4294967295, 31, false, Collation::known('utf8mb4_bin')), '[1]'))]);
    }

    public function testQuoteWritesAJsonString(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_QUOTE('a\"b'), JSON_QUOTE(NULL), JSON_QUOTE(CONVERT('é' USING latin1))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['"a\\"b"', null, '"é"']], $result->rows);
        $this->expectExceptionMessage('Incorrect type for argument 1 in function json_quote.');
        (new Instance())->connect()->query('SELECT JSON_QUOTE(1)');
    }

    public function testUnquoteReadsAQuotedStringOnly(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_UNQUOTE('\"a\\\\nb\"'), JSON_UNQUOTE('\"abc'), JSON_UNQUOTE(CAST('[1, 2]' AS JSON)), JSON_UNQUOTE(CAST(DATE'2020-01-01' AS JSON))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([["a\nb", '"abc', '[1, 2]', '2020-01-01']], $result->rows);
    }

    public function testValidTellsWhetherAValueHoldsAJsonText(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_VALID('{'), JSON_VALID(1), JSON_VALID(NULL), JSON_VALID(x'5b5d'), JSON_VALID('[1]')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', null, '0', '1']], $result->rows);
    }

    public function testLengthCountsWhatAWildPathSelects(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_LENGTH('[1,[2,3]]'), JSON_LENGTH('1'), JSON_LENGTH('[[1,2,3]]', '$[*]'), JSON_LENGTH('[]', '$[*]'), JSON_LENGTH('[1]', '$[5]')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '1', '1', null, null]], $result->rows);
    }

    public function testKeysAnswersTheNamesOfAnObject(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_KEYS('{\"b\":1,\"a\":2}'), JSON_KEYS('[1]'), JSON_KEYS('{\"a\":{\"x\":1}}', '$.a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['["a", "b"]', null, '["x"]']], $result->rows);
        $this->expectExceptionMessage('In this situation, path expressions may not contain the * and ** tokens or an array range.');
        (new Instance())->connect()->query("SELECT JSON_KEYS('{}', '$[*]')");
    }
}
