<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * A star projection that cannot be expanded because member lists are missing from the context.
 *
 * It stands for an undetermined number of output positions. No placeholder
 * fields are invented for them.
 *
 * @visibility public
 * @example Keeping an unexpandable star as a typed request
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT * FROM t');
 *     $operation->facts->output?->projection[0] instanceof \SqlSemantics\Statement\Shape\OpenStar // => true
 */
final class OpenStar
{
    use Snapshot;

    /**
     * @var non-empty-list<MissingInput> The member lists needed to expand the star
     */
    public readonly array $missing;

    /**
     * @param list<MissingInput> $missing The member lists needed to expand the star; at least one
     */
    public function __construct(array $missing)
    {
        $this->missing = Check::listOf($missing, MissingInput::class, 'An open star names at least one missing input.', 1);
    }
}
