<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * ViewCheck alternatives.
 *
 * @visibility public
 */
enum ViewCheck: string
{
    case None = '';
    case Local = 'LOCAL';
    case Cascaded = 'CASCADED';
}
