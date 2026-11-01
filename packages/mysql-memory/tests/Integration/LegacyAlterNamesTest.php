<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class LegacyAlterNamesTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testADottedTargetExecutesFromItsSemanticName(string $version): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $session->query('CREATE TABLE t(i INT)');
        $statement = $session->analyze('ALTER TABLE .t ADD COLUMN j INT');
        self::assertSame('ALTER TABLE .t ADD COLUMN j INT', $statement->toString());
        $session->text = '';
        (new Execution($session))->statement($statement);
        $session->query('INSERT INTO t VALUES (1, 2)');
        $rows = $session->query('SELECT i, j FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', '2']], $rows->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        yield 'MySQL 5.6' => ['5.6.51'];
        yield 'MySQL 5.7' => ['5.7.44'];
    }
}
