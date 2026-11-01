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

    public function testPrepareReadsTheTableHeadingFromTheResolvedStructure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionCode(1241);
        $session->query('SHOW TABLES WHERE ROW(Tables_in_d,2) LIKE 1');
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

    public function testResolveContextualizesTheSameOperandBeforeOpeningItsTarget(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->text = 'unrelated invalid input';
        $header = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))));
        $variable = new \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable(new \SqlSemantics\Statement\Identifier\Name('no_such_variable'));
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([$variable, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter($header, new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($row));
        self::assertSame(1046, (new ShowInput())->resolve($problem, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame(1193, (new ShowInput())->resolve($problem, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testResolveContextualizesLegacyFiltersBeforeSelectingTheirDatabase(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $this->expectExceptionCode(1193);
        $session->query('SHOW COLUMNS FROM t WHERE (@@no_such_variable, 1)');
    }

    public function testSelectedCanResolveTheIdentityWithoutOpeningTheTarget(): void
    {
        $session = (new Instance())->connect();
        $header = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('missing'))));
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter($header, new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($row));
        self::assertSame('missing', (new ShowInput())->selected($problem, $session, false));
    }

    public function testHeadingsKeepTheOutputScopeWithoutInputText(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->text = 'opaque request label';
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('Db')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus(), new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($row));
        self::assertSame(1241, (new ShowInput())->resolve($problem, $session)->getCode());
        self::assertSame('opaque request label', $session->text);
    }

    public function testPrepareNamesTheWhereClauseForAnUnboundFilterColumn(): void
    {
        $this->expectExceptionMessage("Unknown column 'absent_column' in 'where clause'");
        (new Instance())->connect()->query('SHOW PROCEDURE STATUS WHERE absent_column+(1,2)');
    }

    public function testResolvePreparesAnEventFilterBeforeInspectingItsDatabase(): void
    {
        $this->expectExceptionCode(1241);
        (new Instance('8.4.7'))->connect()->query('SHOW EVENTS FROM missing WHERE (Name,1)');
    }

    public function testResolveRequiresTheInspectionDatabaseBeforeAWindowCount(): void
    {
        $this->expectExceptionCode(1046);
        (new Instance('8.4.7'))->connect()->query('SHOW TABLES WHERE (NTILE(n) OVER (),1)');
    }
}
