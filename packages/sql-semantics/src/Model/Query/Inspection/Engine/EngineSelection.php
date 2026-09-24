<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Engine;

/**
 * Selects every installed storage engine instead of one named engine.
 * @visibility public
 * @example Inspecting an engine report over all engines
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW ENGINE ALL STATUS');
 *     $statement->engine === \SqlSemantics\Model\Query\Inspection\Engine\EngineSelection::All // => true
 */
enum EngineSelection: string
{
    case All = 'ALL';
}
