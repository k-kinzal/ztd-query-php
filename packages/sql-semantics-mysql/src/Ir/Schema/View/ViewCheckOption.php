<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

/**
 * The resolved rule for validating a new row written through an updatable view.
 *
 * @visibility public
 * @example Reading the native catalog policy
 *     \SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCheckOption::Cascaded->value // => 'CASCADED'
 */
enum ViewCheckOption: string
{
    case None = 'NONE';
    case Local = 'LOCAL';
    case Cascaded = 'CASCADED';
}
