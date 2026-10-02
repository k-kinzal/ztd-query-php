<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Query\SqliteLimit;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(SqliteLimit::class)]
#[Small]
final class SqliteLimitTest extends TestCase
{
    #[TestWith([false])]
    #[TestWith([true])]
    public function testToStringKeepsCountAndSkipRolesAcrossBothNotations(bool $comma): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $limit = new SqliteLimit($scope, new SqliteInteger(new UnsignedInteger('2')), new SqliteInteger(new UnsignedInteger('1')), $comma);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER); INSERT INTO bar VALUES(1),(2),(3),(4)');
        $result = $db->query('SELECT foo FROM bar ' . $limit->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([2, 3], $result->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testToStringRetainsNegativeCountAndOffsetMeaningWithoutExecutingThem(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $negative = new SqliteInteger(new UnsignedInteger('1'), true);
        $limit = new SqliteLimit($scope, $negative, $negative);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER); INSERT INTO bar VALUES(1),(2),(3)');
        $result = $db->query('SELECT foo FROM bar ' . $limit->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([1, 2, 3], $result->fetchAll(PDO::FETCH_COLUMN));
    }
}
