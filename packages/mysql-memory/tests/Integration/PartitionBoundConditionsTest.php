<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversNothing]
#[Medium]
final class PartitionBoundConditionsTest extends TestCase
{
    #[DataProvider('providerBoundConditions')]
    public function testCompleteDefinitionsExecuteWithoutSql(string $version, ?string $database, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: $database);
        $statement = $session->analyze($sql);
        self::assertSame($sql, $statement->toString());
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    /**
     * @return iterable<string, array{string, ?string, string, int}>
     */
    public static function providerBoundConditions(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            $missing = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? 1479 : 1064;
            foreach ([null, 'pbt'] as $database) {
                foreach ([
                    ['ALTER TABLE t PARTITION BY RANGE (i) (PARTITION p)', $missing, false],
                    ['ALTER TABLE t PARTITION BY LIST (i) (PARTITION p)', $missing, false],
                    ['ALTER TABLE t PARTITION BY HASH (i) (PARTITION p VALUES IN (1))', 1480, false],
                    ['ALTER TABLE pbt.t PARTITION BY RANGE (i) (PARTITION p)', $missing, true],
                    ['ALTER TABLE t PARTITION BY RANGE (i) (PARTITION p VALUES LESS THAN (1), PARTITION q)', $missing, false],
                    ['CREATE TABLE t (i INT) PARTITION BY LIST (i) (PARTITION p VALUES LESS THAN (1))', 1480, false],
                ] as $index => [$sql, $code, $qualified]) {
                    yield $release->value . ' ' . ($database ?? 'none') . ' ' . $index => [substr($release->value, 6), $database, $sql, $database === null && !$qualified ? 1046 : $code];
                }
            }
        }
    }
}
