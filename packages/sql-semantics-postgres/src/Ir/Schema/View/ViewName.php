<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A view identity in an absolute persistent schema.
 *
 * @visibility public
 * @example Quoting both parts of the identity
 *     $schema = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app');
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('My View');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName($schema, $name))->quoted() // => '"app"."My View"'
 */
final class ViewName
{
    use Snapshot;

    /**
     * Fixes the persistent schema and view name.
     */
    public function __construct(public readonly Identifier $schema, public readonly Identifier $view)
    {
        Check::input(
            $schema->value !== 'pg_temp' && preg_match('/\Apg_(?:toast_)?temp_[0-9]+\z/D', $schema->value) === 0,
            'A persistent PostgreSQL view requires an absolute non-temporary schema.',
        );
    }

    /**
     * Writes the qualified view identity.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->view->quoted();
    }
}
