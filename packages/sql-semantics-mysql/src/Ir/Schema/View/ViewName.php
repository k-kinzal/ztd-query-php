<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The identity of an persistent view in a named persistent schema.
 *
 * @visibility public
 * @example Quoting both parts of a table identity
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\View\ViewName(new \SqlSemantics\Platform\MySql\Ir\Identifier('app'), new \SqlSemantics\Platform\MySql\Ir\Identifier('accounts')))->quoted() // => '`app`.`accounts`'
 */
final class ViewName
{
    use Snapshot;

    /**
     * Fixes the absolute schema and view names.
     */
    public function __construct(public readonly Identifier $schema, public readonly Identifier $view)
    {
        foreach ([$schema, $view] as $identifier) {
            Check::input(
                strpbrk($identifier->value, '/\\.') === false && preg_match('/\A.{1,64}(?<! )\z/us', $identifier->value) === 1,
                'An absolute MySQL view name needs 1 to 64 characters without path separators or a trailing space.',
            );
        }
    }

    /**
     * Emits both identifier positions.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->view->quoted();
    }
}
