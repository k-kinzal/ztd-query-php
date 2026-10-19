<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Command\Show\Keys;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;
use SqlSemantics\Statement\Statement;

/**
 * Retains index options through definition changes and checks primary keys.
 *
 * The last written visibility wins. An explicit primary key, or the first
 * non-null unique key used as an implicit primary, cannot be invisible.
 * Verified through CREATE TABLE and ALTER INDEX on MySQL 8.0 and 8.4.
 *
 * @visibility MySqlMemory
 */
final class IndexOptions
{
    /**
     * Retains the last USING option when the storage engine supports it.
     * Modern InnoDB and MyISAM discard HASH; legacy definitions retain the
     * requested spelling. MEMORY retains HASH and BTREE.
     * Verified through CREATE TABLE and SHOW CREATE TABLE on MySQL 8.4.
     */
    public function algorithm(IndexDefinition $index, string $engine, bool $legacy = false): ?IndexAlgorithm
    {
        $algorithm = $index->algorithm;
        foreach ($index->options as $option) {
            if ($option instanceof IndexUsing) {
                $algorithm = $option->algorithm;
            }
        }

        return !$legacy && $algorithm === IndexAlgorithm::Hash && !in_array(strtoupper($engine), ['MEMORY', 'HEAP'], true) ? null : $algorithm;
    }

    /**
     * Records the modern server note when an unsupported structure falls back.
     * MySQL 5.6 and 5.7 silently replace HASH on disk-backed engines.
     */
    public function warn(IndexDefinition $index, string $engine, Context $context): void
    {
        $reported = $context->reported;
        if (isset($reported[$index])) {
            return;
        }
        $reported[$index] = true;
        if (in_array($context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return;
        }
        if ($this->algorithm($index, 'MEMORY') === IndexAlgorithm::Hash && $this->algorithm($index, $engine) === null) {
            $context->note(SchemaError::UnsupportedIndexAlgorithm, 'HASH');
        }
    }

    /**
     * Reports CREATE TABLE index substitutions before column binding failures.
     * Reusing the same prepared node never repeats its diagnostic.
     */
    public function prepare(Statement|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $statement, Session $session, Context $context): void
    {
        if (!$statement instanceof CreateTable && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable) {
            return;
        }
        $schemaName = $statement->name->schema->value ?? $session->variables->database;
        $name = $statement->name->name->value;
        $schema = $session->instance->dictionary->schema($schemaName);
        $existing = $statement->temporary() ? $session->temporaries->table($schemaName, $name) : ($schema?->table($name) ?? $schema?->views[$name] ?? null);
        if ($existing !== null) {
            return;
        }
        $engine = (new StorageOptions())->engine($statement, $session->variables);
        foreach ($statement->elements as $element) {
            if ($element instanceof IndexDefinition) {
                $this->warn($element, $engine, $context);
            }
        }
    }

    /**
     * Answers the effective visibility, which defaults to visible.
     */
    public function visible(IndexDefinition $index): bool
    {
        $visible = true;
        foreach ($index->options as $option) {
            if ($option instanceof IndexVisibility) {
                $visible = $option->visible;
            }
        }

        return $visible;
    }

    /**
     * Rejects an invisible explicit or implicit primary key.
     */
    public function check(TableDefinition $table): void
    {
        if ((new Keys())->primary($table)?->visible === false) {
            throw SchemaError::PrimaryKeyInvisible->error();
        }
    }

    /**
     * Replaces visibility without losing the other index options.
     */
    public function change(IndexDefinition $index, bool $visible): IndexDefinition
    {
        $options = array_values(array_filter($index->options, static fn ($option): bool => !$option instanceof IndexVisibility));
        $options[] = new IndexVisibility($visible);

        return new IndexDefinition($index->kind, $index->parts, $index->name, $index->algorithm, $options, $index->constraint, $index->keyword);
    }
}
