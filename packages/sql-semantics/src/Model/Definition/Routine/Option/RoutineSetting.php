<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;

/**
 * Sets a configuration parameter while the routine runs; FROM CURRENT captures the value at definition time.
 * @visibility public
 * @example Reading the assigned parameter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() SET search_path TO app, public');
 *     $statement->changes[0]->setting->name // => ['search_path']
 */
final class RoutineSetting implements RoutineOption
{
    /**
     * Retains the stored assignment.
     */
    public function __construct(public readonly AssignedSetting|DefaultSetting|CurrentSetting $setting)
    {
    }
}
