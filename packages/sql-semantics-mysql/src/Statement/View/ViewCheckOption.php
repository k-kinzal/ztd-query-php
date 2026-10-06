<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

/**
 * The WITH CHECK OPTION clause of an updatable view.
 *
 * Without CASCADED or LOCAL the check is CASCADED; the written form is kept.
 * Each case holds the keyword written between WITH and CHECK, if any.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/view-check-option.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption::Local->value // => 'LOCAL'
 */
enum ViewCheckOption: string
{
    case Unqualified = '';
    case Cascaded = 'CASCADED';
    case Local = 'LOCAL';

    /**
     * Tells whether the check also applies the conditions of the views the view is defined on.
     */
    public function cascades(): bool
    {
        return $this !== self::Local;
    }
}
