<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint\FullText;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Evaluation\Context;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Retains the SQL-visible first full-text index transition of an InnoDB table.
 *
 * Native 5.6 and 5.7 report warning 124 when adding their implicit document ID.
 * Dropping the index does not undo that history. No physical document ID is
 * allocated by memory; only this observable table history is retained.
 *
 * @visibility MySqlMemory
 */
final class History
{
    /**
     * Records a completed layout transition, after definition and row checks succeed.
     */
    public function changed(StoredTable $table, TableDefinition $definition, Context $context): void
    {
        if (strcasecmp($definition->engine, 'InnoDB') !== 0) {
            $table->fullTextDocumentId = false;
            return;
        }
        if ($table->fullTextDocumentId || array_filter($definition->keys, static fn (Key $key): bool => $key->kind === KeyKind::FullText) === []) {
            return;
        }
        if ($definition->position('FTS_DOC_ID') === null && in_array($context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $context->diagnostics->warning(124, 'InnoDB rebuilding table to add column FTS_DOC_ID');
        }
        $table->fullTextDocumentId = true;
    }
}
