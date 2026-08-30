<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\Driver\PdoParameterKind;

#[CoversClass(PdoParameterBinder::class)]
#[UsesClass(PdoParameterKind::class)]
final class PdoParameterBinderTest extends TestCase
{
    public function testExecuteBindsEachValueAsTheKindPdoReadsItAs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare('SELECT typeof(?), typeof(?), typeof(?)');
        self::assertNotFalse($statement);

        self::assertTrue((new PdoParameterBinder())->execute($statement, [1, null, '1']));
        self::assertSame(['integer', 'null', 'text'], $statement->fetch(PDO::FETCH_NUM));
    }

    public function testExecuteBindsEveryKindAndNamesEveryNamedPlaceholder(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare(
            'SELECT typeof(?), typeof(?), typeof(?), typeof(?), typeof(:name), typeof(:ratio)',
        );
        self::assertNotFalse($statement);
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
    public function testExecuteRunsWhatIsAlreadyBoundWhereItIsGivenNothing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare('SELECT 1');
        self::assertNotFalse($statement);

        self::assertTrue((new PdoParameterBinder())->execute($statement, null));
    }

    public function testParameterNameWritesAPlainNameAsAPlaceholder(): void
    {
        self::assertSame(':name', (new PdoParameterBinder())->parameterName('name'));
    }

    public function testParameterNameLeavesANameThatIsAlreadyAPlaceholderAlone(): void
    {
        self::assertSame(':name', (new PdoParameterBinder())->parameterName(':name'));
    }
}
