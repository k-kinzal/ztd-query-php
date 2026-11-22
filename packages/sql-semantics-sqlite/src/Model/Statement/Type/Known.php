<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Type;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A type the rules of the language profile determine exactly.
 *
 * @visibility SqlSemantics
 */
final class Known implements TypeFact
{
    use Snapshot;

    /**
     * @param TypeDescriptor $descriptor The determined type
     */
    public function __construct(public readonly TypeDescriptor $descriptor)
    {
    }
}
