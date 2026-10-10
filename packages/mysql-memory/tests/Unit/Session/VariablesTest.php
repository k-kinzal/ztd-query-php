<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability;

#[CoversClass(Variables::class)]
#[Small]
final class VariablesTest extends TestCase
{
    public function testSystemAndSetShareTheLastInsertIdOfTheFunction(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT LAST_INSERT_ID(42)');
        self::assertSame(42, $session->variables->read('last_insert_id'));
        self::assertSame(42, $session->variables->read('identity'));
        $session->query('SET identity=73');
        $result = $session->query('SELECT LAST_INSERT_ID(), @@last_insert_id, @@identity')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['73', '73', '73']], $result->rows);
    }

    public function testSystemReadsTheConnectionIdUntilThePseudoIdIsAssigned(): void
    {
        $session = (new Instance())->connect();
        $id = $session->id;
        self::assertSame($id, $session->variables->read('pseudo_thread_id'));
        $session->query('SET pseudo_thread_id=42');
        $result = $session->query('SELECT CONNECTION_ID(), @@pseudo_thread_id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['42', '42']], $result->rows);
        self::assertSame($id, $session->id);
    }

    public function testUserAnswersNullForAVariableNeverAssigned(): void
    {
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals());

        self::assertEquals([null, Domain::string(0, Collation::binary(), Field::MediumBlob)], $variables->user('nothing'));
    }

    public function testUserReadsTheNameWithoutRegardToCase(): void
    {
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals());
        $variables->assign('Total', 5, Domain::integer());

        self::assertEquals([5, Domain::integer()], $variables->user('TOTAL'));
    }

    public function testAssignKeepsTheValueAndDomainByLowerCaseName(): void
    {
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals());
        $variables->assign('V', 'ink', Domain::string(3, Collation::known('utf8mb4_0900_ai_ci')));

        self::assertEquals(['v' => ['ink', Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'))]], $variables->user);
    }

    public function testAssignOfAStatementIsReadBack(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @V = 5');
        $result = $session->query('SELECT @v, @nothing')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', null]], $result->rows);
    }

    public function testSystemAnswersTheSessionValue(): void
    {
        $definition = new Definition('autocommit', Reach::Both, ValueShape::Boolean, 'ON', Writability::Writable, null, null, Resolved::integer());
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals());
        $variables->set($definition, 'OFF');

        self::assertSame('OFF', $variables->system($definition, Scope::Session));
        self::assertSame('OFF', $variables->system($definition, Scope::Both));
    }

    public function testSystemAnswersTheGlobalValueInTheGlobalScope(): void
    {
        $definition = new Definition('autocommit', Reach::Both, ValueShape::Boolean, 'ON', Writability::Writable, null, null, Resolved::integer());
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals());
        $variables->set($definition, 'OFF');

        self::assertSame('ON', $variables->system($definition, Scope::Global));
    }

    public function testSystemAnswersTheGlobalValueWithoutASessionValue(): void
    {
        $definition = new Definition('autocommit', Reach::Both, ValueShape::Boolean, 'ON', Writability::Writable, null, null, Resolved::integer());
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals(['autocommit' => 'OFF']));

        self::assertSame('OFF', $variables->system($definition, Scope::Session));
    }

    public function testSetChangesOnlyTheSessionValue(): void
    {
        $definition = new Definition('div_precision_increment', Reach::Both, ValueShape::Unsigned, 4, Writability::Writable, 0, 30, Resolved::integer());
        $globals = new Globals();
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), $globals);
        $variables->set($definition, 6);

        self::assertSame(['div_precision_increment' => 6], $variables->session);
        self::assertSame(4, $globals->value($definition));
    }

    public function testSetOfAStatementLeavesTheGlobalValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET div_precision_increment = 6');
        $result = $session->query('SELECT @@div_precision_increment, @@SESSION.div_precision_increment, @@GLOBAL.div_precision_increment')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['6', '6', '4']], $result->rows);
    }

    public function testReadAnswersTheSessionValueByName(): void
    {
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals());

        self::assertSame('ON', $variables->read('autocommit'));
        self::assertSame(4, $variables->read('div_precision_increment'));
        self::assertSame(1024, $variables->read('group_concat_max_len'));
    }

    public function testReadAnswersNullForAnUnknownName(): void
    {
        $variables = new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals());

        self::assertNull($variables->read('nosuch'));
    }

    public function testReadDescribesTheConnection(): void
    {
        $session = (new Instance())->connect('app', 'example.com');

        self::assertSame('app@example.com', $session->variables->account);
        self::assertSame('app@%', $session->variables->definer);
        self::assertSame($session->id, $session->variables->connection);
        self::assertSame(0, $session->variables->rowCount);
        self::assertSame(0, $session->variables->lastInsertId);
    }

    public function testSystemKeepsTheGlobalValueTheConnectionStartedWith(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query('SET GLOBAL div_precision_increment = 6');
        $result = $session->query('SELECT @@div_precision_increment, @@global.div_precision_increment, 1/3')[0];
        $later = $instance->connect()->query('SELECT @@div_precision_increment')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $later);
        self::assertSame([['4', '6', '0.3333']], $result->rows);
        self::assertSame([['6']], $later->rows);
    }

    public function testSetKeepsANullValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET character_set_results = NULL');
        $result = $session->query('SELECT @@character_set_results, @@global.character_set_results')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, 'utf8mb4']], $result->rows);
        self::assertNull($session->variables->read('character_set_results'));
    }

    public function testInstantReadsTheClockOfTheServer(): void
    {
        $instance = new Instance();
        $instance->registry->threads->pass(7200.0);
        $variables = new Variables($instance->catalog, $instance->globals, $instance);

        self::assertGreaterThan(microtime(true) + 7100.0, $variables->instant());
    }

    public function testInstantReadsTheTimestampTheSessionSet(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET timestamp = 1700000000.5');

        self::assertSame(1700000000.5, $session->variables->instant());
    }

    public function testCountReadsAnUnsignedValueBeyondTheSignedRangeAsTheLargestInteger(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET group_concat_max_len = 18446744073709551615');

        self::assertSame([PHP_INT_MAX, 7], [$session->variables->count('group_concat_max_len', 1024), $session->variables->count('nosuch', 7)]);
    }
}
