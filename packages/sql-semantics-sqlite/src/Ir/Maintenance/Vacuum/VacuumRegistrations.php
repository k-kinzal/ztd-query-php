<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum;

use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Native parameter registrations for an ignored temporary VACUUM filename.
 *
 * @visibility public
 * @example Preserving the numbered label without evaluating it
 *     $parameter = new \SqlSemantics\Platform\Sqlite\Ir\Parameter('002');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumRegistrations([$parameter]))->toString() // => '(0 IN (?002))'
 */
final class VacuumRegistrations
{
    use Snapshot;

    /**
     * The owned registration occurrences in their original order.
     * @var non-empty-list<Parameter>
     */
    public readonly array $bindings;

    /**
     * Copies the checked and bounded registration list.
     * @param list<Parameter> $bindings
     * @throws InvalidConstruction When the registration list is empty, untyped or outside the fixed budget
     */
    public function __construct(array $bindings)
    {
        Check::input(count($bindings) <= 8192, 'VACUUM registration occurrences exceed the scalar budget.');
        $this->bindings = Check::listOf($bindings, Parameter::class, 'VACUUM requires a nonempty list of numbered registrations.', 1);
        $bytes = 0;
        foreach ($this->bindings as $binding) {
            $bytes += strlen($binding->label);
        }
        Check::input($bytes <= 990000000, 'VACUUM registrations exceed the SQL payload budget.');
    }

    /**
     * Emits an inert expression whose markers are still registered by SQLite.
     */
    public function toString(): string
    {
        $markers = [];
        foreach ($this->bindings as $binding) {
            $markers[] = '?' . $binding->label;
        }

        return '(0 IN (' . implode(', ', $markers) . '))';
    }
}
