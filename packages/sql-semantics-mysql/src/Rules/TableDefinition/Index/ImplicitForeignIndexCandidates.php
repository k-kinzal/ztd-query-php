<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\DeclaredTableStorage;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ForeignKeyDeclarations;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Finds each native child-side support index before its physical name is allocated.
 * @visibility SqlSemantics
 */
final class ImplicitForeignIndexCandidates
{
    /**
     * Keeps only the necessary generated keys and their original positions.
     * @param array<array-key, array{Name, bool, bool}> $columns Canonical fields by native case
     * @param list<list<IndexCandidate>> $declared Explicit keys by element in statement order
     * @return array<int, IndexCandidate>|null
     */
    public function collect(CreateTable $definition, array $columns, array $declared, GrammarRelease $release): ?array
    {
        if (strcasecmp((new DeclaredTableStorage())->resolve($definition), 'InnoDB') !== 0) {
            return [];
        }
        $rules = new ForeignKeyDeclarations();
        $inline = $rules->inline($release);
        $directions = (new TableIndexCandidates())->storesDirection($release);
        $proposals = [];
        foreach ($definition->elements as $position => $element) {
            if ((!$element instanceof ForeignKey && !$element instanceof ColumnDefinition) || $rules->reference($element, $inline) === null) {
                continue;
            }
            if ($definition->temporary()) {
                return null;
            }
            $key = $this->propose($element, $columns, $directions);
            if ($key === null) {
                return null;
            }
            $proposals[$position] = $key;
        }

        return $this->prune($proposals, array_merge(...$declared));
    }

    /**
     * Derives a generated key's exact physical field prefix and requested name.
     * @param array<array-key, array{Name, bool, bool}> $columns Known native fields
     */
    public function propose(ForeignKey|ColumnDefinition $element, array $columns, bool $directions): ?IndexCandidate
    {
        $parts = $element instanceof ForeignKey ? $element->columns : [new ColumnPart($element->name->column)];
        $symbol = $element instanceof ForeignKey ? ($element->constraint->name ?? $element->name)?->column : null;
        $case = new NativeIdentifierCase();
        $prefix = [];
        foreach ($parts as $part) {
            $column = $columns[$case->fold($part->column->value)] ?? null;
            if ($column === null || $part->length !== null || (!$directions && $part->direction !== null)) {
                return null;
            }
            $prefix[] = [$column[0], $part->direction === Direction::Descending];
        }
        $first = $prefix[0][0];
        $increment = $columns[$case->fold($first->value)][2] ?? false;

        return new IndexCandidate(IndexKind::Index, $symbol, $first, true, false, $increment ? $first : null, $prefix, implicitForeignKey: true);
    }

    /**
     * Removes any proposal subsumed by an explicit or longer native key.
     * @param array<int, IndexCandidate> $proposals Ordered generated alternatives with original positions
     * @param list<IndexCandidate> $declared Every explicit key in the original table
     * @return array<int, IndexCandidate>
     */
    public function prune(array $proposals, array $declared): array
    {
        $retained = [];
        foreach ($proposals as $position => $required) {
            foreach ($declared as $key) {
                if ($this->covers($key, $required)) {
                    continue 2;
                }
            }
            foreach ($proposals as $rival => $candidate) {
                if (($rival > $position || count($candidate->prefix) > count($required->prefix)) && $this->covers($candidate, $required)) {
                    continue 2;
                }
            }
            $retained[$position] = $required;
        }

        return $retained;
    }

    /**
     * Checks matching full ordered names and native directions within a B-tree prefix.
     */
    public function covers(IndexCandidate $available, IndexCandidate $required): bool
    {
        if ($available->kind === IndexKind::FullText || $available->kind === IndexKind::Spatial) {
            return false;
        }
        $case = new NativeIdentifierCase();
        foreach ($required->prefix as $slot => [$field, $descending]) {
            [$seen, $direction] = $available->prefix[$slot] ?? [null, null];
            if ($seen === null || $case->fold($seen->value) !== $case->fold($field->value) || $direction !== $descending) {
                return false;
            }
        }

        return $required->prefix !== [];
    }
}
