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
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Resolves physical index names from the ordered declaration of an ordinary table.
 *
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
        $case = new NativeIdentifierCase();
        $candidates = new TableIndexCandidates();
        $columnNames = $candidates->fields($columns);
        if ($columnNames === null) {
            return null;
        }
        $used = [];
        $firstCandidate = null;
        foreach ($definition->elements as $element) {
            if ($element instanceof ForeignKey) {
                return null;
            }
            $items = $candidates->keys($element, $columnNames);
            if ($items === null) {
                return null;
            }
            foreach ($items as [$kind, $name, $first, $ordinary, $clusterCandidate]) {
                $resolved = $name ?? ($first === null ? null : (new IndexNameAllocation())->generated($first, $used, $release));
                if ($resolved === null || preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]{1,64}(?<! )\z/u', $resolved->value) !== 1) {
                    return null;
                }
                $key = $case->fold($resolved->value);
                if (isset($used[$key]) || ($key === 'primary') !== ($kind === IndexKind::Primary)) {
                    return null;
                }
                $used[$key] = new IndexDeclaration($resolved, PhysicalIndexKind::from($kind->value), $ordinary);
                if ($clusterCandidate) {
                    $firstCandidate ??= $key;
                }
            }
        }
        if (count($used) > 64) {
            return null;
        }
        if ($firstCandidate !== null && !isset($used['primary'])) {
            $unique = $used[$firstCandidate];
            $used[$firstCandidate] = new IndexDeclaration($unique->name, PhysicalIndexKind::Unique, true, true);
        }

        return array_values($used);
    }
}
