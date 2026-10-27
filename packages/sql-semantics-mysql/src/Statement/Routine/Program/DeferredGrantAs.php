<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\BodyFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;
use SqlSemantics\Rendering\Output;

/**
 * A program-only GRANT whose non-global AS condition is raised when reached.
 *
 * MySQL accepts the definition, permits an unreachable instruction, and raises
 * error 3835 on invocation before resolving the accounts or target objects.
 * The grant operands and AS clause are retained as values; no input SQL is kept.
 * Observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Refusing to defer a global grant
 *     $grant = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantAs($grant, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Keeping a deferred privilege instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER');
 *     [$operation->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantAs, $operation->toString(), $operation->body->problem()->error] // => [true, 'CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER', 'ER_UNSUPPORTED_USE_OF_GRANT_AS']
 */
final class DeferredGrantAs implements ProgramStatement, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\PrivilegeChange
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param GrantRequest $grant The retained privilege operands, without an AS clause
     * @param GrantAs $as The clause whose level constraint is deferred by the program
     */
    public function __construct(public readonly GrantRequest $grant, public readonly GrantAs $as, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($grant->as === null && !$grant->level instanceof GlobalLevel, 'A deferred GRANT AS has a non-global level and exactly one AS clause.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Preserves the ordinary grant's definition-time binding and program restrictions.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        if ($this->grant->scopeProblem() === null) {
            $grant = new GrantPrivileges($this->grant->privileges, $this->grant->kind, $this->grant->level, $this->grant->grantees, $this->grant->tls, $this->grant->options);
            (new BodyFacts())->statement($grant, $derivation, $scope);
        }
    }

    /**
     * Answers the condition raised before executing this program instruction.
     */
    public function problem(): MisplacedPrivilege
    {
        return new MisplacedPrivilege('AS clause', $this->grant->level->describe(), 'ER_UNSUPPORTED_USE_OF_GRANT_AS');
    }

    /**
     * Writes every retained operand, including the deferred AS clause.
     */
    public function render(Output $out): void
    {
        $out->node($this->grant)->node($this->as);
    }
}
