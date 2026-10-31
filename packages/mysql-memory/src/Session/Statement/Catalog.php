<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Statement;

use MySqlMemory\Dictionary\Binding\Declarations;
use MySqlMemory\Dictionary\Binding\ViewBinding;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Resolution\Views;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Declaration\Table;

/**
 * Supplies catalog inputs at the session boundary, before a plan consumes them.
 * Planning receives immutable bindings and cannot invoke the resolver itself.
 *
 * @visibility MySqlMemory
 */
final class Catalog
{
    /**
     * Captures permanent view bindings under the caller's interpretation settings.
     */
    public static function snapshot(Session $session): Declarations
    {
        return Views::capture($session->instance->dictionary, $session->settings());
    }

    /**
     * Supplies the outer statement's declarations with its explicit temporary namespace.
     *
     * @return list<Table>
     */
    public static function declarations(Session $session): array
    {
        return self::snapshot($session)->tables($session->temporaries);
    }

    /**
     * Checks a view before a command consumes its bound columns.
     *
     * @throws SqlError When the view or a persistent dependency no longer resolves
     */
    public static function view(View $view, Session $session): ViewBinding
    {
        $binding = self::snapshot($session)->view($view);
        if (!$binding->valid) {
            throw SchemaError::ViewInvalid->error($view->schema, $view->name);
        }
        return $binding;
    }
}
