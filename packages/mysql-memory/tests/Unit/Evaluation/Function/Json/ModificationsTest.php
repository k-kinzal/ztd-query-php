<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Json\Modifications;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Modifications::class)]
#[Small]
final class ModificationsTest extends TestCase
{
    public function testRoutinesNamesTheFunctions(): void
    {
        self::assertSame(['JSON_SET', 'JSON_INSERT', 'JSON_REPLACE', 'JSON_ARRAY_APPEND', 'JSON_ARRAY_INSERT', 'JSON_REMOVE', 'JSON_MERGE_PATCH', 'JSON_MERGE_PRESERVE', 'JSON_MERGE'], array_map(static fn ($routine): string => $routine->name, (new Modifications())->routines()));
    }

    public function testPutSetsInsertsAndReplacesKeepingTypes(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_SET('[1]', '$[0]', 1.50), JSON_TYPE(JSON_EXTRACT(JSON_SET('{}', '$.a', 1.50), '$.a')), JSON_INSERT('{\"a\":1}', '$.a', 2, '$.b', 3), JSON_REPLACE('{\"a\":1}', '$.a', 2, '$.b', 3)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[1.50]', 'DECIMAL', '{"a": 1, "b": 3}', '{"a": 2}']], $result->rows);
    }

    public function testAppendAppendsToArraysInTurn(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_ARRAY_APPEND('[1, [2]]', '$[1]', 3, '$[0]', 4)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[[1, 4], [2, 3]]']], $result->rows);
    }

    public function testInsertShiftsTheCells(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_ARRAY_INSERT('[1, 2]', '$[1]', 9, '$[9]', 8)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[1, 9, 2, 8]']], $result->rows);
    }

    public function testEditRefusesAWildPath(): void
    {
        $this->expectExceptionMessage('In this situation, path expressions may not contain the * and ** tokens or an array range.');

        (new Instance())->connect()->query("SELECT JSON_SET('[1]', '$[*]', 1)");
    }

    public function testRemoveRemovesInTurn(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_REMOVE('[1, 2, 3]', '$[0]', '$[0]'), JSON_REMOVE('{\"a\":1}', '$.b')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[3]', '{"a": 1}']], $result->rows);
        $this->expectExceptionMessage("The path expression '$' is not allowed in this context.");
        (new Instance())->connect()->query("SELECT JSON_REMOVE('[1]', '$')");
    }

    public function testPreserveMergesEveryDocument(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_MERGE_PRESERVE('[1]', '{\"a\":1}', '2'), JSON_MERGE_PRESERVE('[1]', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[1, {"a": 1}, 2]', null]], $result->rows);
    }

    public function testPatchReplacesANullDocumentByALaterOne(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_MERGE_PATCH('{\"a\":1}', NULL, '[2]'), JSON_MERGE_PATCH('{\"a\":1,\"b\":2}', '{\"a\":null,\"c\":3}')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[2]', '{"b": 2, "c": 3}']], $result->rows);
    }

    public function testSingleAcceptsAPathWithoutWildcards(): void
    {
        $session = (new Instance())->connect();
        Modifications::single(JsonPath::parse('$[0].a'), new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        $this->expectExceptionMessage('In this situation, path expressions may not contain the * and ** tokens or an array range.');
        Modifications::single(JsonPath::parse('$[0 to 1]'), new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));
    }

    public function testResultRefusesADocumentNestingTooDeeply(): void
    {
        self::assertSame('[1]', Modifications::result(JsonNode::parse('[1]')));
        $this->expectExceptionMessage('The JSON document exceeds the maximum depth.');
        Modifications::result(JsonNode::parse('[' . str_repeat('[', 100) . str_repeat(']', 100) . ']', 200));
    }
}
