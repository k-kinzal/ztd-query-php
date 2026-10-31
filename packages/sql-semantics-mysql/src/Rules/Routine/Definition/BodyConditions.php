<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyArrays;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyBindings;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure;
use SqlSemantics\Platform\MySql\Statement\Routine as R;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Source\SourceMap;

/**
 * Finds the definition condition that makes a stored program body unstorable.
 *
 * @phpstan-type Problem R\Problem\ProgramProblem|\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse|R\Problem\LanguageBodyMismatch|R\Problem\ScopedLocalAssignment|R\Problem\ParameterScopeMismatch|\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount|\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument|\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction
 *
 * The same typed operands are checked for parsed and directly constructed
 * declarations: a definition statement refuses construction with such a body,
 * and the lowering refuses the input before constructing it. Input sources
 * order the condition among other input notices but never supply SQL meaning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyConditions
{
    /**
     * Answers the first definition condition, the instruction it is found at, and whether reading stops there.
     *
     * Declaration-input conditions precede completed-body restrictions. The server finds
     * most conditions while parsing the instruction; the body-wide restrictions of
     * functions and triggers and a missing RETURN are found once the whole body is read.
     *
     * @param list<R\Characteristic\Characteristic> $characteristics The routine characteristics in written order
     * @param int|null $bodyRefusalOffset The input position of a language/body mismatch, when known
     * @return array{Problem, Node, bool}|null
     */
    public function refusal(Node $body, R\ProgramKind $kind, QualifiedName $name, ?R\ParameterList $parameters, array $characteristics = [], ?int $bodyRefusalOffset = null, ?SourceMap $sources = null): ?array
    {
        $locals = array_map(static fn (R\Parameter $parameter): \SqlSemantics\Statement\Identifier\Name => $parameter->name, $parameters->parameters ?? []);
        $input = (new BodyInputs())->first($body, $locals, $sources);
        if ($input !== null) {
            return [$input[0], $input[1], true];
        }
        $completed = $this->completed($body, $kind, $name, $parameters, $characteristics, $bodyRefusalOffset);
        if ($completed === null) {
            return null;
        }
        return [$completed[0], $completed[1], $this->aborts($completed[0])];
    }

    /**
     * Checks complete-body restrictions independently of runtime state and SQL text.
     *
     * @param list<R\Characteristic\Characteristic> $characteristics The routine characteristics in written order
     * @return array{Problem, Node}|null The condition and the instruction it is found at
     */
    public function completed(Node $body, R\ProgramKind $kind, QualifiedName $name, ?R\ParameterList $parameters, array $characteristics = [], ?int $bodyRefusalOffset = null): ?array
    {
        $position = (new \SqlSemantics\Platform\MySql\Rules\Query\Recursion\Constraints())->position($body);
        $located = $position === null ? (new BodyStructure())->located($body, $kind, $name, $parameters) : [$position, $body];
        $problem = $located[0] ?? null;
        if (!(new BodyLanguage())->compatible($body, $characteristics) && ($problem === null || !$this->aborts($problem))) {
            return [new R\Problem\LanguageBodyMismatch($bodyRefusalOffset), $body];
        }
        if ($located !== null) {
            return $located;
        }
        $problem = (new BodyBindings())->problem($body, $kind) ?? (new BodyArrays())->problem($body) ?? (new \SqlSemantics\Platform\MySql\Rules\Call\Structure\CallRoles())->problem($body);
        return $problem === null ? null : [$problem, $body];
    }

    /**
     * Tells whether the server stops reading at the condition: every condition but the
     * body-wide restrictions of functions and triggers and a missing RETURN.
     */
    public function aborts(Diagnostic $problem): bool
    {
        return !($problem instanceof R\Problem\ProgramProblem && in_array($problem->rule, [R\Problem\ProgramRule::MissingReturn, R\Problem\ProgramRule::ResultSet, R\Problem\ProgramRule::AutocommitInFunction, R\Problem\ProgramRule::CommitInFunction, R\Problem\ProgramRule::FunctionStatement], true));
    }

    /**
     * Orders the derived notices of a definition by the input order of its declarations and body.
     */
    public function order(R\CreateProcedure|R\CreateFunction|R\CreateTrigger|R\CreateEvent|R\AlterEvent $statement, Derivation $derivation): void
    {
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
}
