<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.RESOURCE_GROUPS: the resource groups of the server, user groups first, each kind by name.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-resource-groups-table.html.
 *
 * @visibility MySqlMemory
 */
final class ResourceGroups implements SystemRows
{
    /**
     * Answers a row for each resource group.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $groups = array_values($reading->instance->registry->resourceGroups->groups);
        usort($groups, static fn ($left, $right): int => [$left->system, strtolower($left->name)] <=> [$right->system, strtolower($right->name)]);

        return array_map(static fn ($group): array => ['RESOURCE_GROUP_NAME' => $group->name, 'RESOURCE_GROUP_TYPE' => $group->system ? 'SYSTEM' : 'USER', 'RESOURCE_GROUP_ENABLED' => $group->enabled ? 1 : 0, 'VCPU_IDS' => $group->vcpus(), 'THREAD_PRIORITY' => $group->priority], $groups);
    }
}
