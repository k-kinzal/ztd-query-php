<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Session\ParameterBinder;
use ZtdQuery\Adapter\Pdo\Session\ParameterKind;

#[CoversClass(ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class ParameterBinderTest extends TestCase
{
    public function testExecuteBindsEachValueAsTheKindPdoReadsItAs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare('SELECT typeof(?), typeof(?), typeof(?)');
        self::assertNotFalse($statement);

        self::assertTrue((new ParameterBinder())->execute($statement, [1, null, '1']));
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

        self::assertTrue((new ParameterBinder())->execute($statement, [
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

        self::assertTrue((new ParameterBinder())->execute($statement, null));
    }

    public function testParameterNameWritesAPlainNameAsAPlaceholder(): void
    {
        self::assertSame(':name', (new ParameterBinder())->parameterName('name'));
    }

    public function testParameterNameLeavesANameThatIsAlreadyAPlaceholderAlone(): void
    {
        self::assertSame(':name', (new ParameterBinder())->parameterName(':name'));
    }
}
