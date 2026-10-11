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
final class RetainedTableReadTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $actions = [
            'cold' => '',
            'read nonnull' => 'SELECT * FROM retained_table_read WHERE id=1;',
            'read null' => 'SELECT * FROM retained_table_read WHERE id=2;',
            'missing after read' => 'SELECT * FROM retained_table_read WHERE id=1; SELECT * FROM retained_table_read WHERE id=99;',
            'scan filtered' => 'SELECT * FROM retained_table_read WHERE n=99;',
            'unchanged update' => 'UPDATE retained_table_read SET n=n WHERE id=1;',
            'changed update' => 'UPDATE retained_table_read SET n=NULL WHERE id=1;',
            'replace' => 'REPLACE INTO retained_table_read VALUES (1,NULL);',
            'duplicate unchanged' => 'INSERT INTO retained_table_read VALUES (1,10) ON DUPLICATE KEY UPDATE n=n;',
            'duplicate update' => 'INSERT INTO retained_table_read VALUES (1,10) ON DUPLICATE KEY UPDATE n=NULL;',
            'rollback' => 'START TRANSACTION; UPDATE retained_table_read SET n=NULL WHERE id=1; ROLLBACK;',
            'delete null' => 'DELETE FROM retained_table_read WHERE id=2;',
            'flush' => 'SELECT * FROM retained_table_read WHERE id=2; FLUSH TABLES retained_table_read;',
        ];
        foreach ($actions as $name => $action) {
            yield $name => ['CREATE TABLE retained_table_read (id INT PRIMARY KEY,n INT); INSERT INTO retained_table_read VALUES (1,10),(2,NULL); ' . $action . ' SELECT (SELECT id,NULL FROM retained_table_read WHERE 0) <=> (NULL,NULL) AS id_state, (SELECT n,NULL FROM retained_table_read WHERE 0) <=> (NULL,NULL) AS nullable_state, (SELECT n+1,NULL FROM retained_table_read WHERE 0) <=> (NULL,NULL) AS computed_state; DROP TABLE retained_table_read'];
        }
        foreach ($actions as $name => $action) {
            yield $name . ' one handle' => ['CREATE TABLE retained_table_read (id INT PRIMARY KEY,n INT); INSERT INTO retained_table_read VALUES (1,10),(2,NULL); ' . $action . ' SELECT (SELECT n,NULL FROM retained_table_read WHERE 0) <=> (NULL,NULL) AS nullable_state; DROP TABLE retained_table_read'];
        }
        yield 'unused occurrence reserves a handle' => ['CREATE TABLE retained_table_read (id INT PRIMARY KEY,n INT); INSERT INTO retained_table_read VALUES (1,10),(2,NULL); SELECT CASE WHEN 0 THEN (SELECT n,NULL FROM retained_table_read WHERE 0) <=> (NULL,NULL) ELSE 9 END, (SELECT n,NULL FROM retained_table_read WHERE 0) <=> (NULL,NULL); DROP TABLE retained_table_read'];
        yield 'outer occurrence reserves a handle' => ['CREATE TABLE retained_table_read (id INT PRIMARY KEY,n INT); INSERT INTO retained_table_read VALUES (1,10),(2,NULL); SELECT n,(SELECT n,NULL FROM retained_table_read WHERE 0) <=> (NULL,NULL) FROM retained_table_read WHERE id=1; DROP TABLE retained_table_read'];
        foreach (['WHERE id=99', 'WHERE id=99 AND 0', 'WHERE id=1 AND 0', 'LIMIT 0', 'ORDER BY id DESC LIMIT 1 OFFSET 2', 'ORDER BY n ASC LIMIT 1 OFFSET 2', 'ORDER BY n DESC LIMIT 1 OFFSET 2', 'ORDER BY n+0 ASC LIMIT 1 OFFSET 2', 'WHERE n=99'] as $clause) {
            yield $clause => ['CREATE TABLE retained_table_read (id INT PRIMARY KEY,n INT); INSERT INTO retained_table_read VALUES (1,10),(2,NULL); SELECT (SELECT id,n FROM retained_table_read ' . $clause . ') <=> (NULL,NULL) AS both_null, (SELECT n,NULL FROM retained_table_read ' . $clause . ') <=> (NULL,NULL) AS nullable_state; DROP TABLE retained_table_read'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testRetainedTableFieldsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
