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
final class XaIdentifierConditionsTest extends TestCase
{
    #[DataProvider('providerRefusedIdentifiers')]
    public function testCompleteStatementsRetainIdentifierConditionsForDirectExecution(string $version, string $sql): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze($sql);
        self::assertSame($sql, $statement->toString());
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        (new Execution($session))->statement($statement);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRefusedIdentifiers(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            $parts = [
                'global' => "'" . str_repeat('g', 65) . "'",
                'branch' => "'g', '" . str_repeat('b', 65) . "'",
                'raw controls' => "'\x07" . str_repeat("\x01", 128) . "'",
            ];
            if ($release !== GrammarRelease::MySql5651) {
                $parts['format'] = "'g', 'b', 18446744073709551616";
            }
            foreach (['START', 'END', 'PREPARE', 'COMMIT', 'ROLLBACK'] as $command) {
                foreach ($parts as $part => $identifier) {
                    yield $release->value . ' ' . $command . ' ' . $part => [substr($release->value, 6), 'XA ' . $command . ' ' . $identifier];
                }
            }
        }
    }
}
