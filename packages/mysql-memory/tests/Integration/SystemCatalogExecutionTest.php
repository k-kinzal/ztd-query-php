<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class SystemCatalogExecutionTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testMetadataAndZeroLimitDoNotRequireUnrequestedRows(string $version): void
    {
        $session = (new Instance($version))->connect();
        $result = $session->query('SELECT THREAD_ID FROM performance_schema.threads LIMIT 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(1, $result->columns);
        self::assertSame('THREAD_ID', $result->columns[0]->name);
        self::assertSame([], $result->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            yield $version => [$version];
        }
    }

}
