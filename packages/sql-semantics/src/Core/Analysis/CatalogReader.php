<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\DeclarationProvider;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\Script\Sequence;
use SqlSemantics\Statement\SemanticGraph;

/**
 * Collects the explicit declaration context; schema operations are never applied as history.
 * @visibility SqlSemantics
 */
final class CatalogReader
{
    /**
     * Preserves distinct conflicting declarations and deduplicates only identical objects.
     * @return list<Table>
     */
    public function tables(Table|Operation ...$dependencies): array
    {
        $tables = [];
        foreach ($dependencies as $dependency) {
            \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($dependency), 'Declaration context contains only immutable semantic values.');
            $declarations = match (true) {
                $dependency instanceof Table => [$dependency],
                $dependency instanceof DeclarationProvider => $dependency->declaredTables(),
                $dependency instanceof Sequence => $this->tables(...$dependency->operations),
                default => [],
            };
            foreach ($declarations as $table) {
                $tables[spl_object_id($table)] = $table;
            }
        }
        return array_values($tables);
    }
}
