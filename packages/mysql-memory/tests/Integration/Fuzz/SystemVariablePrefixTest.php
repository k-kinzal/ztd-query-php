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
final class SystemVariablePrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['SET unknown_prefix', 'SET unknown_prefix 1', 'SET `unknown_prefix` 1', 'SET GLOBAL unknown_prefix 1', 'SET SESSION unknown_prefix 1', 'SET LOCAL unknown_prefix 1', 'SET @@unknown_prefix 1', 'SET @@SESSION.unknown_prefix 1', 'SET component.unknown_prefix 1', 'SET autocommit 1', 'SET @unknown_prefix 1', 'SET unknown_prefix=', 'SET unknown_prefix=(', 'SET @x=1,unknown_prefix 1', 'SET @x=@@another_missing,unknown_prefix 1', 'SET /* before target */ unknown_prefix /* after target */ 1'] as $sql) {
            yield $sql => [$sql];
        }
    }

    #[DataProvider('providerStatements')]
    public function testAssignmentTargetResolutionPrecedesLaterSyntaxErrors(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
