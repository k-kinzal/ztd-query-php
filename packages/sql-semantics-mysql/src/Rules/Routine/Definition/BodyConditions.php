<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyArrays;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyBindings;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine as R;
use SqlSemantics\Statement\Node;

/**
 * Derives definition-time conditions without discarding a complete instruction body.
 *
 * The same typed operands are checked for parsed and directly constructed declarations.
 * Input sources order and format notices but never supply missing SQL meaning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyConditions
{
    /**
     * Publishes the first definition condition beside the complete statement and body.
     */
    public function publish(R\CreateProcedure|R\CreateFunction|R\CreateTrigger|R\CreateEvent|R\AlterEvent $statement, Derivation $derivation): void
    {
        $condition = $this->first($statement, $derivation);
        if ($condition !== null) {
            [$problem, $at, $aborts] = $condition;
            $diagnostic = $problem->problem;
            $reported = $diagnostic instanceof R\Problem\ProgramProblem && array_filter($derivation->diagnostics(), static fn ($existing): bool => $existing instanceof R\Problem\ProgramProblem && $existing->rule === $diagnostic->rule && $existing->subject === $diagnostic->subject) !== [];
            if (!$reported) {
                $derivation->report($diagnostic);
            }
            $derivation->warn(new ParseFailure($problem, $aborts), $at);
        }
        $inputs = $statement instanceof R\CreateProcedure || $statement instanceof R\CreateFunction ? [$statement->parameters] : [];
        if ($statement instanceof R\CreateFunction) {
            $inputs[] = $statement->returns;
        }
        if ($statement instanceof R\CreateProcedure || $statement instanceof R\CreateFunction) {
            array_push($inputs, ...$statement->characteristics);
        }
        if ($statement->body !== null) {
            $inputs[] = $statement->body;
        }
        $ordered = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->inputs($inputs, childrenFirst: true);
        $derivation->orderWarnings($statement, [...$ordered, $statement]);
    }

    /**
     * Orders declaration-input conditions before completed-body restrictions.
     *
     * @return array{R\Problem\RefusedProgramBody, Node, bool}|null The condition, semantic boundary and whether reading stops there
     */
    public function first(R\CreateProcedure|R\CreateFunction|R\CreateTrigger|R\CreateEvent|R\AlterEvent $statement, Derivation $derivation): ?array
    {
        if ($statement->body === null) {
            return null;
        }
        $kind = match (true) {
            $statement instanceof R\CreateProcedure => R\ProgramKind::Procedure,
            $statement instanceof R\CreateFunction => R\ProgramKind::Function,
            $statement instanceof R\CreateTrigger => R\ProgramKind::Trigger,
            default => R\ProgramKind::Event,
        };
        $parameters = $statement instanceof R\CreateProcedure || $statement instanceof R\CreateFunction ? $statement->parameters : null;
        $returns = $statement instanceof R\CreateFunction ? $statement->returns : null;
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Typing\Declaration\FixedSizeBounds())->declaration($statement->body, $parameters, $returns);
        $at = $statement;
        $aborts = false;
        $locals = array_map(static fn (R\Parameter $parameter): \SqlSemantics\Statement\Identifier\Name => $parameter->name, $parameters->parameters ?? []);
        $input = $problem === null ? (new BodyInputs())->first($statement->body, $locals, $derivation->sources) : null;
        if ($input !== null) {
            [$problem, $at] = $input;
            $aborts = true;
        }
        $problem ??= $this->completed($statement, $kind, $parameters);
        if ($problem === null) {
            return null;
        }
        return [new R\Problem\RefusedProgramBody($statement->name, $kind, $problem, $statement->definer, $parameters, $returns, $statement instanceof R\CreateFunction ? $statement->collation : null, $statement->definitionSource->typeEnds ?? [], $statement->definitionSource->nameEnd ?? 0), $at, $aborts];
    }

    /**
     * Checks complete-body restrictions independently of runtime state and SQL text.
     */
    public function completed(R\CreateProcedure|R\CreateFunction|R\CreateTrigger|R\CreateEvent|R\AlterEvent $statement, R\ProgramKind $kind, ?R\ParameterList $parameters): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse|R\Problem\ProgramProblem|R\Problem\LanguageBodyMismatch|R\Problem\ParameterScopeMismatch|\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount|\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument|\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction|null
    {
        $body = $statement->body;
        if ($body === null) {
            return null;
        }
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\Recursion\Constraints())->position($body) ?? (new BodyStructure())->problem($body, $kind, $statement->name, $parameters);
        $characteristics = $statement instanceof R\CreateProcedure || $statement instanceof R\CreateFunction ? $statement->characteristics : [];
        if (!(new BodyLanguage())->compatible($body, $characteristics) && ($problem === null || ($problem instanceof R\Problem\ProgramProblem && in_array($problem->rule, [R\Problem\ProgramRule::MissingReturn, R\Problem\ProgramRule::ResultSet, R\Problem\ProgramRule::AutocommitInFunction, R\Problem\ProgramRule::CommitInFunction, R\Problem\ProgramRule::FunctionStatement], true)))) {
            $problem = new R\Problem\LanguageBodyMismatch($statement->definitionSource?->bodyRefusalOffset);
        }
        return $problem ?? (new BodyBindings())->problem($body, $kind) ?? (new BodyArrays())->problem($body) ?? (new \SqlSemantics\Platform\MySql\Rules\Call\Structure\CallRoles())->problem($body);
    }
}
