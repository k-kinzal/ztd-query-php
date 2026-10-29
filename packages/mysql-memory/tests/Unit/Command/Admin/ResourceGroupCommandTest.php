<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\ResourceGroupCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Registry\ResourceGroups;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup;

#[CoversClass(ResourceGroupCommand::class)]
#[Small]
final class ResourceGroupCommandTest extends TestCase
{
    #[DataProvider('providerRestrictedStatements')]
    public function testRevokedPrivilegesAreCheckedBeforeTheGroupAndCpuNames(string $sql, string $privilege): void
    {
        $session = (new Instance())->connect();
        $session->query('REVOKE ALL PRIVILEGES, GRANT OPTION FROM CURRENT_USER');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1227);
        $this->expectExceptionMessage('Access denied; you need (at least one of) the ' . $privilege . ' privilege(s) for this operation');
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRestrictedStatements(): iterable
    {
        yield 'create' => ['CREATE RESOURCE GROUP missing TYPE USER VCPU 9999', 'RESOURCE_GROUP_ADMIN'];
        yield 'alter' => ['ALTER RESOURCE GROUP missing VCPU 1 1', 'RESOURCE_GROUP_ADMIN'];
        yield 'drop' => ['DROP RESOURCE GROUP missing', 'RESOURCE_GROUP_ADMIN'];
        yield 'set' => ['SET RESOURCE GROUP missing', 'RESOURCE_GROUP_ADMIN OR RESOURCE_GROUP_USER'];
        yield 'set threads' => ['SET RESOURCE GROUP missing FOR 1', 'RESOURCE_GROUP_ADMIN OR RESOURCE_GROUP_USER'];
    }

    public function testAnActiveRoleAllowsAssignmentWithoutAdministration(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE RESOURCE GROUP batch TYPE USER; CREATE ROLE assigner; GRANT RESOURCE_GROUP_USER ON *.* TO assigner; GRANT assigner TO CURRENT_USER; SET ROLE assigner; REVOKE RESOURCE_GROUP_ADMIN, RESOURCE_GROUP_USER ON *.* FROM CURRENT_USER');
        self::assertInstanceOf(Completion::class, $session->query('SET RESOURCE GROUP batch')[0]);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1227);
        $session->query('ALTER RESOURCE GROUP batch ENABLE');
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ResourceGroupCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesAGroupOnTheCpusItNames(): void
    {
        $instance = new Instance();
        $reply = $instance->connect()->query('CREATE RESOURCE GROUP batch TYPE USER VCPU = 1, 2, 5, 7, 4 THREAD_PRIORITY = 3 DISABLE')[0];
        $group = $instance->registry->resourceGroups->find('BATCH');

        self::assertInstanceOf(Completion::class, $reply);
        self::assertNotNull($group);
        self::assertSame(['1-2,4-5,7', 3, false], [$group->vcpus(), $group->priority, $group->enabled]);
    }

    public function testExecuteRefusesAPriorityOutsideTheRangeOfASystemGroup(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3654);
        $this->expectExceptionMessage('Invalid thread priority value -272010 for System resource group g. Allowed range is [-20, 0].');

