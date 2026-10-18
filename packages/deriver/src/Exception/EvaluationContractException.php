<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * A registered evaluator violates the contract required for sound analysis.
 *
 * @visibility public
 * @example Inspecting a evaluator contract failure
 *     (new \Deriver\Exception\EvaluationContractException('Invalid domain join'))->getMessage() // => 'Invalid domain join'
 */
final class EvaluationContractException extends RuntimeException
{
}
