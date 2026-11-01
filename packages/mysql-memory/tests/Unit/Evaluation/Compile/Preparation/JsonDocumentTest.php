<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Preparation;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Preparation\JsonDocument;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonDocument::class)]
#[Medium]
final class JsonDocumentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerDocuments(): iterable
    {
        yield 'row' => ['(1,2)', 1241, 'Operand should contain 1 column(s)'];
        yield 'row-subquery' => ['(SELECT a,b FROM t)', 1210, 'Incorrect arguments to JSON_TABLE'];
        yield 'scalar-subquery' => ['(SELECT a FROM t)', 1210, 'Incorrect arguments to JSON_TABLE'];
        yield 'dependent-width' => ['(SELECT * FROM t)', 1210, 'Incorrect arguments to JSON_TABLE'];
        yield 'nested-subquery' => ['ABS((SELECT a FROM t))', 1210, 'Incorrect arguments to JSON_TABLE'];
        yield 'column' => ['(1,missing)', 1054, "Unknown column 'missing' in 'a table function argument'"];
    }

    #[DataProvider('providerDocuments')]
    public function testCompilePreparesDocumentDependenciesBeforeRefusingTheirRole(string $document, int $code, string $message): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, b INT)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);
        $session->query("DELETE t FROM t JOIN JSON_TABLE($document, '$' COLUMNS(n FOR ORDINALITY)) j");
    }

    public function testCompileKeepsAnOrdinaryScalarDocument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT * FROM JSON_TABLE('[1,2]', '$[*]' COLUMNS(n FOR ORDINALITY)) j")[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
    }
}
