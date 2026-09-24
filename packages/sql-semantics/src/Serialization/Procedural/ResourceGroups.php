<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Server\ResourceGroup\AlterResourceGroupStatement;
use SqlSemantics\Model\Statement\Server\ResourceGroup\CreateResourceGroupStatement;
use SqlSemantics\Model\Statement\Server\ResourceGroup\SetResourceGroupStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes CREATE, ALTER and SET RESOURCE GROUP from the group name and its options.
 * @visibility SqlSemantics
 */
final class ResourceGroups
{
    /**
     * Spells options with `=` and omits the options a request leaves unchanged.
     */
    public static function write(CreateResourceGroupStatement|AlterResourceGroupStatement|SetResourceGroupStatement $statement): Tree
    {
        $name = Build::identifier([$statement->name], Dialect::MySql);
        if ($statement instanceof SetResourceGroupStatement) {
            $threads = $statement->threads === [] ? [] : [Build::keyword('FOR'), Build::separated(array_map(Expressions::write(...), $statement->threads))];
            return new Tree('set-resource-group', [Build::keyword('SET RESOURCE GROUP'), $name, ...$threads]);
        }
        $cpus = $statement->cpus === [] ? [] : [Build::keyword('VCPU ='), Build::separated(array_map(self::cpus(...), $statement->cpus))];
        $priority = $statement->priority === null ? [] : [Build::keyword('THREAD_PRIORITY = ' . $statement->priority)];
        $state = $statement->state === null ? [] : [Build::keyword($statement->state->value)];
        if ($statement instanceof CreateResourceGroupStatement) {
            return new Tree('create-resource-group', [Build::keyword('CREATE RESOURCE GROUP'), $name, Build::keyword('TYPE = ' . $statement->type->value), ...$cpus, ...$priority, ...$state]);
        }
        return new Tree('alter-resource-group', [Build::keyword('ALTER RESOURCE GROUP'), $name, ...$cpus, ...$priority, ...$state, ...($statement->force ? [Build::keyword('FORCE')] : [])]);
    }

    /**
     * Spells a single CPU as its number and a wider range as first-last.
     */
    public static function cpus(CpuRange $range): Tree
    {
        return Build::keyword($range->first === $range->last ? (string) $range->first : $range->first . '-' . $range->last);
    }
}
