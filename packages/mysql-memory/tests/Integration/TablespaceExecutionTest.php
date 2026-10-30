<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class TablespaceExecutionTest extends TestCase
{
    #[DataProvider('providerReads')]
    public function testDetachedStorageIsRefusedFromTheAnalyzedStatement(string $version, string $sql): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY); INSERT INTO t VALUES(1)');
        $discard = $session->analyze('ALTER TABLE t DISCARD TABLESPACE');
        $read = $session->analyze($sql);
        $session->text = '';
        $execution = new Execution($session);
        $execution->statement($discard);
        $this->expectExceptionCode(1814);
        $execution->statement($read);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerReads(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $version) {
            foreach (['SELECT * FROM t', 'SELECT * FROM t WHERE id=1', 'UPDATE t SET id=2', 'DELETE FROM t'] as $sql) {
                yield $version . ' ' . $sql => [$version, $sql];
            }
        }
    }
}
