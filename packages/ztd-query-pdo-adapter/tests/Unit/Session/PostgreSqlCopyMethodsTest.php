<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Adapter\Pdo\ZtdPdoStatement;

#[CoversClass(ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PostgreSqlCopy::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[CoversClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
#[CoversClass(\ZtdQuery\Adapter\Pdo\Session\CopyArguments::class)]
final class PostgreSqlCopyMethodsTest extends TestCase
{
    public function testPgsqlCopyToArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyToArray('users', fields: 'id');
    }

    public function testPgsqlCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyFromArray('users', ["1\tada\n"]);
    }

    public function testPgsqlCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyToFile('users', '/dev/null', fields: 'id');
    }

    public function testPgsqlCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyFromFile('users', '/dev/null', fields: 'id');
    }
}
