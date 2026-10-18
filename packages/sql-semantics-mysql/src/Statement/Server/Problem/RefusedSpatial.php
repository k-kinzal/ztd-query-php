<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A completed spatial declaration outside the ordinary statement domain.
 *
 * The typed operation keeps range-error labels without retaining SQL or a parser
 * node. These constraints also reject unreachable stored-program instructions.
 *
 * @visibility public
 * @example Retaining the operation of a refused identifier
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedSpatial(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand::Drop, new \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierZero));
 *     $problem->command->value // => 'DROP'
 */
final class RefusedSpatial implements Diagnostic
{
    use Snapshot;

    /**
     * @param SpatialCommand $command The operation that owns the operand
     * @param SpatialProblem $problem The intrinsic declaration contradiction
     */
    public function __construct(public readonly SpatialCommand $command, public readonly SpatialProblem $problem)
    {
    }

    /**
     * Describes the retained declaration constraint.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
