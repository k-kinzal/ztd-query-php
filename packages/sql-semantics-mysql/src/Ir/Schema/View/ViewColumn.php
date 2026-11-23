<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One admitted name in a view's public catalog heading.
 *
 * @visibility public
 * @example Recording the public name of the first result position
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\View\ViewColumn(new \SqlSemantics\Platform\MySql\Ir\Identifier('account')))->name->value // => 'account'
 */
final class ViewColumn
{
    use Snapshot;

    /**
     * Checks the stored identifier in its catalog role.
     */
    public function __construct(public readonly Identifier $name)
    {
        Check::input(preg_match('/\A[\x01-\x7f]{1,64}(?<! )\z/D', $name->value) === 1, 'A checked MySQL view field needs 1 to 64 ASCII characters without a trailing space.');
    }
}
