<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A declaration whose instruction arrangement contradicts its program kind.
 *
 * The header supplies database and definer precedence without retaining the
 * input SQL or a parser tree. Complete declarations retain their body beside
 * this diagnostic; incomplete inputs can report it without a declaration.
 *
 * @visibility public
 * @example Describing a body restriction
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f')), \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function, new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::CommitInFunction)))->message() // => 'Explicit or implicit commit is not allowed in stored function or trigger.'
 */
final class RefusedProgramBody implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<int> Numeric completion positions corresponding to the typed declarations
     */
    public readonly array $typeEnds;

    /**
     * @param QualifiedName $name The declaration's name and optional database
     * @param ProgramKind $kind The enclosing declaration kind
     * @param \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse|ProgramProblem|LanguageBodyMismatch|ScopedLocalAssignment|ParameterScopeMismatch|EmptyEventAlteration|\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount|\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument|\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction|\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize|\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable $problem The intrinsic instruction, language, binding or return constraint
     * @param Account|null $definer The preceding definer operand
     * @param ParameterList|null $parameters The routine parameters, absent for events and triggers
     * @param DeclaredName|null $returns The stored function return type
     * @param CollationName|null $collation The return type collation
     * @param list<int> $typeEnds Completion offsets of parameters followed by the return type, used only to order input notices
     * @param int $nameEnd Completion offset of the declared name, before its field types
     */
    public function __construct(public readonly QualifiedName $name, public readonly ProgramKind $kind, public readonly \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse|ProgramProblem|LanguageBodyMismatch|ScopedLocalAssignment|ParameterScopeMismatch|EmptyEventAlteration|\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount|\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument|\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction|\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize|\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable $problem, public readonly ?Account $definer = null, public readonly ?ParameterList $parameters = null, public readonly ?DeclaredName $returns = null, public readonly ?CollationName $collation = null, array $typeEnds = [], public readonly int $nameEnd = 0)
    {
        \SqlSemantics\Diagnostic\Check::input(!$problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse || $problem->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::RecursiveReferencePosition, 'A recursive program refusal identifies a lexical reference constraint.');
        \SqlSemantics\Diagnostic\Check::input($nameEnd >= 0, 'The input name position is nonnegative.');
        $this->typeEnds = (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\InputPositions())->checked($typeEnds);
        \SqlSemantics\Diagnostic\Check::input($typeEnds === [] || count($typeEnds) === count($parameters->parameters ?? []) + ($returns === null ? 0 : 1), 'Type positions correspond to parameter and return declarations.');
        \SqlSemantics\Diagnostic\Check::input($returns === null || $kind === ProgramKind::Function, 'Only a function has a return type.');
        \SqlSemantics\Diagnostic\Check::input($parameters === null || $kind === ProgramKind::Function || $kind === ProgramKind::Procedure, 'Only a routine has parameter declarations.');
    }

    /**
     * Describes the intrinsic body constraint.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
