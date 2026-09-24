<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

use SqlSemantics\Model\Configuration\ResetSetting;

/**
 * Removes a stored configuration parameter of the routine; a null setting is RESET ALL.
 * @visibility public
 * @example Reading a full reset
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURE p() RESET ALL');
 *     $statement->changes[0]->setting // => null
 */
final class RoutineReset implements RoutineOption
{
    /**
     * Retains the removed parameter, or null for every parameter.
     */
    public function __construct(public readonly ?ResetSetting $setting)
    {
    }
}
