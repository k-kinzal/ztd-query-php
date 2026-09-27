<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * A registered model violates the contract required for sound analysis.
 *
 * @visibility public
 * @example Inspecting a model contract failure
 *     (new \Deriver\Exception\ModelContractException('Invalid domain join'))->getMessage() // => 'Invalid domain join'
 */
final class ModelContractException extends RuntimeException
{
}
