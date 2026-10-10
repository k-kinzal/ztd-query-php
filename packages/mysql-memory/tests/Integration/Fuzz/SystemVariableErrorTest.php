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
final class SystemVariableErrorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['SET unknown_variable=1', 'SET @@unknown_variable=1', 'SELECT @@unknown_variable', 'SET GLOBAL unknown_variable=1', 'SET SESSION unknown_variable=DEFAULT', 'SET @a=1, unknown_variable=1', 'SET @a=@@unknown_variable', 'SET autocommit=@@unknown_variable', 'SET unknown_variable=@@other_missing', 'SET @@unknown_variable=@@other_missing', 'SET GLOBAL unknown_variable=@@other_missing', 'SET SESSION unknown_variable=@@other_missing', 'SET component.unknown_variable=@@other_missing', 'SET component.unknown_variable=1', 'SELECT @@component.unknown_variable'] as $sql) {
            yield $sql => [$sql];
        }
    }

    #[DataProvider('providerStatements')]
    public function testVariableErrorConditionsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
