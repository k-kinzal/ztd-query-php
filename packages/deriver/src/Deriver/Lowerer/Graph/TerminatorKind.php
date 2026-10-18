<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

/**
 * Control transfers that end a lowered basic block.
 * @visibility root
 */
enum TerminatorKind: string
{
    case Branch = 'branch';
    case Jump = 'jump';
    case Return = 'return';
    case Throw = 'throw';
    case Exit = 'exit';
    case Residual = 'residual';
    case CompleteJump = 'complete-jump';
    case LeaveTry = 'leave-try';
    case Resume = 'resume';
}
