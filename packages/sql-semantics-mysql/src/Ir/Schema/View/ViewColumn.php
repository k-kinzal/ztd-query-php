<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;
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
    public function __construct(public readonly Identifier|RequestedResultAlias $name)
    {
        Check::input(strlen($name->value) <= 256, 'A checked MySQL view field needs at most 256 bytes.');
        $native = new NativeLocalNames();
        Check::input(
            !$name instanceof RequestedResultAlias || $native->readable($name->requested),
            'A checked MySQL view heading must persist valid UTF-8.'
        );
        Check::input(
            $name instanceof RequestedResultAlias || $native->prefix($name->value) === $name->value
                || (new ViewProjectionAliases())->valid($name->value),
            'A checked MySQL view field needs an admitted legacy or modern heading.'
        );
    }
}
