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
final class EmptyRowBufferTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $actions = [
            'inserted null' => '',
            'read nonnull' => 'SELECT * FROM empty_row_buffer WHERE id=1;',
            'read null' => 'SELECT * FROM empty_row_buffer WHERE id=2;',
            'scan' => 'SELECT * FROM empty_row_buffer;',
            'missing key' => 'SELECT * FROM empty_row_buffer WHERE id=99;',
            'updated nonnull' => 'UPDATE empty_row_buffer SET n=20 WHERE id=1;',
            'deleted nonnull' => 'DELETE FROM empty_row_buffer WHERE id=1;',
            'inserted nonnull' => 'INSERT INTO empty_row_buffer VALUES (3,30);',
            'flushed' => 'FLUSH TABLES empty_row_buffer;',
            'flushed after read' => 'SELECT * FROM empty_row_buffer WHERE id=2; FLUSH TABLES empty_row_buffer;',
        ];
        foreach (['persistent' => '', 'temporary' => 'TEMPORARY '] as $storage => $temporary) {
            foreach ($actions as $name => $action) {
                yield $storage . ' ' . $name => ['CREATE ' . $temporary . 'TABLE empty_row_buffer (id INT PRIMARY KEY,n INT NULL); INSERT INTO empty_row_buffer VALUES (1,10),(2,NULL); ' . $action . ' SELECT (SELECT n,NULL FROM empty_row_buffer WHERE 0) <=> (NULL,NULL) AS null_state; DROP TABLE empty_row_buffer'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptyProjectionRetainsTheTableReadState(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
