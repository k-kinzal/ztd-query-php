<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Partition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\LocatedErrors;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Source\SourceMap;

/**
 * Resolves the retained operand before formatting an intrinsic partition refusal.
 * Variable catalog errors and contextual warnings still precede the role error.
 *
 * @visibility MySqlMemory
 */
final class Operand
{
    /**
     * Resolves the completed table name before contextual operand restrictions.
     */
    public function database(\SqlSemantics\Construction\RejectedInput $failure, string $statement, Session $session): ?SqlError
    {
        return $failure->problem instanceof InvalidPartitionExpression || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount || ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar && $failure->problem->condition === OperandCondition::Subquery)
            ? (new \MySqlMemory\Session\Parse\Definition\DefinitionPrefix())->database($statement, $failure->end, $session, 'TABLE_SYM') : null;
    }

    /**
     * Places retained operand notices after grammar notices and preceding engine selection.
     *
     * @template T of object
     * @param list<array{int, T}> $notices Completed input notices
     * @return array{list<array{int, T|Deprecation|SqlError}>, SqlError}
     */
    public function ordered(\SqlSemantics\Construction\RejectedInput $failure, array $notices, SqlError $error, string $statement, Session $session): array
    {
        if ($failure->problem instanceof InvalidPartitionExpression) {
            foreach ($this->notices($failure->problem, $session) as $notice) {
                $notices[] = [$failure->end, $notice];
            }
        }
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong || $failure->problem instanceof InvalidPartitionExpression) {
            return (new \MySqlMemory\Session\Parse\EnginePrefix())->reached($notices, (new \MySqlMemory\Session\Parse\EnginePrefix())->completed($statement, $failure->end, $session), $error, $session->modes()->has('NO_ENGINE_SUBSTITUTION'));
        }
        return [$notices, $error];
    }

    /**
     * Formats the first reached operand condition using numeric occurrence ranges.
     */
    public function error(InvalidPartitionExpression $problem, SourceMap $sources, Session $session): SqlError
    {
        foreach ($this->facts($problem, $session)->diagnostics as $diagnostic) {
            if (Stages::parsed($diagnostic) || Stages::characters($diagnostic, $session->settings()->legacy())) {
                return (new Errors())->error($diagnostic, $session);
            }
        }
        $error = (new LocatedErrors())->located($problem, $sources, $session);
        \SqlSemantics\Diagnostic\Check::invariant($error !== null, 'A partition operand refusal has a located error.');
        return $error;
    }

    /**
     * @return list<Deprecation> Contextual notices not already emitted by the input grammar
     */
    public function notices(InvalidPartitionExpression $problem, Session $session): array
    {
        return array_values(array_filter($this->facts($problem, $session)->warnings, static fn ($warning): bool => $warning instanceof Deprecation && $warning->phase() === NoticePhase::Contextualization));
    }

    /**
     * Modern partition subqueries are refused before interpreting their contents.
     */
    public function facts(InvalidPartitionExpression $problem, Session $session): StatementSemantics
    {
        $derivation = new Derivation($session->semantics()->context([], session: $session->resolution()));
        if ($session->settings()->legacy() || (new PartitionExpressions())->condition($problem->expression, $session->settings()->release()) !== OperandCondition::Subquery) {
            $derivation->scalar(\SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($problem->expression), $derivation->environment());
        }
        return (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation);
    }
}
