<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Definition;

use LogicException;
use MySqlMemory\Command\Definition\AlterTableCommand;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Session\Resolution\IndexPreparation;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;

/**
 * Binds retained index operands and reports their reached condition after target lookup.
 *
 * @visibility MySqlMemory
 */
final class IndexUse
{
    /**
     * Binds the complete request and prepares its header without opening the target.
     */
    public function header(CreateIndex $definition, Session $session, ?string $database = null): IndexPreparation
    {
        (new \MySqlMemory\Command\Definition\Expression\GenerationExecution())->variables($definition, $session);
        $database = $definition->table->schema->value ?? $database ?? $session->variables->database;
        if ($database === '') {
            throw QueryError::NoDatabase->error();
        }
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, new SearchPath($database), $session->resolution());
        $definition = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($definition);
        $derivation = new Derivation($context);
        $definition->deriveStatement($derivation);
        $facts = (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation);
        foreach ($facts->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem) || \MySqlMemory\Session\Problem\Stages::characters($problem, $session->settings()->legacy())) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
            }
        }
        $alter = new AlterTableCommand();
        [, $commands] = $alter->request($definition);
        $alter->read($commands, $facts, $session->settings()->legacy());
        return new IndexPreparation($definition, $facts);
    }

    /**
     * Checks target and dependency state before the retained name or operand condition.
     *
     * @throws \MySqlMemory\Error\SqlError When lookup or the retained key is refused
     * @throws LogicException When a retained key reaches execution without its required condition
     */
    public function raise(CreateIndex $definition, Session $session, ?string $database = null): never
    {
        $prepared = $this->header($definition, $session, $database);
        $session->transaction->commit();
        (new \MySqlMemory\Session\Problem\Definition\FunctionalQueries())->prepare($prepared, $session, new \MySqlMemory\Session\Problems());
        $alter = new AlterTableCommand();
        $alter->table($session, $definition->table->schema->value ?? $database ?? $session->variables->database, $definition->table->name->value);
        $alter->resolve($prepared, $session);
        throw new LogicException('A retained index instruction must report its name or operand condition.');
    }
}
