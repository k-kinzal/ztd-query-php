<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Lifecycle;
use Fuzz\Target\Servers;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class LifecycleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLifecycleStatements(): iterable
    {
        yield 'shutdown' => ['SHUTDOWN'];
        yield 'restart' => ['RESTART'];
    }

    /**
     * @throws JsonException When an observation cannot be serialized
     */
    #[DataProvider('providerLifecycleStatements')]
    public function testCompareChecksCompletionAndTheServerTransition(string $sql): void
    {
        $comparison = (new Lifecycle())->compare($sql, (new Servers())->environment('MYSQL_VERSION', '8.4.7'));

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