        $session->query('CREATE RESOURCE GROUP g TYPE SYSTEM VCPU = 00000028 - 0 THREAD_PRIORITY := - 00000000000272010');
    }

    public function testCreateChecksTheCpusBeforeTheName(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE RESOURCE GROUP USR_default TYPE SYSTEM VCPU = 9');
        self::assertInstanceOf(CreateResourceGroup::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3652);
        $this->expectExceptionMessage('Invalid cpu id 9');

        (new ResourceGroupCommand())->create($statement, new ResourceGroups());
    }

    public function testCreateRefusesAGroupThatExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE RESOURCE GROUP g1 TYPE USER');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3650);
        $this->expectExceptionMessage("Resource Group 'G1' exists");

        $session->query('CREATE RESOURCE GROUP G1 TYPE USER');
    }

    public function testCreateRefusesALongName(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1059);

        $session->query('CREATE RESOURCE GROUP ' . str_repeat('a', 65) . ' TYPE USER');
    }

    public function testAlterChecksTheCpusBeforeTheGroup(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ALTER RESOURCE GROUP missing VCPU := 000007208, 1 - 0 FORCE');
        self::assertInstanceOf(AlterResourceGroup::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3652);
        $this->expectExceptionMessage('Invalid cpu id 7208');

        (new ResourceGroupCommand())->alter($statement, new ResourceGroups(), $session->diagnostics);
    }

    public function testAlterRefusesAPriorityOutsideTheRangeOfTheGroup(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE RESOURCE GROUP g TYPE USER');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3654);
        $this->expectExceptionMessage('Invalid thread priority value 25 for User resource group g. Allowed range is [0, 19].');

        $session->query('ALTER RESOURCE GROUP g THREAD_PRIORITY = 25');
    }

    public function testAlterRefusesForceWithoutDisable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE RESOURCE GROUP g TYPE USER');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3662);
        $this->expectExceptionMessage('Option FORCE invalid as DISABLE option is not specified.');

        $session->query('ALTER RESOURCE GROUP g ENABLE FORCE');
    }

    public function testAlterRefusesADefaultGroup(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3655);
        $this->expectExceptionMessage('Alter operation is disallowed on default resource groups.');

        $session->query('ALTER RESOURCE GROUP USR_default THREAD_PRIORITY = 99');
    }

    public function testAlterResetsThePriorityItDoesNotName(): void
    {
        $instance = new Instance();
        $instance->connect()->query('CREATE RESOURCE GROUP g TYPE USER VCPU = 1 THREAD_PRIORITY = 3 DISABLE; ALTER RESOURCE GROUP g VCPU = 0-7');
        $group = $instance->registry->resourceGroups->find('g');

        self::assertNotNull($group);
        self::assertSame(['0-7', 0, false], [$group->vcpus(), $group->priority, $group->enabled]);
    }

    public function testDropRefusesAGroupAThreadIsAssignedTo(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE RESOURCE GROUP g TYPE USER; SET RESOURCE GROUP g');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3656);
        $this->expectExceptionMessage('Resource group g is busy.');

        $session->query('DROP RESOURCE GROUP g');
    }

    public function testDropForcesTheThreadsBack(): void
    {
        $instance = new Instance();
        $instance->connect()->query('CREATE RESOURCE GROUP g TYPE USER; SET RESOURCE GROUP g; DROP RESOURCE GROUP G FORCE');

        self::assertSame([null, []], [$instance->registry->resourceGroups->find('g'), $instance->registry->resourceGroups->bindings]);
    }

    public function testDropRefusesAMissingGroup(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('DROP RESOURCE GROUP RESTART FORCE');
        self::assertInstanceOf(DropResourceGroup::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3651);
        $this->expectExceptionMessage("Resource Group 'RESTART' does not exist.");

        (new ResourceGroupCommand())->drop($statement, new ResourceGroups());
    }

    public function testDropRefusesADefaultGroup(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3655);
        $this->expectExceptionMessage('Drop operation  operation is disallowed on default resource groups.');

        $session->query('DROP RESOURCE GROUP SYS_default');
    }

    public function testAssignRefusesADisabledGroup(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE RESOURCE GROUP g TYPE SYSTEM DISABLE');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3657);
        $this->expectExceptionMessage('Resource group g is disabled.');

        $session->query('SET RESOURCE GROUP g FOR 999999');
    }

    public function testAssignRefusesASystemGroupForTheSession(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SET RESOURCE GROUP SYS_default');
        self::assertInstanceOf(SetResourceGroup::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3661);
        $this->expectExceptionMessage("Unable to bind resource group SYS_default with thread id ({$session->id}).(System resource group can't be applied to user thread.).");

        (new ResourceGroupCommand())->assign($statement, $session, new ResourceGroups());
    }

    public function testAssignRefusesASingleThreadId(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3660);
        $this->expectExceptionMessage('Invalid thread id (999999).');

        $session->query('SET RESOURCE GROUP USR_default FOR 999999');
    }

    public function testCpusChecksEveryIdBeforeTheRanges(): void
    {
        $statement = (new Instance())->connect()->analyze('CREATE RESOURCE GROUP g TYPE USER VCPU = 3-1, 99');
        self::assertInstanceOf(CreateResourceGroup::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Invalid cpu id 99');

        (new ResourceGroupCommand())->cpus($statement->cpus, 8);
    }

    public function testCpusRefusesAReversedRange(): void
    {
        $statement = (new Instance())->connect()->analyze('CREATE RESOURCE GROUP g TYPE USER VCPU = 7-0');
        self::assertInstanceOf(CreateResourceGroup::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3653);
        $this->expectExceptionMessage('Invalid VCPU range 7-0');

        (new ResourceGroupCommand())->cpus($statement->cpus, 8);
    }

    public function testCpusAnswersEveryCpuForNoList(): void
    {
        self::assertSame([0, 1, 2], (new ResourceGroupCommand())->cpus([], 3));
    }

    public function testPriorityReadsTheSignAndTheDigits(): void
    {
        $statement = (new Instance())->connect()->analyze('CREATE RESOURCE GROUP g TYPE SYSTEM THREAD_PRIORITY = - 0005');
        self::assertInstanceOf(CreateResourceGroup::class, $statement);

        self::assertSame([-5, 0], [(new ResourceGroupCommand())->priority($statement->priority), (new ResourceGroupCommand())->priority(null)]);
    }

    public function testAlterRefusesAMissingGroupBeforeAReversedRange(): void
    {
        $session = (new Instance())->connect();
        $missing = $session->run('ALTER RESOURCE GROUP g VCPU 2-1')[0];
        $cpu = $session->run('ALTER RESOURCE GROUP g VCPU 999')[0];
        $predefined = $session->run('ALTER RESOURCE GROUP USR_default VCPU 1-0')[0];
        $session->query('CREATE RESOURCE GROUP g TYPE = USER');
        $reversed = $session->run('ALTER RESOURCE GROUP g VCPU 1-0')[0];

        self::assertInstanceOf(SqlError::class, $missing);
        self::assertInstanceOf(SqlError::class, $cpu);
        self::assertInstanceOf(SqlError::class, $predefined);
        self::assertInstanceOf(SqlError::class, $reversed);
        self::assertSame([3651, 3652, 3655, 3653], [$missing->getCode(), $cpu->getCode(), $predefined->getCode(), $reversed->getCode()]);
    }
    public function testConfiguredIgnoresUnsupportedNonzeroPrioritiesWithAWarning(): void
    {
        $session = (new Instance(resourcePriorities: false))->connect();
        $priority = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority(false, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('20'));
        $value = (new ResourceGroupCommand())->configured($priority, $session->instance->registry->resourceGroups, $session->diagnostics);

        self::assertSame(0, $value);
        self::assertSame([['Warning', 3659, 'Attribute thread_priority is ignored (using default value).']], $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string, int, list<list<string>>}>
     */
    public static function providerPreparationFailures(): iterable
    {
        yield 'priority before commit' => ['CREATE RESOURCE GROUP g TYPE USER THREAD_PRIORITY 20', 3654, []];
        yield 'create CPU before commit' => ['CREATE RESOURCE GROUP g TYPE USER VCPU 99999', 3652, []];
        yield 'alter CPU before commit' => ['ALTER RESOURCE GROUP g VCPU 99999', 3652, []];
        yield 'range after commit' => ['CREATE RESOURCE GROUP g TYPE USER VCPU 1-0', 3653, [['1']]];
    }

    /**
     * @param list<list<string>> $rows Rows that survive ROLLBACK after the refusal
     */
    #[DataProvider('providerPreparationFailures')]
    public function testPrepareKeepsTheImplicitCommitBoundary(string $sql, int $code, array $rows): void
    {
        $session = (new Instance(databases: ['app']))->connect(database: 'app');
        $session->query('CREATE TABLE t (id INT); START TRANSACTION; INSERT INTO t VALUES (1)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        try {
            $session->query($sql);
        } finally {
            $session->query('ROLLBACK');
            $result = $session->query('SELECT * FROM t')[0];
            self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
            self::assertSame($rows, $result->rows);
        }
    }

}
