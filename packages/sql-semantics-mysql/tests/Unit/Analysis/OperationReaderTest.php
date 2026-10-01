<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Language;
use SqlSemantics\Platform\MySql\Analysis\OperationReader;
use SqlSemantics\Platform\MySql\Dialect;
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
    #[TestWith(['RELEASE SAVEPOINT mark', 'RELEASE SAVEPOINT mark'])]
    public function testReadDescribesTheRequestWithoutTransactionHistory(string $sql, string $expected): void
    {
        $language = new Language(Dialect::MySql);
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
        $language = new Language(Dialect::MySql);
        $source = Tree::outer($language->parser()->parse('SAVEPOINT mark'), ['savepoint'])[0];
        $operation = (new OperationReader())->transaction($source);
        self::assertInstanceOf(Savepoint::class, $operation);
        self::assertSame('mark', $operation->name->value);
    }

    public function testIdentifierKeepsTheDecodedName(): void
    {
        $language = new Language(Dialect::MySql);
        $source = Tree::outer($language->parser()->parse('SAVEPOINT mark'), ['ident'])[0];
        self::assertSame('mark', (new OperationReader())->identifier($source)->value);
    }
}
