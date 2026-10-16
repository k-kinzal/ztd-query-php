<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored GRANT whose target condition is raised only when reached.
 *
 * Native SQL accepts the definition without resolving the target or accounts.
 * Column privileges outside an object level use this form only on MySQL 8+;
 * legacy SQL refuses them even in unreachable program instructions.
 *
 * @visibility public
 * @example Keeping a stored condition outside the standalone statement domain
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u')->statement;
 *     [$create->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel, $create->body instanceof \SqlSemantics\Platform\MySql\Statement\Statement] // => [true, false]
 */
final class DeferredGrantLevel implements ProgramStatement
{
    use Snapshot;

    /**
     * @param GrantRequest $request The instruction's typed privilege and account operands
     */
    public function __construct(public readonly GrantRequest $request)
    {
        Check::input($request->as === null || $request->level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel, 'Non-global AS takes precedence and uses DeferredGrantAs.');
        Check::input($request->scopeProblem() !== null, 'A deferred grant level needs a contradictory privilege target.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Defines no names and performs no target binding before invocation.
     *
     * The target condition precedes table, routine and account lookup; binding
     * those names here would incorrectly reject accepted program definitions.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
    }

    /**
     * Answers the deferred condition with its earlier-resolution operands.
     */
    public function problem(): RefusedGrantLevel
    {
        return new RefusedGrantLevel($this->request);
    }

    /**
     * Writes every retained operand of the instruction.
     */
    public function render(Output $out): void
    {
        $out->node($this->request);
    }
}
