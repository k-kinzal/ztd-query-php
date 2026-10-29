<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ViewReadConditionTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testCompleteViewValuesRetainTheirReadConditions(string $version, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE VIEW existing AS SELECT 1 AS id');
        $statement = $session->analyze($sql);
        self::assertSame($sql, $statement->toString());
        $session->text = 'No SQL is available to execute this view definition';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach ([
                'CREATE VIEW v AS SELECT *' => 1096,
                'CREATE OR REPLACE ALGORITHM = UNDEFINED VIEW existing AS SELECT *' => 1096,
                'ALTER VIEW absent AS SELECT *' => 1146,
                'ALTER VIEW existing AS SELECT *' => 1096,
                'CREATE VIEW v AS SELECT 1 UNION SELECT 1, 2' => 1222,
            ] as $sql => $code) {
                yield "$version $sql" => [$version, $sql, $code];
            }
        }
    }
}
