<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

use SqlSemantics\Model\Expression;

/**
 * A default expression evaluated by the consumer when a JSON path cannot supply a value.
 * @visibility public
 */
final class DefaultResponse implements ValueResponse
{
    /**
     * Keeps the required default expression without evaluating it.
     */
    public function __construct(public readonly Expression $expression)
    {
    }
}
