<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use ZtdQuery\Platform\MySql\Parse\MySqlSelectRelationParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * What the shadow can say about the tables a statement names.
 *
 * A table is known when the shadow holds rows for it, when something has
 * declared it, or when a view goes by that name; a statement naming anything
 * else is reading something ZTD cannot answer for. A name the statement
 * declares for itself in a WITH prefix is not a table at all, so it is never
 * one the shadow is missing.
 *
 * A shadow that has been told nothing knows no table, and refusing every
 * statement on that basis would be useless, so it says instead that it has
 * nothing to go on.
 */
final class MySqlKnownTables
{
    /**
     * @param ShadowStore $shadowStore What the shadow holds
     * @param TableDefinitionRegistry $registry What has been declared
     * @param ViewDefinitionSet $views The views that have been declared
     * @param MySqlSelectRelationParser $relations Reads the tables a statement names
     * @param MySqlCteShadowComposer $ctes Reads the names a statement declares for itself
     */
    public function __construct(
        private readonly ShadowStore $shadowStore,
        private readonly TableDefinitionRegistry $registry,
        private readonly ViewDefinitionSet $views,
        private readonly MySqlSelectRelationParser $relations = new MySqlSelectRelationParser(),
        private readonly MySqlCteShadowComposer $ctes = new MySqlCteShadowComposer(),
    ) {
    }

    /**
     * Answers the first table a statement reads that the shadow does not know.
     *
     * @param string $sql The statement, as written
     *
     * @return string|null The table, or null where the shadow knows every one of them
     */
    public function firstUnknownIn(string $sql): ?string
    {
        $declaredCtes = array_fill_keys($this->ctes->declaredCteNames($sql), true);
        foreach ($this->relations->tableNames($sql) as $tableName) {
            if (isset($declaredCtes[strtolower($tableName)])) {
                continue;
            }
            if (!$this->knows($tableName)) {
                return $tableName;
            }
        }

        return null;
    }

    /**
     * Reports whether the shadow knows a table at all.
     *
     * @param string $tableName Name to look for
     *
     * @return bool True when it has rows for it, a declaration of it, or a view by that name
     */
    public function knows(string $tableName): bool
    {
        return $this->shadowStore->has($tableName)
            || $this->registry->has($tableName)
            || $this->views->has($tableName);
    }

    /**
     * Reports whether the shadow has been told anything at all.
     *
     * @return bool True when anything has been declared or filled in
     */
    public function hasAnything(): bool
    {
        return $this->shadowStore->getAll() !== []
            || $this->registry->hasAnyTables()
            || $this->views->hasAnyViews();
    }
}
