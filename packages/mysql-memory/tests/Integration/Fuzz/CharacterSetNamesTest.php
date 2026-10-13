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
final class CharacterSetNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'empty charset' => ['SELECT CONVERT(1 USING ``)'];
        yield 'empty charset with unresolved operand' => ['SELECT CONVERT(missing USING ``)'];
        yield 'unknown charset with unresolved operand' => ['SELECT CONVERT(missing USING `unknown_charset`)'];
        yield 'empty collation' => ['SELECT 1 COLLATE ``'];
        yield 'empty collation with unresolved operand' => ['SELECT missing COLLATE ``'];
        yield 'unknown collation with unresolved operand' => ['SELECT missing COLLATE zz'];
        yield 'empty collation before unresolved operand' => ['SELECT 1 COLLATE `` + missing'];
        yield 'empty collation with unknown database' => ['SELECT 1 COLLATE `` FROM absent.foo'];
    }

    #[DataProvider('providerStatements')]
    public function testNamesMatchTheNativeDiagnostics(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
