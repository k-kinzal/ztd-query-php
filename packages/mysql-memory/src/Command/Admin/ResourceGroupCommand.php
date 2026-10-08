<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Registry\ResourceGroup;
use MySqlMemory\Registry\ResourceGroups;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE, ALTER, DROP and SET RESOURCE GROUP.
 *
 * CREATE, ALTER and DROP commit the open transaction. The VCPU list is checked first: a CPU the
 * server lacks is ER_INVALID_VCPU_ID, then a range whose first CPU follows its last is
 * ER_INVALID_VCPU_RANGE. A group that exists is ER_RESOURCE_GROUP_EXISTS, one that does not is
 * ER_RESOURCE_GROUP_NOT_EXIST; the default groups cannot be altered or dropped. ALTER checks the
 * priority against the type of the group, refuses FORCE without DISABLE, and sets the priority
 * to 0 when it names none. DISABLE FORCE moves the threads of the group back to the default
 * group; DROP refuses a group a thread is assigned to unless FORCE is written. SET RESOURCE GROUP
 * assigns the session's thread to an enabled user group. The emulator runs no thread FOR could
 * name: one id is ER_INVALID_THREAD_ID, as the server answers for the session's own thread, and
 * a list of ids assigns nothing (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-resource-group.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-resource-group.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-resource-group.html.
 *
 * @visibility MySqlMemory
 */
final class ResourceGroupCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates, alters, drops or assigns the group.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $groups = $session->instance->registry->resourceGroups;
        if ($statement instanceof SetResourceGroup) {
            $this->assign($statement, $session, $groups);

            return new Completion();
        }
        $session->transaction->commit();
        if ($statement instanceof CreateResourceGroup) {
            $this->create($statement, $groups);
        } elseif ($statement instanceof AlterResourceGroup) {
            $this->alter($statement, $groups);
        } elseif ($statement instanceof DropResourceGroup) {
            $this->drop($statement, $groups);
        }

        return new Completion();
    }

    /**
     * Creates a group.
     *
     * @throws \MySqlMemory\Error\SqlError When the CPUs are wrong or the group exists
     */
    public function create(CreateResourceGroup $statement, ResourceGroups $groups): void
    {
        $name = $statement->name->value;
        if (mb_strlen($name) > 64) {
            throw SchemaError::TooLongIdentifier->error($name);
        }
        $cpus = $this->cpus($statement->cpus, $groups->processors);
        if ($groups->find($name) !== null) {
            throw AdministrationError::ResourceGroupExists->error($name);
        }
        $groups->add(new ResourceGroup($name, $statement->kind === ResourceGroupKind::System, $cpus, $this->priority($statement->priority), $statement->enabled ?? true));
    }

    /**
     * Changes a group.
     *
     * @throws \MySqlMemory\Error\SqlError When the CPUs, the group, the priority or FORCE is wrong
     */
    public function alter(AlterResourceGroup $statement, ResourceGroups $groups): void
    {
        $name = $statement->name->value;
        $cpus = $this->cpus($statement->cpus, $groups->processors);
        $group = $groups->find($name) ?? throw AdministrationError::ResourceGroupMissing->error($name);
        if ($groups->predefined($name)) {
            throw AdministrationError::OperationDisallowed->error('Alter', 'default resource groups.');
        }
        $priority = $this->priority($statement->priority);
        [$low, $high] = $group->system ? [-20, 0] : [0, 19];
        if ($priority < $low || $priority > $high) {
            throw AdministrationError::InvalidThreadPriority->error((string) $priority, $group->system ? 'System' : 'User', $name, (string) $low, (string) $high);
        }
        if ($statement->force && $statement->enabled !== false) {
            throw AdministrationError::ForceWithoutDisable->error();
        }
        if ($statement->cpus !== []) {
            $group->cpus = $cpus;
        }
        $group->priority = $priority;
        $group->enabled = $statement->enabled ?? $group->enabled;
        if ($statement->force) {
            $key = array_search($group, $groups->groups, true);
            $groups->bindings = array_filter($groups->bindings, static fn (string $bound): bool => $bound !== $key);
        }
    }

    /**
     * Drops a group.
     *
     * @throws \MySqlMemory\Error\SqlError When the group is missing, a default one or busy
     */
    public function drop(DropResourceGroup $statement, ResourceGroups $groups): void
    {
        $name = $statement->name->value;
        if ($groups->find($name) === null) {
            throw AdministrationError::ResourceGroupMissing->error($name);
        }
        if ($groups->predefined($name)) {
            throw AdministrationError::OperationDisallowed->error('Drop operation ', 'default resource groups.');
        }
        if (!$statement->force && $groups->busy($name)) {
            throw AdministrationError::ResourceGroupBusy->error($name);
        }
        $groups->remove($name);
    }

    /**
     * Assigns the session's thread to a group.
     *
     * @throws \MySqlMemory\Error\SqlError When the group is missing, disabled or of another type, or a thread id is unknown
     */
    public function assign(SetResourceGroup $statement, Session $session, ResourceGroups $groups): void
    {
        $name = $statement->name->value;
        $group = $groups->find($name) ?? throw AdministrationError::ResourceGroupMissing->error($name);
        if (!$group->enabled) {
            throw AdministrationError::ResourceGroupDisabled->error($name);
        }
        if (count($statement->threads) === 1) {
            throw AdministrationError::InvalidThreadId->error((new Literals())->number($statement->threads[0]));
        }
        if ($statement->threads !== []) {
            return;
        }
        if ($group->system) {
            throw AdministrationError::ResourceGroupBindFailed->error($name, (string) $session->id, "System resource group can't be applied to user thread.");
        }
        $groups->bind($session->id, $name);
    }

    /**
     * Answers the CPUs of a VCPU list in ascending order, every CPU of the server for an empty list.
     *
     * @param list<CpuRange> $ranges
     * @return list<int>
     *
     * @throws \MySqlMemory\Error\SqlError When a CPU is not one of the server or a range is reversed
     */
    public function cpus(array $ranges, int $processors): array
    {
        if ($ranges === []) {
            return range(0, $processors - 1);
        }
        $literals = new Literals();
        $bounds = [];
        foreach ($ranges as $range) {
            $first = $literals->number($range->first);
            $last = $range->last === null ? $first : $literals->number($range->last);
            foreach ([$first, $last] as $cpu) {
                if (bccomp($cpu, (string) ($processors - 1)) > 0) {
                    throw AdministrationError::InvalidCpuId->error($cpu);
                }
            }
            $bounds[] = [(int) $first, (int) $last];
        }
        $cpus = [];
        foreach ($bounds as [$first, $last]) {
            if ($first > $last) {
                throw AdministrationError::InvalidCpuRange->error((string) $first, (string) $last);
            }
            array_push($cpus, ...range($first, $last));
        }
        $cpus = array_values(array_unique($cpus));
        sort($cpus);

        return $cpus;
    }

    /**
     * Answers the priority a statement writes, 0 when it writes none.
     */
    public function priority(?ThreadPriority $priority): int
    {
        if ($priority === null) {
            return 0;
        }
        $value = (new Literals())->number($priority->number);
        if (bccomp($value, '2147483647') > 0) {
            $value = '2147483647';
        }

        return $priority->negative ? -(int) $value : (int) $value;
    }
}
