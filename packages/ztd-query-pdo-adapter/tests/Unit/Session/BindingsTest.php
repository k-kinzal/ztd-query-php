<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Session\Bindings;

#[CoversClass(Bindings::class)]
final class BindingsTest extends TestCase
{
    public function testApplyReplaysLatestValuesAndNativeFetchOptions(): void
    {
        $native = new PDO('sqlite::memory:');
        $bindings = new Bindings();
        $bindings->parameter(1, static fn (PDOStatement $statement): bool => $statement->bindValue(1, 7, PDO::PARAM_INT));
        $bindings->parameter(1, static fn (PDOStatement $statement): bool => $statement->bindValue(1, 9, PDO::PARAM_INT));
        $bindings->fetch(static fn (PDOStatement $statement): bool => $statement->setFetchMode(PDO::FETCH_COLUMN, 1));
        $statement = $native->prepare('SELECT 1, ?');
        $bindings->apply($statement);
        self::assertTrue($statement->execute());
        self::assertSame(9, $statement->fetch());
    }

    public function testParameterReferencesKeepTheirCurrentValueAcrossStatements(): void
    {
        $native = new PDO('sqlite::memory:');
        $bindings = new Bindings();
        $value = 4;
        $bindings->parameter(':value', static function (PDOStatement $statement) use (&$value): bool {
            return $statement->bindParam(':value', $value, PDO::PARAM_INT);
        });
        $first = $native->prepare('SELECT :value');
        $bindings->apply($first);
        self::assertTrue($first->execute());
        self::assertSame(4, $first->fetchColumn());
        $value = 8;
        $second = $native->prepare('SELECT :value');
        $bindings->apply($second);
        self::assertTrue($second->execute());
        self::assertSame(8, $second->fetchColumn());
    }

    public function testFetchDefaultsLeaveNativeDefaultsIntact(): void
    {
        $statement = (new PDO('sqlite::memory:'))->prepare('SELECT 3 AS value');
        (new Bindings())->apply($statement);
        self::assertTrue($statement->execute());
        self::assertSame(['value' => 3, 0 => 3], $statement->fetch());
    }
}
