<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * The resource groups of the server and the group each session's thread is assigned to.
 *
 * The server starts with the default groups USR_default, of type USER, and SYS_default, of type
 * SYSTEM, both on every CPU. Group names compare without regard to letter case. A session that
 * names no group runs in USR_default.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/resource-groups.html.
 *
 * @visibility MySqlMemory
 */
final class ResourceGroups
{
    /**
     * @var array<string, ResourceGroup> The groups, by the key of their name
     */
    public array $groups = [];

    /**
     * @var array<int, string> The key of the name of the group each session's thread is assigned to, by connection id; a session not listed runs in USR_default
     */
    public array $bindings = [];

    /**
     * @param int $processors The number of CPUs the server sees; the CPUs are numbered from 0
     */
    public function __construct(public readonly int $processors = 8)
    {
        foreach (['USR_default' => false, 'SYS_default' => true] as $name => $system) {
            $this->groups[Registry::key($name)] = new ResourceGroup($name, $system, range(0, $processors - 1));
        }
    }

    /**
     * Finds a group by name, or answers null.
     */
    public function find(string $name): ?ResourceGroup
    {
        return $this->groups[Registry::key($name)] ?? null;
    }

    /**
     * Tells whether a name is that of a default group, which cannot be altered or dropped.
     */
    public function predefined(string $name): bool
    {
        return in_array(Registry::key($name), [Registry::key('USR_default'), Registry::key('SYS_default')], true);
    }

    /**
     * Adds a group.
     */
    public function add(ResourceGroup $group): void
    {
        $this->groups[Registry::key($group->name)] = $group;
    }

    /**
     * Removes a group, moving the sessions assigned to it back to USR_default.
     */
    public function remove(string $name): void
    {
        $key = Registry::key($name);
        unset($this->groups[$key]);
        $this->bindings = array_filter($this->bindings, static fn (string $bound): bool => $bound !== $key);
    }

    /**
     * Tells whether the thread of a session is assigned to a group.
     */
    public function busy(string $name): bool
    {
        return in_array(Registry::key($name), $this->bindings, true);
    }

    /**
     * Assigns the thread of a session to a group.
     */
    public function bind(int $session, string $name): void
    {
        $this->bindings[$session] = Registry::key($name);
    }
}
