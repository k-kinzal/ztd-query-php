<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Language;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Analysis\OperationReader;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Transaction\MySql\Access;
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
        $catalog = $platform->catalog(new Language(Dialect::MySql), $platform->searchPath(), false);
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTransactionForms(): iterable
    {
        foreach ((new VersionRegistry())->names('mysql') as $version) {
            foreach (['', ' READ ONLY', ' READ WRITE', ' WITH CONSISTENT SNAPSHOT', ' READ ONLY, WITH CONSISTENT SNAPSHOT', ' READ ONLY, READ WRITE', ' READ WRITE, READ ONLY', ' READ ONLY, READ ONLY', ' WITH CONSISTENT SNAPSHOT, WITH CONSISTENT SNAPSHOT'] as $options) {
                yield $version . ':START' . $options => [$version, 'START TRANSACTION' . $options];
            }
            foreach (['COMMIT', 'ROLLBACK'] as $operation) {
                foreach (['', ' AND CHAIN', ' AND NO CHAIN'] as $chain) {
                    foreach (['', ' RELEASE', ' NO RELEASE'] as $release) {
                        yield $version . ':' . $operation . $chain . $release => [$version, $operation . ' WORK' . $chain . $release];
                    }
                }
            }
        }
    }

    #[DataProvider('providerTransactionForms')]
    public function testReadStructuresTransactionChoicesForEveryShippedGrammar(string $version, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $operation = $semantics->analyze($sql);
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($semantics->analyze($operation->toString())));
    }

    #[TestWith(['READ ONLY, WITH CONSISTENT SNAPSHOT, READ ONLY', true, Access::ReadOnly])]
    #[TestWith(['READ WRITE, READ ONLY', false, Access::Conflicting])]
    #[TestWith(['WITH CONSISTENT SNAPSHOT, WITH CONSISTENT SNAPSHOT', true, Access::SessionDefault])]
    public function testStartDerivesRequirementsFromAllCharacteristics(string $options, bool $snapshot, Access $access): void
    {
        $language = new Language(Dialect::MySql);
        $source = Tree::outer($language->parser()->parse('START TRANSACTION ' . $options), ['start'])[0];
        $operation = (new OperationReader())->start($source);
        self::assertSame($snapshot, $operation->consistentSnapshot);
        self::assertSame($access, $operation->access);
    }

    #[TestWith(['COMMIT', 'opt_chain', null])]
    #[TestWith(['COMMIT AND CHAIN', 'opt_chain', true])]
    #[TestWith(['COMMIT AND NO CHAIN', 'opt_chain', false])]
    #[TestWith(['COMMIT RELEASE', 'opt_release', true])]
    #[TestWith(['COMMIT NO RELEASE', 'opt_release', false])]
    public function testCompletionKeepsOmissionDistinctFromExplicitNo(string $sql, string $clause, ?bool $expected): void
    {
        $language = new Language(Dialect::MySql);
        $source = Tree::outer($language->parser()->parse($sql), ['commit'])[0];
        self::assertSame($expected, (new OperationReader())->completion(Tree::child($source, [$clause])));
    }

}
