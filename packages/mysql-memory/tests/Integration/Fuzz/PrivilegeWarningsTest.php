<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class PrivilegeWarningsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSuper(): iterable
    {
        yield 'database level' => ['GRANT SUPER ON * TO CURRENT_USER'];
        yield 'missing grantee' => ['GRANT SUPER ON *.* TO definitely_missing'];
        yield 'missing revokee' => ['REVOKE SUPER ON *.* FROM definitely_missing'];
        yield 'repeated privilege' => ['GRANT SUPER,SUPER ON *.* TO definitely_missing'];
        yield 'mixed privileges' => ['GRANT SELECT,SUPER ON * TO CURRENT_USER'];
        yield 'table level' => ['GRANT SUPER ON t1 TO CURRENT_USER'];
        yield 'role grant syntax' => ['GRANT SUPER TO CURRENT_USER'];
        yield 'role revoke syntax' => ['REVOKE SUPER FROM CURRENT_USER'];
        yield 'implicit all privileges' => ['GRANT ALL ON * TO definitely_missing'];
    }

    #[DataProvider('providerSuper')]
    public function testWarningsPrecedePrivilegeErrors(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
