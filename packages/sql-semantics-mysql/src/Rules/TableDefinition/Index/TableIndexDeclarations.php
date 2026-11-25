<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Resolves physical index names from the ordered declaration of an ordinary table.
 * @visibility SqlSemantics
 */
final class TableIndexDeclarations
{
    /**
     * Resolves the full index name set when it is determined by the declaration.
     * @param list<array{ColumnDefinition|null, Column}> $columns The effective ordered column definitions
     * @return list<IndexDeclaration>|null
     */
    public function collect(CreateTable $definition, array $columns, GrammarRelease $release): ?array
    {
        if ($definition->partitioning !== null) {
            return null;
        }
        $candidates = new TableIndexCandidates();
        $columnNames = $candidates->fields($columns);
        if ($columnNames === null) {
            return null;
        }
        $explicit = [];
        foreach ($definition->elements as $element) {
            $items = $candidates->keys($element, $columnNames, $release);
            if ($items === null) {
                return null;
            }
            $explicit[] = $items;
        }
        $implicit = (new ImplicitForeignIndexCandidates())->collect($definition, $columnNames, $explicit, $release);
        if ($implicit === null) {
            return null;
        }
        foreach ($implicit as $position => $candidate) {
            $explicit[$position][] = $candidate;
        }

        return $this->allocate(array_merge(...$explicit), count(array_filter(array_column($columnNames, 2))), $release);
    }

    /**
     * Assigns each retained physical name in the declared order and marks the clustered key.
     * @param list<IndexCandidate> $candidates The complete ordered physical keys
     * @return list<IndexDeclaration>|null
     */
    public function allocate(array $candidates, int $generatingFields, GrammarRelease $release): ?array
    {
        $case = new NativeIdentifierCase();
        $allocate = new IndexNameAllocation();
        $used = [];
        $firstCandidate = null;
        foreach ($candidates as $candidate) {
            $first = $candidate->firstField;
            $name = $candidate->writtenName ?? ($first === null ? null : $allocate->generated($first, $used, $release));
            if ($name === null || preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]{1,64}(?<! )\z/u', $name->value) !== 1) {
                return null;
            }
            $key = $case->fold($name->value);
            if (isset($used[$key]) || ($key === 'primary') !== ($candidate->kind === IndexKind::Primary)) {
                return null;
            }
            $used[$key] = $candidate->declaration($name);
            if ($candidate->clusterCandidate) {
                $firstCandidate ??= $key;
            }
        }
        $backing = array_filter($used, static fn (IndexDeclaration $index): bool => $index->autoIncrementField !== null);
        $maximumGeneratingFields = min(1, count($backing));
        if (count($used) > 64 || $generatingFields > $maximumGeneratingFields) {
            return null;
        }
        if ($firstCandidate !== null && !isset($used['primary'])) {
            $unique = $used[$firstCandidate];
            $used[$firstCandidate] = new IndexDeclaration($unique->name, PhysicalIndexKind::Unique, true, true, $unique->autoIncrementField, $unique->leadingFields, true);
        }

        return array_values($used);
    }
}
