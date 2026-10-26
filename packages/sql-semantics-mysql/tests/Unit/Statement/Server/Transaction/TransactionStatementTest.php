<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;
use SqlSemantics\Validation\Equivalence;

#[CoversClass(TransactionStatement::class)]
#[Medium]
final class TransactionStatementTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testAnalysisPublishesTheConcreteStatementAndItsSelectedLanguage(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $statement = $semantics->analyze($sql);
        self::assertInstanceOf(TransactionStatement::class, $statement);
        self::assertSame($release, $statement->profile()->grammar);
        self::assertSame([], $statement->declarations());
        self::assertFalse(property_exists($statement, 'statement'));
        self::assertFalse(property_exists($statement, 'facts'));
        self::assertFalse(property_exists($statement, 'context'));
        self::assertNull((new Equivalence())->difference($statement, $semantics->analyze($statement->toString())));
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['BEGIN WORK', 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT', 'COMMIT AND NO CHAIN NO RELEASE', 'ROLLBACK WORK AND CHAIN', 'SAVEPOINT `s p`', 'ROLLBACK WORK TO `s p`', 'RELEASE SAVEPOINT `s p`', 'SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE, READ WRITE', "XA START 'g', 'b', 7", "XA END 'g', 'b', 7", "XA PREPARE 'g', 'b', 7", "XA COMMIT 'g', 'b', 7 ONE PHASE", "XA ROLLBACK 'g', 'b', 7", 'XA RECOVER'] as $sql) {
                yield $release->value . ':' . $sql => [$release, $sql];
            }
        }
    }
}
