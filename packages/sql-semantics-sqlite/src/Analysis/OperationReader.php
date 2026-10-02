<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\MaintenanceReader;
use SqlSemantics\Platform\Sqlite\SchemaChangeReader;
use SqlSemantics\Platform\Sqlite\TransactionReader;
use SqlSemantics\Statement\Inspection\ExplainPlan;
use SqlSemantics\Statement\Inspection\ExplainProgram;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Script\Sequence;

/**
 * Lowers statement boundaries into concrete operations against one unchanged declaration context.
 * @visibility SqlSemantics
 */
final class OperationReader implements \SqlSemantics\Core\Policy\OperationRules
{
    /**
     * Reads the complete input, retaining operation order without simulating earlier requests.
     */
    public function read(Node $source, Catalog $catalog): Operation
    {
        assert($source->name === 'input', 'Operation analysis starts at the complete SQLite input.');
        $operations = $this->statements($source, $catalog);
        return count($operations) === 1 ? $operations[0] : new Sequence(...$operations);
    }

    /**
     * Every command sees the same supplied declarations, including after ALTER and DROP.
     * @return list<Operation>
     */
    public function statements(Node $source, Catalog $catalog): array
    {
        $operations = [];
        foreach (Tree::outer($source, ['ecmd']) as $boundary) {
            $commands = Tree::outer($boundary, ['cmd']);
            if ($commands === []) {
                continue;
            }
            assert(count($commands) === 1, 'A statement boundary owns exactly one command.');
            $operation = $this->command($commands[0], $catalog);
            $explain = Tree::child($boundary, ['explain']);
            $operations[] = $explain === null ? $operation : match (strtoupper(Tree::text($explain))) {
                'EXPLAIN' => new ExplainProgram($operation),
                'EXPLAIN QUERY PLAN' => new ExplainPlan($operation),
                default => Tree::unsupported($explain, 'inspection request'),
            };
        }
        return $operations;
    }

    /**
     * Dispatches semantic operation families; no grammar model is used as a fallback.
     */
    public function command(Node $source, Catalog $catalog): Operation
    {
        assert($source->name === 'cmd', 'A command reader receives one complete command.');
        if (Tree::child($source, ['create_table']) !== null) {
            return (new CreateTableReader())->read($source, $catalog->profile);
        }
        if (Tree::child($source, ['insert_cmd']) !== null) {
            return (new InsertionReader())->read($source, $catalog);
        }
        $query = Tree::child($source, ['select']);
        if ($query !== null) {
            Tree::assertChildren($source, ['select'], []);
            $operation = (new QueryReader())->read($query, $catalog);
            return $operation;
        }
        $tokens = $source->tokens();
        return match (strtoupper($tokens[0]->text)) {
            'BEGIN', 'COMMIT', 'END', 'ROLLBACK', 'SAVEPOINT', 'RELEASE' => (new TransactionReader())->read($source),
            'VACUUM' => (new DatabaseReader())->vacuum($source, $catalog),
            'ATTACH', 'DETACH' => (new DatabaseReader())->attachment($source, $catalog),
            'REINDEX', 'ANALYZE' => (new MaintenanceReader())->read($source),
            'UPDATE', 'DELETE' => (new MutationReader())->read($source, $catalog),
            'DROP' => (new SchemaChangeReader())->read($source, $catalog),
            'ALTER' => Tree::child($source, ['add_column_fullname']) === null
                ? (new SchemaChangeReader())->read($source, $catalog)
                : Tree::unsupported($source, 'column addition'),
            default => Tree::unsupported($source, 'semantic operation'),
        };
    }
}
