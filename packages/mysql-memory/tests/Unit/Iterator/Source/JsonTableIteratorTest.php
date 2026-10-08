<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Source;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\JsonTableIterator;
use MySqlMemory\Plan\Path\Source\JsonColumn;
use MySqlMemory\Plan\Path\Source\JsonTableScan;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(JsonTableIterator::class)]
#[Small]
final class JsonTableIteratorTest extends TestCase
{
    public function testInitReadsTheDocumentAndNullHasNoRows(): void
    {
        $session = (new Instance())->connect();
        $iterator = new JsonTableIterator(new JsonTableScan(new Constant(Domain::null(), null), JsonPath::parse('$[*]'), [new JsonColumn('ordinality', 'n', Domain::integer())]));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }

    public function testRowsAnswersTheRowsOfEachNestedPathInTurn(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $columns = [new JsonColumn('ordinality', 'n', Domain::integer()), new JsonColumn('nested', '', Domain::null(), JsonPath::parse('$.a[*]'), columns: [new JsonColumn('path', 'x', Domain::integer(), JsonPath::parse('$'))]), new JsonColumn('nested', '', Domain::null(), JsonPath::parse('$.b[*]'), columns: [new JsonColumn('path', 'y', Domain::integer(), JsonPath::parse('$'))])];

        self::assertSame([[1, 1, null], [1, 2, null], [1, null, 3], [2, null, null]], JsonTableIterator::rows(JsonNode::parse('[{"a": [1, 2], "b": [3]}, {}]'), JsonPath::parse('$[*]'), $columns, $context));
    }

    public function testReadAnswersTheRowsInOrder(): void
    {
        $result = (new Instance())->connect()->query("SELECT * FROM JSON_TABLE('[{\"a\":1,\"c\":[1,2]},{\"a\":\"zz\"}]', '$[*]' COLUMNS (n FOR ORDINALITY, a INT PATH '$.a', e INT EXISTS PATH '$.c', NESTED PATH '$.c[*]' COLUMNS (m FOR ORDINALITY, v VARCHAR(5) PATH '$'))) AS t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '1', '1'], ['1', '1', '1', '2', '2'], ['2', null, '0', null, null]], $result->rows);
        self::assertSame(['n', 't'], [$result->columns[0]->name, $result->columns[0]->table]);
        self::assertSame(Collation::known('utf8mb4_0900_ai_ci')->id, $result->columns[4]->charset);
    }
}
