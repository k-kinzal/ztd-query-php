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
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;

#[CoversNothing]
#[Medium]
final class ExplainedReadConditionsTest extends TestCase
{
    #[DataProvider('providerConditions')]
    public function testCompleteExplanationExecutesItsReadConditionWithoutSql(string $version, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $statement = $session->analyze($sql);
        self::assertInstanceOf(Explain::class, $statement);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerConditions(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['DESC SELECT *' => 1096, 'EXPLAIN FORMAT=XML SELECT *' => 1791, 'EXPLAIN SELECT 1 UNION SELECT 1, 2' => 1222] as $sql => $code) {
                yield $release->value . ' ' . $sql => [substr($release->value, 6), $sql, $code];
            }
        }
    }
}
