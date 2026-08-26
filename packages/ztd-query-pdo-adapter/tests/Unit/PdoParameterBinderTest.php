<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\PdoParameterType;

#[CoversClass(PdoParameterBinder::class)]
#[UsesClass(PdoParameterType::class)]
final class PdoParameterBinderTest extends TestCase
{
    public function testBindsExecuteArrayUsingPhpValueTypes(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare('SELECT typeof(?), typeof(?), typeof(?)');
        self::assertInstanceOf(PDOStatement::class, $statement);

        self::assertTrue((new PdoParameterBinder())->execute($statement, [1, null, '1']));
        self::assertSame(['integer', 'null', 'text'], $statement->fetch(PDO::FETCH_NUM));
    }

    public function testBindsEveryPhpValueTypeAndNormalizesNamedParameters(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare(
            'SELECT typeof(?), typeof(?), typeof(?), typeof(?), typeof(:name), typeof(:ratio)',
        );
        self::assertInstanceOf(PDOStatement::class, $statement);
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
