<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use ZtdQuery\ResultSelectRunner;
use Tests\Fixtures\MySqlContainer;
use PDO;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

final class ResultSelectRunnerTest extends TestCase
{
    public function testRunStatementFetchesRows(): void
    {
        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $admin = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $admin->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));

        $pdo = null;
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$databaseName;charset=utf8mb4",
                'root',
                'root',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('CREATE TABLE runner_users (id INT PRIMARY KEY, name VARCHAR(255))');
            $pdo->exec("INSERT INTO runner_users (id, name) VALUES (1, 'Alice')");

            $stmt = $pdo->prepare('SELECT id, name FROM runner_users WHERE id = ?');
            $this->assertNotFalse($stmt);

            $runner = new ResultSelectRunner();
            $rows = $runner->runStatement($stmt, [1]);

            $this->assertCount(1, $rows);
            $id = $rows[0]['id'] ?? null;
            if (!is_int($id) && !is_string($id) && !is_float($id)) {
                $this->fail('Expected numeric id value.');
            }
            $this->assertSame(1, (int) $id);
            $name = $rows[0]['name'] ?? null;
            if (!is_string($name)) {
                $this->fail('Expected string name value.');
            }
            $this->assertSame('Alice', $name);
        } finally {
            $pdo = null;
            $admin->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testRunReturnsEmptyOnFalseExecutor(): void
    {
        $runner = new ResultSelectRunner();
        $executor = fn (string $sql): bool => false;
        $rows = $runner->run('SELECT 1', $executor);

        $this->assertSame([], $rows);
    }

    public function testRunReturnsRowsFromExecutor(): void
    {
        $runner = new ResultSelectRunner();
        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $admin = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $admin->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));

        $pdo = null;
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$databaseName;charset=utf8mb4",
                'root',
                'root',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('CREATE TABLE runner_users (id INT PRIMARY KEY, name VARCHAR(255))');
            $pdo->exec("INSERT INTO runner_users (id, name) VALUES (1, 'Alice')");

            $rows = $runner->run('SELECT id, name FROM runner_users WHERE id = 1', fn (string $sql) => $pdo->query($sql));

            $this->assertCount(1, $rows);
            $this->assertSame('Alice', $rows[0]['name']);
            $id = $rows[0]['id'] ?? null;
            if (!is_int($id) && !is_string($id) && !is_float($id)) {
                $this->fail('Expected numeric id value.');
            }
            $this->assertSame(1, (int) $id);
        } finally {
            $pdo = null;
            $admin->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

}
