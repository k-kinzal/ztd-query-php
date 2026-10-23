<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\JsonColumn;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(JsonColumn::class)]
#[Small]
final class JsonColumnTest extends TestCase
{
    public function testWidthCountsANestedPathByItsColumns(): void
    {
        self::assertSame([1, 2], [(new JsonColumn('ordinality', 'n', Domain::integer()))->width(), (new JsonColumn('nested', '', Domain::null(), JsonPath::parse('$'), columns: [new JsonColumn('ordinality', 'm', Domain::integer()), new JsonColumn('path', 'v', Domain::integer(), JsonPath::parse('$'))]))->width()]);
    }

    public function testValueConvertsOrAnswersTheResponses(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $column = new JsonColumn('path', 'a', Domain::integer(Field::Long, 11), JsonPath::parse('$.a'), [JsonResponseKind::Default, JsonNode::parse('5')]);

        self::assertSame([2, 5, null, null, 1], [$column->value(JsonNode::parse('{"a": 1.5}'), $context), $column->value(JsonNode::parse('{}'), $context), $column->value(JsonNode::parse('{"a": [1]}'), $context), $column->value(JsonNode::parse('{"a": "12x"}'), $context), (new JsonColumn('exists', 'e', Domain::integer(Field::Long, 11), JsonPath::parse('$.a')))->value(JsonNode::parse('{"a": null}'), $context)]);
    }

    public function testFailWarnsOfAValueOutOfTheRangeOfTheColumn(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        (new JsonColumn('path', 'v', Domain::integer(Field::Tiny, 4), JsonPath::parse('$')))->fail(JsonNode::parse('300'), $context);
        (new JsonColumn('path', 'v', Domain::string(3, Collation::known('utf8mb4_0900_ai_ci')), JsonPath::parse('$')))->fail(JsonNode::parse('"abcdef"'), $context);

        self::assertSame(2, $session->diagnostics->count());
        $this->expectExceptionMessage('Invalid JSON value for CAST to INTEGER from column v at row 1');
        (new JsonColumn('path', 'v', Domain::integer(Field::Tiny, 4), JsonPath::parse('$')))->fail(JsonNode::parse('"abc"'), $context);
    }

    public function testRespondRaisesTheErrorOfAnErrorResponse(): void
    {
        $column = new JsonColumn('path', 'a', Domain::integer(Field::Long, 11), JsonPath::parse('$.a'));

        self::assertSame([null, 7], [$column->respond([JsonResponseKind::Null, null], \MySqlMemory\Error\Family\DataError::MissingJsonTableValue), $column->respond([JsonResponseKind::Default, JsonNode::parse('"7"')], \MySqlMemory\Error\Family\DataError::MissingJsonTableValue)]);
        $this->expectExceptionObject(new SqlError(\MySqlMemory\Error\Family\DataError::MissingJsonTableValue, "Missing value for JSON_TABLE column 'a'"));
        $column->respond([JsonResponseKind::Error, null], \MySqlMemory\Error\Family\DataError::MissingJsonTableValue);
    }
}
