<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A classified SQL/JSON wrapper policy.
 * @visibility public
 */
enum ArrayWrapping: string
{
    case Default = '';
    case Without = 'WITHOUT WRAPPER';
    case Conditional = 'WITH CONDITIONAL WRAPPER';
    case Unconditional = 'WITH UNCONDITIONAL WRAPPER';
}
