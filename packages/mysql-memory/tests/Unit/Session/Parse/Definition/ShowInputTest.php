<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\ShowInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowInput::class)]
#[Small]
final class ShowInputTest extends TestCase
{
    public function testErrorOpensTheInspectionDatabaseBeforeNestedTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'missing'");
        $session->query('SHOW TABLE STATUS FROM missing WHERE ROW(1,2) LIKE (SELECT a FROM absent)');
    }

    public function testErrorResolvesNestedTablesBeforeTheInspectionDuringPreparation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.absent' doesn't exist");
        $session->analyze('SHOW TABLE STATUS FROM missing WHERE ROW(1,2) LIKE (SELECT x FROM absent)', true);
    }

    public function testErrorRestoresTheInputProtocolAfterARefusedPreparation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $prepared = $session->run('SHOW TABLE STATUS FROM missing WHERE ROW(1,2) LIKE 1', prepared: true)[0];
        $direct = $session->run('SHOW TABLE STATUS FROM missing WHERE ROW(1,2) LIKE 1')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $prepared);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $direct);
        self::assertSame([1241, 1049], [$prepared->getCode(), $direct->getCode()]);
        self::assertFalse($session->preparation->inputPrepared);
    }

    public function testPrepareResolvesTheShowColumnBeforeTheOperandWidth(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionCode(1241);
        $session->query('SHOW TABLE STATUS WHERE ROW(Name,2) LIKE 1');
    }
    public function testResolveKeepsTheQualifiedInspectionTargetAndProtocol(): void
    {
        $session = (new Instance())->connect();
        $prepared = $session->run('SHOW KEYS FROM missing.t WHERE ROW(1,2) LIKE 1', prepared: true)[0];
        $direct = $session->run('SHOW KEYS FROM missing.t WHERE ROW(1,2) LIKE 1')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $prepared);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $direct);
        self::assertSame([1241, 1049], [$prepared->getCode(), $direct->getCode()]);
    }
    public function testSelectedUsesTheWrittenTableDatabase(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $header = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('d'))));
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter($header, new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($row));
        self::assertSame('d', (new ShowInput())->selected($problem, $session));
    }
}
