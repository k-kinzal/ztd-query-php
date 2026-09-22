<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

use SqlSemantics\Model\Expression;

/**
 * An SQL/JSON input expression and its optional declared document format.
 * @visibility public
 */
final class Input
{
    /**
     * Describes how the consumer should interpret this expression as a JSON value.
     */
    public function __construct(public readonly Expression $expression, public readonly ?Format $format = null)
    {
    }
}
