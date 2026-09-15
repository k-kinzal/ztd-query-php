<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class ForeignKeyCascadeTest extends TestCase
{
    public function testForeignKeysValidateAndCascadeUpdatesAndDeletes(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE departments (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY, department_id INTEGER, '
            . 'CONSTRAINT fk_department FOREIGN KEY (department_id) REFERENCES departments (id) '
            . 'ON DELETE CASCADE ON UPDATE CASCADE)');
        $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, employee_id INTEGER, '
            . 'CONSTRAINT fk_employee FOREIGN KEY (employee_id) REFERENCES employees (id) '
            . 'ON DELETE CASCADE ON UPDATE CASCADE)');
        $ztdPdo = ZtdPdo::fromPdo($pdo);

        self::assertSame(1, $ztdPdo->exec("INSERT INTO departments VALUES (1, 'Engineering')"));
        self::assertSame(1, $ztdPdo->exec('INSERT INTO employees VALUES (10, 1)'));
        self::assertSame(1, $ztdPdo->exec('INSERT INTO tasks VALUES (100, 10)'));

        try {
            $ztdPdo->exec('INSERT INTO employees VALUES (11, 999)');
            self::fail('Expected foreign-key validation to reject the orphan row.');
        } catch (ZtdPdoException $exception) {
            self::assertStringContainsString("Foreign key constraint 'fk_department' violated", $exception->getMessage());
        }
        $employees = $ztdPdo->query('SELECT id, department_id FROM employees ORDER BY id');
        self::assertNotFalse($employees);
        self::assertSame([['id' => 10, 'department_id' => 1]], $employees->fetchAll());

        self::assertSame(1, $ztdPdo->exec('UPDATE departments SET id = 2 WHERE id = 1'));
        self::assertSame(1, $ztdPdo->exec('UPDATE employees SET id = 20 WHERE id = 10'));
        $task = $ztdPdo->query('SELECT employee_id FROM tasks WHERE id = 100');
        self::assertNotFalse($task);
        self::assertSame(20, $task->fetchColumn());

        self::assertSame(1, $ztdPdo->exec('DELETE FROM departments WHERE id = 2'));
        $orphans = $ztdPdo->query('SELECT employees.id FROM employees LEFT JOIN departments '
            . 'ON employees.department_id = departments.id WHERE departments.id IS NULL');
        self::assertNotFalse($orphans);
        self::assertSame([], $orphans->fetchAll());
        $tasks = $ztdPdo->query('SELECT id FROM tasks');
        self::assertNotFalse($tasks);
        self::assertSame([], $tasks->fetchAll());

        $physical = $pdo->query('SELECT (SELECT COUNT(*) FROM departments) '
            . '+ (SELECT COUNT(*) FROM employees) + (SELECT COUNT(*) FROM tasks)');
        self::assertNotFalse($physical);
        self::assertSame(0, (int) $physical->fetchColumn());
    }
}
