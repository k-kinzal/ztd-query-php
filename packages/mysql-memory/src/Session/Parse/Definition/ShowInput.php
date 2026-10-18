<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Program\Expression\RowUse;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use WeakReference;

/**
 * Prepares a refused SHOW filter in its retained inspection scope.
 *
 * Modern schema inspections open the named database before resolving the
 * filter. A missing default database for an unqualified nested table is an
 * earlier condition. Legacy inspections prepare their filters first.
 *
 * @visibility MySqlMemory
 */
final class ShowInput
{
    /**
     * Preserves the inspected database and WHERE output-column scope.
     */
    public function error(RefusedFilter $problem, Session $session): SqlError
    {
        try {
            return $this->resolve($problem, $session);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Resolves retained operands without bypassing the input adapter's earlier notices.
     *
     * @throws SqlError When opening or preparing a dependency fails
     */
    public function resolve(RefusedFilter $problem, Session $session): SqlError
    {
        $operand = $problem->condition->operand;
        (new \MySqlMemory\Session\Problem\Identifiers())->check($operand, $session->settings()->release());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $fact = $derivation->relation($problem->header, $derivation->environment());
        $derivation->scalar($operand, new Environment($context, null, [new VisibleRelation($problem->header, $fact->shape)]));
        $diagnostics = $derivation->facts()->diagnostics;
        foreach ($diagnostics as $diagnostic) {
            if ($database === '' && $diagnostic instanceof MissingTable && $diagnostic->name->schema === null) {
                return QueryError::NoDatabase->error();
            }
        }
        $selected = $this->selected($problem, $session);
        if ($selected === '') {
            return QueryError::NoDatabase->error();
        }
        if (!$session->preparation->inputPrepared && !$session->settings()->legacy() && $session->instance->dictionary->schema($selected) === null) {
            return QueryError::BadDatabase->error($selected);
        }
        foreach ($diagnostics as $diagnostic) {
            if (Stages::parsed($diagnostic) || $diagnostic instanceof MissingTable) {
                return (new Errors())->error($diagnostic, $session, 'where clause');
            }
        }
        $this->prepare($problem, $derivation, $session);
        foreach ($diagnostics as $diagnostic) {
            return (new Errors())->error($diagnostic, $session, 'where clause');
        }
        return (new RowUse())->error($operand);
    }

    /**
     * Binds filter dependencies without evaluating the refused condition.
     */
    public function prepare(RefusedFilter $problem, Derivation $derivation, Session $session): void
    {
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($problem->condition->operand, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        $headings = match (true) {
            $problem->header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys => (new \MySqlMemory\Command\Show\ShowKeysCommand())->headings(),
            $problem->header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns => (new \MySqlMemory\Command\Show\ShowColumnsCommand())->headings($problem->header->listing?->full() === true, $session->settings()->legacy(), $session->modes()->strict()),
            $problem->header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables => (new \MySqlMemory\Command\ShowTablesCommand())->headings((new \SqlSemantics\Statement\Operation($derivation->context, $problem->header)), $session),
            $problem->header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus => $session->settings()->legacy() ? (new \MySqlMemory\Command\Show\ShowTableStatusCommand())->legacyHeadings() : (new \MySqlMemory\Command\Show\ShowTableStatusCommand())->headings(false),
            default => (new \MySqlMemory\Command\Program\ShowTriggersCommand())->headings($session->settings()->release()),
        };
        $domains = array_map(static fn (\MySqlMemory\Command\Show\Heading $heading): \MySqlMemory\Typing\Domain => $heading->domain(), $headings);
        $scope = new \MySqlMemory\Evaluation\Scope();
        $scope->place($problem->header, $domains);
        (new RowUse())->prepare($problem->condition->operand, $planner->compiler, $scope);
    }
    /**
     * Resolves the inspection identity before preparing a refused filter.
     *
     * @throws SqlError When modern text execution cannot open its inspection target
     */
    public function selected(RefusedFilter $problem, Session $session): string
    {
        $table = $problem->header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns || $problem->header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
        if ($table && !$session->preparation->inputPrepared && !$session->settings()->legacy()) {
            (new \MySqlMemory\Command\Show\Inspection())->check($problem->header, $session, $session->preparation->inputPrepared);
        }
        return $problem->header->database->value ?? ($table ? $problem->header->table->name->schema->value ?? $session->variables->database : $session->variables->database);
    }
}
