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
final class RoleLexicalBoundaryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['SET ROLE mm_lexical_probe.invalid', "SET ROLE 'mm_lexical_probe.invalid'", 'SET ROLE `mm_lexical_probe.invalid`', 'SET ROLE @mm_lexical_probe.invalid', "SET ROLE 'mm_lexical_probe'@invalid.example", 'SET ROLE mm_lexical_probe@invalid.example', 'SET ROLE /* token boundary */ mm_lexical_probe.invalid', "SET ROLE 'mm_lexical_probe.invalid'@'invalid.example'"] as $sql) {
            yield $sql => [$sql];
        }
    }

    #[DataProvider('providerStatements')]
    public function testRoleNameAndHostnameBoundariesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
