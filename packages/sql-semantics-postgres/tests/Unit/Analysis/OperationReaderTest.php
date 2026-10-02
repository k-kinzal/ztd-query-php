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
use SqlSemantics\Platform\PostgreSql\Analysis\OperationReader;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Transaction\Postgres\Isolation;
use SqlSemantics\Statement\Transaction\Postgres\Prepare;
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
        $catalog = $platform->catalog(new Language(Dialect::PostgreSql), $platform->searchPath(), false);
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTransactionForms(): iterable
    {
        foreach ((new VersionRegistry())->names('postgresql') as $version) {
            foreach (['BEGIN', 'BEGIN WORK', 'START TRANSACTION'] as $start) {
                foreach (['', ' ISOLATION LEVEL READ UNCOMMITTED', ' ISOLATION LEVEL READ COMMITTED', ' ISOLATION LEVEL REPEATABLE READ', ' ISOLATION LEVEL SERIALIZABLE'] as $isolation) {
                    foreach (['', ' READ ONLY', ' READ WRITE'] as $access) {
                        foreach (['', ' DEFERRABLE', ' NOT DEFERRABLE'] as $deferrable) {
                            $sql = $start . $isolation . $access . $deferrable;
                            yield $version . ':' . $sql => [$version, $sql];
                        }
                    }
                }
            }
            foreach (['COMMIT', 'END', 'ROLLBACK', 'ABORT'] as $verb) {
                foreach (['', ' AND CHAIN', ' AND NO CHAIN'] as $chain) {
                    yield $version . ':' . $verb . $chain => [$version, $verb . ' TRANSACTION' . $chain];
                }
            }
            foreach (['PREPARE TRANSACTION', 'COMMIT PREPARED', 'ROLLBACK PREPARED'] as $verb) {
                foreach (["'id'", "'a''b'", 'E\'a\\\\b\'', '$gid$Mixed Case$gid$', "U&'d!0061ta' UESCAPE '!'", "'a'\n'b'"] as $id) {
                    yield $version . ':' . $verb . $id => [$version, $verb . ' ' . $id];
                }
            }
        }
    }

    #[DataProvider('providerTransactionForms')]
    public function testReadStructuresTransactionFormsForEveryShippedGrammar(string $version, string $sql): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, $version);
        $operation = $semantics->analyze($sql);
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($semantics->analyze($operation->toString())));
    }

    public function testBeginUsesTheLastValueForEachCharacteristic(): void
    {
        $language = new Language(Dialect::PostgreSql);
        $sql = 'BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE, ISOLATION LEVEL READ COMMITTED, NOT DEFERRABLE, READ WRITE';
        $source = Tree::outer($language->parser()->parse($sql), ['TransactionStmtLegacy'])[0];
        $operation = (new OperationReader($language))->begin($source);
        self::assertSame(Isolation::ReadCommitted, $operation->isolation);
        self::assertFalse($operation->readOnly);
        self::assertFalse($operation->deferrable);
    }

    #[TestWith(['COMMIT', false])]
    #[TestWith(['COMMIT AND CHAIN', true])]
    #[TestWith(['COMMIT AND NO CHAIN', false])]
    public function testChainNormalizesOmissionAndExplicitNo(string $sql, bool $expected): void
    {
        $language = new Language(Dialect::PostgreSql);
        $source = Tree::outer($language->parser()->parse($sql), ['TransactionStmt'])[0];
        self::assertSame($expected, (new OperationReader($language))->chain(Tree::child($source, ['opt_transaction_chain'])));
    }

    public function testPreparedKeepsGlobalIdentifierBytesWithoutNameFolding(): void
    {
        $language = new Language(Dialect::PostgreSql);
        $source = Tree::outer($language->parser()->parse('PREPARE TRANSACTION $id$Mixed Case$id$'), ['TransactionStmt'])[0];
        $operation = (new OperationReader($language))->prepared($source);
        self::assertInstanceOf(Prepare::class, $operation);
        self::assertSame('Mixed Case', $operation->identifier->value);
    }

    #[TestWith(['U&"Mi\\0078ed"', 'Mixed'])]
    #[TestWith(['U&"Mi!0078ed" UESCAPE \'!\'', 'Mixed'])]
    public function testIdentifierDecodesUnicodeWithoutFoldingQuotedNames(string $spelling, string $expected): void
    {
        $language = new Language(Dialect::PostgreSql);
        $source = Tree::outer($language->parser()->parse('SAVEPOINT ' . $spelling), ['ColId'])[0];
        self::assertSame($expected, (new OperationReader($language))->identifier($source)->value);
    }

}
