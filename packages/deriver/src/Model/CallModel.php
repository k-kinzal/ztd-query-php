<?php

declare(strict_types=1);

namespace Deriver\Model;

use Deriver\Exception\ModelException;

/**
 * Supplies API meaning as a declarative plan; the core binds and evaluates calls.
 *
 * @visibility public
 * @example Describing a model identity
 *     (new \Deriver\Model\ModelDescriptor('keys', '1', 'key'))->id // => 'keys'
 */
interface CallModel
{
    /**
     * Identifies the model and its supported signature.
     * @return ModelDescriptor Stable registration metadata
     * @throws ModelException If trusted plugin code fails
     */
    public function descriptor(): ModelDescriptor;

    /**
     * Selects a plan using call metadata rather than executing application code.
     * @param CallDescription $call Normalized call metadata
     * @return ModelDecision Handled, declined, or explicitly unsupported
     * @throws ModelException If trusted plugin code fails
     */
    public function describe(CallDescription $call): ModelDecision;
}
