<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Binding;

use LogicException;
use MySqlMemory\Dictionary\Temporaries;
use MySqlMemory\Dictionary\View;
use SqlSemantics\Statement\Declaration\Table;

/**
 * An immutable catalog input shared by binding and execution.
 * It contains values only: reading a view cannot invoke analysis or change a dictionary.
 *
 * @visibility MySqlMemory
 */
final class Declarations
{
    /**
     * @param list<Table> $tables Persistent declarations with bound view output types
     * @param array<int, ViewBinding> $views Bindings by immutable stored definition identity
     * @param array<string, true> $schemas Schemas present when the snapshot was captured
     */
    public function __construct(private readonly array $tables = [], private readonly array $views = [], private readonly array $schemas = [])
    {
    }

    /**
     * Answers captured declarations with the calling session's explicit temporary shadows.
     *
     * @return list<Table>
     */
    public function tables(?Temporaries $temporaries = null): array
    {
        if ($temporaries === null) {
            return $this->tables;
        }
        $tables = [];
        foreach ($temporaries->tables as $stored) {
            foreach ($stored as $table) {
                if (isset($this->schemas[$table->definition->schema])) {
                    $tables[] = $table->definition->declaration;
                }
            }
        }
        foreach ($this->tables as $table) {
            if ($temporaries->table($table->name->schema->value ?? '', $table->name->name->value) === null) {
                $tables[] = $table;
            }
        }
        return $tables;
    }

    /**
     * Answers a binding from this snapshot; later dictionary changes cannot alter it.
     *
     * @throws LogicException When the definition does not belong to this snapshot
     */
    public function view(View $view): ViewBinding
    {
        return $this->views[spl_object_id($view)] ?? throw new LogicException('The view definition does not belong to this declaration snapshot.');
    }
}
