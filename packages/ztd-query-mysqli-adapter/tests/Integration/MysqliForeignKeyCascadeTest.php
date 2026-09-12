<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli_result;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

#[CoversNothing]
#[Large]
final class MysqliForeignKeyCascadeTest extends TestCase
{
    public function testForeignKeysValidateAndCascadeUpdatesAndDeletes(): void
    {
        [$databaseName, $mysqli] = MySqlContainer::createTestDatabase();

        try {
            $mysqli->query('CREATE TABLE departments (id INT PRIMARY KEY, name VARCHAR(50)) ENGINE=InnoDB');
            $mysqli->query('CREATE TABLE employees (id INT PRIMARY KEY, department_id INT, '
                . 'CONSTRAINT fk_department FOREIGN KEY (department_id) REFERENCES departments (id) '
                . 'ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB');
            $mysqli->query('CREATE TABLE tasks (id INT PRIMARY KEY, employee_id INT, '
                . 'CONSTRAINT fk_employee FOREIGN KEY (employee_id) REFERENCES employees (id) '
                . 'ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB');
            $ztdMysqli = ZtdMysqli::fromMysqli($mysqli, null);

            self::assertNotFalse($ztdMysqli->query("INSERT INTO departments VALUES (1, 'Engineering')"));
            self::assertNotFalse($ztdMysqli->query('INSERT INTO employees VALUES (10, 1)'));
            self::assertNotFalse($ztdMysqli->query('INSERT INTO tasks VALUES (100, 10)'));

            try {
                $ztdMysqli->query('INSERT INTO employees VALUES (11, 999)');
                self::fail('Expected foreign-key validation to reject the orphan row.');
            } catch (ZtdMysqliException $exception) {
                self::assertStringContainsString("Foreign key constraint 'fk_department' violated", $exception->getMessage());
            }
            $employees = $ztdMysqli->query('SELECT id, department_id FROM employees ORDER BY id');
            self::assertInstanceOf(mysqli_result::class, $employees);
            self::assertSame([['id' => 10, 'department_id' => 1]], $employees->fetch_all(MYSQLI_ASSOC));

            self::assertNotFalse($ztdMysqli->query('UPDATE departments SET id = 2 WHERE id = 1'));
            self::assertNotFalse($ztdMysqli->query('UPDATE employees SET id = 20 WHERE id = 10'));
            $task = $ztdMysqli->query('SELECT employee_id FROM tasks WHERE id = 100');
            self::assertInstanceOf(mysqli_result::class, $task);
            self::assertSame([['employee_id' => 20]], $task->fetch_all(MYSQLI_ASSOC));

            self::assertNotFalse($ztdMysqli->query('DELETE FROM departments WHERE id = 2'));
            $employees = $ztdMysqli->query('SELECT id FROM employees');
            $tasks = $ztdMysqli->query('SELECT id FROM tasks');
            self::assertInstanceOf(mysqli_result::class, $employees);
            self::assertInstanceOf(mysqli_result::class, $tasks);
            self::assertSame([], $employees->fetch_all(MYSQLI_ASSOC));
            self::assertSame([], $tasks->fetch_all(MYSQLI_ASSOC));

            $physical = $mysqli->query('SELECT '
                . '(SELECT COUNT(*) FROM departments) + '
                . '(SELECT COUNT(*) FROM employees) + '
                . '(SELECT COUNT(*) FROM tasks) AS row_count');
            self::assertInstanceOf(mysqli_result::class, $physical);
            self::assertSame([['row_count' => '0']], $physical->fetch_all(MYSQLI_ASSOC));
        } finally {
            $mysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
