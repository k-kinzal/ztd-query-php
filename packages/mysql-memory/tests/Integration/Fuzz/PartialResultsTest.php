<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\PartialResultObservation;
use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class PartialResultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerClients(): iterable
    {
        $queries = [
            'first row fails' => "SELECT JSON_EXTRACT('invalid','$')",
            'regular expression' => "SELECT 'abc' REGEXP 'a{2,1}'",
            'like escape' => "SELECT (b NOT LIKE 'a' ESCAPE USER()) IS NULL FROM (SELECT 'a' AS b) AS t",
            'constant overflow' => 'SELECT 1+9223372036854775807',
            'legacy pattern encoding' => 'SELECT _latin1 0xE9 REGEXP _latin1 0xE9',
            'later row fails' => "SELECT JSON_EXTRACT(v,'$') FROM (SELECT '1' AS v UNION ALL SELECT 'invalid') AS t",
            'integer overflow' => "SELECT CAST(v AS UNSIGNED)+18446744073709551615 FROM (SELECT '0' AS v UNION ALL SELECT '1') AS t",
        ];
        foreach ($queries as $name => $sql) {
            foreach ([false, true] as $buffered) {
                foreach ([false, true] as $prepared) {
                    yield $name . ($buffered ? ' buffered' : ' streaming') . ($prepared ? ' prepared' : ' query') => [$sql, $buffered, $prepared];
                }
            }
        }
    }

    #[DataProvider('providerClients')]
    public function testRowsPrecedeTheErrorForStreamingClients(string $sql, bool $buffered, bool $prepared): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $expected = PartialResultObservation::observe($target->native, $target->nativeUser, $target->nativePassword, $sql, $buffered, $prepared);
        $actual = PartialResultObservation::observe($target->memory, 'root', '', $sql, $buffered, $prepared);

        self::assertSame($expected, $actual);
    }
}
