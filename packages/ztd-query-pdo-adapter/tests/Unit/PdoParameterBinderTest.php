<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoParameterBinder;

#[CoversClass(PdoParameterBinder::class)]
final class PdoParameterBinderTest extends TestCase
{
    public function testMapsNativePostgreSqlPositionsWithoutTouchingQuotedText(): void
    {
        $compiled = (new PdoParameterBinder())->compile(
            'SELECT $2, $1, $2, \'$3\' -- $4',
            'pgsql',
            ['first', 'second'],
        );

        self::assertSame(
            'SELECT :__ztd_pdo_2, :__ztd_pdo_1, :__ztd_pdo_2, \'$3\' -- $4',
            $compiled['sql'],
        );
        self::assertSame(
            ['__ztd_pdo_2' => 'second', '__ztd_pdo_1' => 'first'],
            $compiled['params'],
        );
    }

    public function testGivesSqliteParametersNumericStorageClasses(): void
    {
        $compiled = (new PdoParameterBinder())->compile(
            'SELECT ?, :ratio, \'?\', "?"',
            'sqlite',
            [0 => 12, 'ratio' => 2.5],
        );

        self::assertSame(
            'SELECT CAST(? AS INTEGER), CAST(:ratio AS REAL), \'?\', "?"',
            $compiled['sql'],
        );
    }

    public function testBindsExecuteArrayUsingPhpValueTypes(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare('SELECT typeof(?), typeof(?), typeof(?)');
        self::assertInstanceOf(\PDOStatement::class, $statement);

        self::assertTrue((new PdoParameterBinder())->execute($statement, [1, null, '1']));
        self::assertSame(['integer', 'null', 'text'], $statement->fetch(PDO::FETCH_NUM));
    }
}
