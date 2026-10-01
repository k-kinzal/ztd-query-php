<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Language;
use SqlSemantics\Platform\PostgreSql\Analysis\OperationReader;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Transaction\Savepoint;

#[CoversClass(OperationReader::class)]
#[Medium]
final class OperationReaderTest extends TestCase
{
    #[TestWith(['BEGIN WORK', 'BEGIN'])]
    #[TestWith(['COMMIT WORK', 'COMMIT'])]
    #[TestWith(['ROLLBACK', 'ROLLBACK'])]
    #[TestWith(['SAVEPOINT mark', 'SAVEPOINT mark'])]
    #[TestWith(['RELEASE SAVEPOINT mark', 'RELEASE mark'])]
    public function testReadDescribesTheRequestWithoutTransactionHistory(string $sql, string $expected): void
    {
        $language = new Language(Dialect::PostgreSql);
        $platform = $language->dialect->platform();
        $catalog = $platform->catalog($platform->searchPath(), false);
        $reader = new OperationReader();
        $operation = $reader->read($language->parser()->parse($sql), $catalog);
        self::assertSame($expected, $operation->toString());
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($reader->read($language->parser()->parse($operation->toString()), $catalog)));
    }

    public function testTransactionRetainsTheSavepointName(): void
    {
        $language = new Language(Dialect::PostgreSql);
        $source = Tree::outer($language->parser()->parse('SAVEPOINT mark'), ['TransactionStmt'])[0];
        $operation = (new OperationReader())->transaction($source);
        self::assertInstanceOf(Savepoint::class, $operation);
        self::assertSame('mark', $operation->name->value);
    }

    public function testIdentifierKeepsTheDecodedName(): void
    {
        $language = new Language(Dialect::PostgreSql);
        $source = Tree::outer($language->parser()->parse('SAVEPOINT mark'), ['ColId'])[0];
        self::assertSame('mark', (new OperationReader())->identifier($source)->value);
    }
}
