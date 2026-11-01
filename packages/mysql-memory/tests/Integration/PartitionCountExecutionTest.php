<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversNothing]
#[Medium]
final class PartitionCountExecutionTest extends TestCase
{
    #[DataProvider('providerCounts')]
    public function testIntegerSyntaxPrecedesXaAndTableResolution(string $version, string $command, string $number, string $table): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query("CREATE TABLE t(a INT) PARTITION BY HASH(a) PARTITIONS 2; XA BEGIN 'x'");
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("Only integers allowed as number here near '" . $number . "' at line 1");
        $session->query('ALTER TABLE ' . $table . ' ' . $command . ' ' . $number);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerCounts(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['COALESCE PARTITION', 'ADD PARTITION PARTITIONS'] as $command) {
                foreach (['1.0', '1e0', '18446744073709551616'] as $number) {
                    foreach (['t', 'missing_t', 'missing_db.t'] as $table) {
                        yield $release->value . ':' . $command . ':' . $number . ':' . $table => [substr($release->value, 6), $command, $number, $table];
                    }
                }
            }
        }
    }
}
