<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\View;

/**
 * Selects whose privileges MySQL checks when the view is used.
 * @visibility public
 * @example Inspecting the security context
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE SQL SECURITY INVOKER VIEW v AS SELECT 1');
 *     $statement->properties->security === \SqlSemantics\Model\Definition\View\ViewSecurity::Invoker // => true
 */
enum ViewSecurity: string
{
    case Definer = 'DEFINER';
    case Invoker = 'INVOKER';
}
