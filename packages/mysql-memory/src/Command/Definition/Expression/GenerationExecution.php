<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;
use WeakReference;

/**
 * Executes legacy generation rewrites through the ordinary declaration pipeline.
 * The displayed stored expression comes from the original semantic operands.
 *
 * @visibility MySqlMemory
 */
final class GenerationExecution
{
    /**
     * Uses the preparation database for binding and execution, restoring session state.
     */
    public function run(M\Table\CreateTable|M\Alter\AlterTable|M\Routine\Program\Write\DeferredCreateTable $statement, Session $session, string $database): Reply
    {
        $saved = $session->variables->database;
        $session->variables->database = $database;
        try {
            if ($statement instanceof M\Routine\Program\Write\DeferredCreateTable) {
                return (new \MySqlMemory\Program\Expression\TableUse())->raise($statement, $session, false);
            }
            $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement);
            $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
            $connection = new Connection($session->variables, $context, session: WeakReference::create($session));
            $command = $statement instanceof M\Table\CreateTable ? new \MySqlMemory\Command\Definition\CreateTableCommand() : new \MySqlMemory\Command\Definition\AlterTableCommand();
            $reply = $command->execute($operation, $session, $context, $connection);
            $session->variables->rowCount = $reply instanceof \MySqlMemory\Result\Completion ? $reply->affectedRows : -1;
            return $reply;
        } finally {
            $session->variables->database = $saved;
        }
    }

    /**
     * Stores canonical expression display independently of the compiled row computation.
     *
     * @param array<int, \SqlSemantics\Statement\Scalar> $parameters Bound values by marker occurrence identity
     */
    public function display(\MySqlMemory\Dictionary\StoredTable $table, Node $source, Session $session, array $parameters = []): void
    {
        $columns = $table->definition->columns;
        $names = [];
        foreach ($columns as $column) {
            $names[mb_strtolower($column->name)] = $column->name;
        }
        $written = array_map((new GenerationText())->parameter(...), $parameters);
        $writer = new GenerationText(new ItemText($names, $session->settings()->connectionCollation->charset->name, $session->settings()->release(), $written), $parameters);
        foreach ((new Walker())->find($source, M\Table\Column\ColumnDefinition::class) as $element) {
            if (!$element->specification instanceof M\Table\Column\Generation\DeferredGeneratedColumn) {
                continue;
            }
            foreach ($columns as $position => $column) {
                if (strcasecmp($column->name, $element->name->column->value) === 0 && $column->generated !== null) {
                    $columns[$position] = $column->withGeneration($column->generated, $column->stored, implode('', array_column($writer->parts($element->specification->expression), 0)));
                }
            }
        }
        $table->definition = $table->definition->withColumns($columns);
    }

    /**
     * Resolves variable names and scopes before table lookup or omission of their values.
     */
    public function variables(Node $definition, Session $session): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context(session: $session->resolution()));
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->nodes($definition, $derivation->context->relationNames) as $variable) {
            if ($variable instanceof M\Variable\SystemVariable) {
                $variable->deriveScalar($derivation, $derivation->environment());
            }
        }
        foreach ($derivation->facts()->diagnostics as $problem) {
            throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
        }
    }
}
