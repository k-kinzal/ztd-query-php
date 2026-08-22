<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\PdoParameterType;
use ZtdQuery\Platform\SqlPlaceholderEscaper;

#[CoversClass(PdoParameterBinder::class)]
#[UsesClass(PdoParameterType::class)]
final class PdoParameterBinderTest extends TestCase
{
    public function testMapsNativePostgreSqlPositionsWithoutTouchingQuotedText(): void
    {
        $compiled = (new PdoParameterBinder())->compile(
            'SELECT $2, $1, $2, \'$3\' FROM docs WHERE meta ? $1 -- $4',
            'pgsql',
            ['first', 'second'],
            new class () implements SqlPlaceholderEscaper {
                public function escape(string $sql): string
                {
                    return str_replace('meta ? ', 'meta ?? ', $sql);
                }
            },
        );

        self::assertSame(
            'SELECT :__ztd_pdo_2, :__ztd_pdo_1, :__ztd_pdo_2, \'$3\' FROM docs WHERE meta ?? :__ztd_pdo_1 -- $4',
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
            'SELECT ?, ?, :ratio, :integer, \'?\', "?"',
            'sqlite',
            [0 => 12, 1 => 2.5, 'ratio' => 2.5, ':integer' => 9],
        );

        self::assertSame(
            'SELECT CAST(? AS INTEGER), CAST(? AS REAL), CAST(:ratio AS REAL), CAST(:integer AS INTEGER), \'?\', "?"',
            $compiled['sql'],
        );
    }

    public function testLeavesUnboundParametersAndOtherDriversUnchanged(): void
    {
        $binder = new PdoParameterBinder();

        self::assertSame(
            ['sql' => 'SELECT ?, :missing', 'params' => null],
            $binder->compile('SELECT ?, :missing', 'sqlite', null),
        );
        self::assertSame(
            ['sql' => 'SELECT ?, :missing', 'params' => []],
            $binder->compile('SELECT ?, :missing', 'sqlite', []),
        );
        self::assertSame(
            ['sql' => "SELECT meta ? 'key'", 'params' => ['key']],
            $binder->compile("SELECT meta ? 'key'", 'mysql', ['key']),
        );
        self::assertSame(
            ['sql' => 'SELECT ?', 'params' => [1]],
            $binder->compile('SELECT ?', 'pgsql', [1]),
        );
        self::assertSame(
            ['sql' => 'SELECT $0, :__ztd_pdo_1', 'params' => ['__ztd_pdo_1' => 1]],
            $binder->compile('SELECT $0, $1', 'pgsql', [1]),
        );
        self::assertSame(
            ['sql' => 'SELECT :__ztd_pdo_1', 'params' => null],
            $binder->compile('SELECT $1', 'pgsql', null),
        );
        self::assertSame(
            ['sql' => 'SELECT $1', 'params' => [1]],
            $binder->compile('SELECT $1', 'mysql', [1]),
        );
        self::assertSame(
            ['sql' => 'SELECT ?', 'params' => [7]],
            $binder->compile('SELECT ?', 'mysql', [7]),
        );
        self::assertSame(
            ['sql' => 'SELECT ?, :__ztd_pdo_1', 'params' => ['__ztd_pdo_1' => 1]],
            $binder->compile('SELECT ?, $1', 'pgsql', [1]),
        );
        self::assertSame(
            ['sql' => 'SELECT :missing, CAST(:value AS INTEGER)', 'params' => ['value' => 7]],
            $binder->compile('SELECT :missing, :value', 'sqlite', ['value' => 7]),
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

    public function testBindsEveryPhpValueTypeAndNormalizesNamedParameters(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare(
            'SELECT typeof(?), typeof(?), typeof(?), typeof(?), typeof(:name), typeof(:ratio)',
        );
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        self::assertTrue((new PdoParameterBinder())->execute($statement, [
            null,
            true,
            7,
            $resource,
            'name' => 'text',
            ':ratio' => 2.5,
        ]));

        self::assertSame(
            ['null', 'integer', 'integer', 'blob', 'text', 'text'],
            $statement->fetch(PDO::FETCH_NUM),
        );
        fclose($resource);
    }
}
