<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Row\RowChange;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\Row\TableTransition;

/**
 * Works out what a statement did to each table, by comparing the two shadows.
 *
 * Nothing records what a rewritten statement changed, so it is recovered by
 * pairing the rows of the shadow before against the rows after. Where a table
 * declares a key, the pairing is by that key, which is what tells an update
 * apart from a delete followed by an insert; where it declares none, only an
 * identical row can be said to be the same row.
 *
 * An UPDATE is the one case where the pairing is known rather than guessed:
 * the result rows carry both the old key and the new one, so those pairs are
 * taken first and the guessing only fills in what they leave.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class TableTransitions
{
    /**
     * @param TableDefinitionRegistry $registry Answers what a table declares
     * @param RowMatch $rows Finds a row among rows
     * @param MutationRowIdentity $identity Reads the old and new key off a result row
     */
    public function __construct(
        private readonly TableDefinitionRegistry $registry,
        private readonly RowMatch $rows = new RowMatch(),
        private readonly MutationRowIdentity $identity = new MutationRowIdentity(),
    ) {
    }

    /**
     * Answers what happened to every table the statement could have touched.
     *
     * @param ShadowStore $before Shadow as it was
     * @param ShadowStore $after Shadow as it became
     * @param ShadowMutation $mutation Statement that was simulated
     * @param list<Row> $resultRows Rows the rewritten statement read back
     *
     * @return list<TableTransition> One entry per table something happened to
     */
    public function of(ShadowStore $before, ShadowStore $after, ShadowMutation $mutation, array $resultRows): array
    {
        $transitions = [];
        $identityTable = $mutation instanceof UpdateMutation ? $mutation->tableName() : null;

        foreach (array_keys($before->getAll()) as $table) {
            $definition = $this->registry->get($table);
            $transition = $this->between(
                $table,
                $before->get($table),
                $after->get($table),
                $definition->primaryKeys ?? [],
                $identityTable === $table ? $resultRows : [],
            );
            if (!$transition->isEmpty()) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    /**
     * Answers what happened to one table.
     *
     * @param string $table Table being compared
     * @param list<Row> $beforeRows Its rows as they were
     * @param list<Row> $afterRows Its rows as they became
     * @param list<string> $primaryKeys Columns that identify one of its rows
     * @param list<Row> $identityRows Result rows carrying both the old key and the new one
     *
     * @return TableTransition What happened to it
     */
    public function between(
        string $table,
        array $beforeRows,
        array $afterRows,
        array $primaryKeys,
        array $identityRows,
    ): TableTransition {
        $matchedBefore = [];
        $matchedAfter = [];
        $updated = [];

        if ($primaryKeys === []) {
            foreach ($beforeRows as $beforeIndex => $beforeRow) {
                $afterIndex = $this->rows->positionOfIdentical($afterRows, $beforeRow, $matchedAfter);
                if ($afterIndex !== null) {
                    $matchedBefore[] = $beforeIndex;
                    $matchedAfter[] = $afterIndex;
                }
            }

            return new TableTransition($table, $this->unmatched($beforeRows, $matchedBefore), []);
        }

        foreach ($identityRows as $resultRow) {
            $change = $this->identity->extract($resultRow, $primaryKeys);
            $beforeIndex = $this->rows->positionOfSameKey($beforeRows, $change['identity'], $primaryKeys, $matchedBefore);
            $afterIndex = $this->rows->positionOfSameKey($afterRows, $change['row'], $primaryKeys, $matchedAfter);
            if ($beforeIndex === null || $afterIndex === null) {
                continue;
            }
            $matchedBefore[] = $beforeIndex;
            $matchedAfter[] = $afterIndex;
            if ($beforeRows[$beforeIndex] !== $afterRows[$afterIndex]) {
                $updated[] = new RowChange($beforeRows[$beforeIndex], $afterRows[$afterIndex]);
            }
        }

        foreach ($beforeRows as $beforeIndex => $beforeRow) {
            if (in_array($beforeIndex, $matchedBefore, true)) {
                continue;
            }
            $afterIndex = $this->rows->positionOfSameKey($afterRows, $beforeRow, $primaryKeys, $matchedAfter);
            if ($afterIndex === null) {
                continue;
            }
            $matchedBefore[] = $beforeIndex;
            $matchedAfter[] = $afterIndex;
            if ($beforeRow !== $afterRows[$afterIndex]) {
                $updated[] = new RowChange($beforeRow, $afterRows[$afterIndex]);
            }
        }

        return new TableTransition($table, $this->unmatched($beforeRows, $matchedBefore), $updated);
    }

    /**
     * Answers the rows nothing was paired with, which are the ones that went.
     *
     * @param list<Row> $rows Rows as they were
     * @param list<int> $matched Positions that were paired off
     *
     * @return list<Row> The rows that were not
     */
    public function unmatched(array $rows, array $matched): array
    {
        $gone = [];
        foreach ($rows as $index => $row) {
            if (!in_array($index, $matched, true)) {
                $gone[] = $row;
            }
        }

        return $gone;
    }
}
