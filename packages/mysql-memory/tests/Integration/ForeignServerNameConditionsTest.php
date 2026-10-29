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
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;

#[CoversNothing]
#[Medium]
final class ForeignServerNameConditionsTest extends TestCase
{
    #[DataProvider('providerEmptyNames')]
    public function testEmptyNamesRemainCompleteRequestsForDirectExecution(string $version, string $sql): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze($sql);
        self::assertInstanceOf(CreateServer::class, $statement);
        self::assertSame($sql, $statement->toString());
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        (new Execution($session))->statement($statement);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerEmptyNames(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach ([
                "CREATE SERVER '' FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h')",
                "CREATE SERVER '' FOREIGN DATA WRAPPER _sqlfaker_identifier OPTIONS (USER 'a''b')",
                "CREATE SERVER '' FOREIGN DATA WRAPPER '😀' OPTIONS (USER '😀')",
                "CREATE SERVER '' FOREIGN DATA WRAPPER mysql OPTIONS (PORT 1.5)",
                "CREATE SERVER '' FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h', HOST 'q')",
            ] as $index => $sql) {
                yield $release->value . ' ' . $index => [substr($release->value, 6), $sql];
            }
        }
    }
}
