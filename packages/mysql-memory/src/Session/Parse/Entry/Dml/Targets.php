<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Dml;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget;

/**
 * Resolves the ordered database dependencies of a refused DELETE target prefix.
 *
 * @visibility MySqlMemory
 */
final class Targets
{
    /**
     * Resolves earlier target-database and input conditions from retained semantic values.
     * From 5.7 onward an unknown charset is a grammar condition preceding the
     * target database. MySQL 5.6 resolves that target prefix first.
     *
     * @throws LogicException When an intrinsic target refusal has no resolution diagnostic
     */
    public function unmatched(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets $problem, Session $session): SqlError
    {
        $problem = $this->fresh($problem);
        $database = $problem->program->schema->value ?? $session->variables->database;
        if ($problem->program !== null && $database === '') {
            return QueryError::NoDatabase->error();
        }
        $missingDatabase = $database === '' && array_filter($problem->targets, static fn (\SqlSemantics\Statement\Identifier\QualifiedName $target): bool => $target->schema === null) !== [];
        if ($missingDatabase && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return QueryError::NoDatabase->error();
        }
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new \SqlSemantics\Contract\SearchPath($database), $session->resolution());
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $problem->derive($derivation);
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics as $diagnostic) {
            if (\MySqlMemory\Session\Problem\Stages::characters($diagnostic, $session->settings()->legacy())) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($diagnostic, $session);
            }
        }
        if ($missingDatabase) {
            return QueryError::NoDatabase->error();
        }
        $alias = null;
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics as $diagnostic) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($diagnostic)) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($diagnostic, $session);
            }
            if (\MySqlMemory\Session\Problem\Stages::closing($diagnostic)) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($alias ?? $diagnostic, $session);
            }
            $alias ??= $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable ? $diagnostic : null;
        }
        if ($alias !== null) {
            return (new \MySqlMemory\Session\Problem\Errors())->error($alias, $session);
        }
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($diagnostic, $session);
            }
        }
        throw new LogicException('A refused DELETE must resolve an absent target.');
    }

    /**
     * Formats the first condition from typed names without reading source SQL.
     *
     * @throws LogicException When the retained prefix violates its construction invariant
     */
    public function error(RepeatedDeleteTarget $problem, Session $session): SqlError
    {
        $database = $problem->program->schema->value ?? $session->variables->database;
        if ($problem->program !== null && $database === '') {
            return QueryError::NoDatabase->error();
        }
        $seen = [];
        foreach ($problem->targets as $target) {
            if ($target->schema === null && $database === '') {
                return QueryError::NoDatabase->error();
            }
            $key = serialize([$target->schema->value ?? $database, $target->name->value]);
            if (isset($seen[$key])) {
                return QueryError::NonUniqueTable->error($target->name->value);
            }
            $seen[$key] = true;
        }

        throw new LogicException('A refused DELETE prefix must contain a repeated target.');
    }
    /**
     * Copies retained DELETE dependencies before binding them in another scope.
     */
    public function fresh(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets $problem): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets
    {
        return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets(
            $problem->targets,
            array_map(static fn ($table) => \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($table), $problem->tables),
            $problem->with === null ? null : \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($problem->with),
            $problem->where === null ? null : \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($problem->where),
            $problem->program,
        );
    }
}
