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
final class SpatialDefaultTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $cases = [
         'omitted' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); INSERT %s INTO spatial_defaults VALUES()',
         'explicit default' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); INSERT %s INTO spatial_defaults VALUES(DEFAULT)',
         'explicit null' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); INSERT %s INTO spatial_defaults VALUES(NULL)',
         'null rows' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); INSERT %s INTO spatial_defaults VALUES(NULL),(NULL)',
         'select null' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); INSERT %s INTO spatial_defaults SELECT NULL',
         'update null' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); INSERT INTO spatial_defaults VALUES(Point(1,2)); UPDATE %s spatial_defaults SET a=NULL',
         'update default' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); INSERT INTO spatial_defaults VALUES(Point(1,2)); UPDATE %s spatial_defaults SET a=DEFAULT',
         'nullable dropped default' => 'CREATE TABLE spatial_defaults(a POINT); ALTER TABLE spatial_defaults ALTER a DROP DEFAULT; INSERT %s INTO spatial_defaults VALUES(DEFAULT)',
         'update nullable dropped default' => 'CREATE TABLE spatial_defaults(a POINT); INSERT INTO spatial_defaults VALUES(Point(1,2)); ALTER TABLE spatial_defaults ALTER a DROP DEFAULT; UPDATE %s spatial_defaults SET a=DEFAULT',
         'default expression' => 'CREATE TABLE spatial_defaults(a POINT NOT NULL); SELECT DEFAULT(a) FROM spatial_defaults',
        ];
        foreach (['', 'STRICT_TRANS_TABLES'] as $mode) {
            foreach ($cases as $name => $pattern) {
                foreach (['', 'IGNORE'] as $ignore) {
                    yield $mode . ' ' . $name . ' ' . $ignore => ["SET sql_mode='" . $mode . "'; " . sprintf($pattern, $ignore) . '; SHOW WARNINGS; SELECT HEX(a) FROM spatial_defaults'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testSpatialDefaultsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
