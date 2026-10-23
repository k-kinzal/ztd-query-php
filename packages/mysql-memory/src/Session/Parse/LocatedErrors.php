<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Operation;

/**
 * Formats semantic diagnostics that quote their occurrence in the original parser input.
 *
 * Source ranges belong to the analyzed operation; equal expressions elsewhere in a statement
 * cannot supply the location. A directly constructed operation has no original input location.
 *
 * @visibility MySqlMemory
 */
final class LocatedErrors
{
    /**
     * Answers the original SQL tail and line of a forbidden construct, or null for another diagnostic.
     */
    public function error(Diagnostic $problem, Operation $operation, Session $session): ?SqlError
    {
        return $this->located($problem, $operation->sources, $session);
    }

    /**
     * Formats an occurrence from either an operation or a retained input refusal.
     */
    public function located(Diagnostic $problem, \SqlSemantics\Statement\Source\SourceMap $sources, Session $session): ?SqlError
    {
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar && $problem->condition === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery) {
            $origin = $sources->of($problem->operand);
            $offset = $this->subquery($problem->operand, $sources) ?? $origin?->offset;
            return $offset === null ? new SqlError(StatementError::ParseError, $problem->message()) : (new \MySqlMemory\Session\Syntax($session->resolution()->client))->near($offset, $session->text, $session->following, new \SqlSemantics\Construction\RejectedInput($problem, $offset));
        }
        if (!$problem instanceof InvalidPartitionExpression) {
            return null;
        }
        $origin = $sources->of($problem->expression);
        if ($origin === null) {
            return new SqlError(StatementError::ParseError, $problem->message());
        }
        $offset = $origin->offset;
        if (!$session->settings()->legacy() && (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions())->condition($problem->expression, $session->settings()->release()) === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery) {
            $offset = $this->subquery($problem->expression, $sources) ?? $offset;
            return (new \MySqlMemory\Session\Syntax($session->resolution()->client))->near($offset, $session->text, $session->following, new \SqlSemantics\Construction\RejectedInput($problem, $offset));
        }
        if (in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $offset += $origin->length;
            foreach ($session->semantics()->parser()->tokenize($session->text) as $token) {
                if ($token->offset >= $offset && $token->text !== '') {
                    $offset = $token->offset;
                    break;
                }
            }
        }
        $tail = mb_strcut(rtrim(substr($session->text, $offset) . $session->following), 0, 80, 'UTF-8');
        $line = substr_count(ltrim(substr($session->text, 0, $offset)), "\n") + 1;

        return new SqlError(StatementError::ParseError, $problem->message() . " near '" . $tail . "' at line " . $line);
    }

    /**
     * Locates the first query occurrence rather than its enclosing scalar function.
     */
    public function subquery(\SqlSemantics\Statement\Operand $operand, \SqlSemantics\Statement\Source\SourceMap $sources): ?int
    {
        $offsets = [];
        $graph = new \SqlSemantics\Validation\ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\MySql\\Statement\\']);
        foreach ($graph->objects($operand) as $part) {
            $origin = $part instanceof \SqlSemantics\Statement\Query ? $sources->of($part) : null;
            if ($origin !== null) {
                $offsets[] = $origin->offset;
            }
        }
        return $offsets === [] ? null : min($offsets);
    }

}
