<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

/**
 * The native write visibility policy of a PostgreSQL view.
 *
 * @visibility public
 * @example Reading the native LOCAL policy
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewCheckOption::Local->value // => 'LOCAL'
 */
enum ViewCheckOption: string
{
    case None = '';
    case Local = 'LOCAL';
    case Cascaded = 'CASCADED';
}
