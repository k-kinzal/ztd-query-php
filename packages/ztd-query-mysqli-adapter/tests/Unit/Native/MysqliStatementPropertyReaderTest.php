<?php

declare(strict_types=1);

namespace Tests\Unit\Native;

use mysqli;
use mysqli_stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use Tests\Container\MySql80Container;
use Tests\Container\MySql84Container;
use ZtdQuery\Adapter\Mysqli\Native\MysqliStatementPropertyReader;

#[CoversClass(MysqliStatementPropertyReader::class)]
#[Large]
final class MysqliStatementPropertyReaderTest extends TestCase
{
    public function testReadPreservesPreparedMetadataAndBufferedRowCounts(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $statement = $connection->prepare('SELECT ? AS value UNION ALL SELECT ?');
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $reader = new MysqliStatementPropertyReader();
            self::assertSame(2, $reader->read($statement, 'param_count'));
            self::assertSame(1, $reader->read($statement, 'field_count'));
            self::assertSame($statement->id, $reader->read($statement, 'id'));
            self::assertTrue($statement->execute([7, 8]));
            self::assertTrue($statement->store_result());
            self::assertSame(2, $reader->read($statement, 'num_rows'));
            self::assertSame(2, $reader->read($statement, 'affected_rows'));
            self::assertSame(0, $reader->read($statement, 'errno'));
            self::assertSame('', $reader->read($statement, 'error'));
            self::assertSame([], $reader->read($statement, 'error_list'));
            self::assertSame('00000', $reader->read($statement, 'sqlstate'));
            self::assertNull($reader->read($statement, 'unsupported'));
            $statement->close();
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testReadPreservesGeneratedIdsAndWriteCounts(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $connection->query('CREATE TABLE generated_rows (id INT AUTO_INCREMENT PRIMARY KEY, value INT)');
            $statement = $connection->prepare('INSERT INTO generated_rows (value) VALUES (?), (?)');
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            self::assertTrue($statement->execute([7, 8]));
            $reader = new MysqliStatementPropertyReader();
            self::assertSame(1, $reader->read($statement, 'insert_id'));
            self::assertSame(2, $reader->read($statement, 'affected_rows'));
            self::assertSame(0, $reader->read($statement, 'field_count'));
            self::assertSame(0, $reader->read($statement, 'num_rows'));
            $statement->close();
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testReadPreservesNativeFailureDetails(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $connection->query('CREATE TABLE unique_rows (id INT PRIMARY KEY)');
            $statement = $connection->prepare('INSERT INTO unique_rows VALUES (?)');
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            self::assertTrue($statement->execute([1]));
            mysqli_report(MYSQLI_REPORT_OFF);
            try {
                self::assertFalse($statement->execute([1]));
                $reader = new MysqliStatementPropertyReader();
                self::assertSame(1062, $reader->read($statement, 'errno'));
                self::assertSame('23000', $reader->read($statement, 'sqlstate'));
                self::assertStringContainsString('Duplicate entry', $statement->error);
                self::assertSame($statement->error, $reader->read($statement, 'error'));
                self::assertSame([['errno' => 1062, 'sqlstate' => '23000', 'error' => $statement->error]], $reader->read($statement, 'error_list'));
            } finally {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                $statement->close();
                $connection->close();
            }
        } finally {
            $container->stop();
        }
    }
}
