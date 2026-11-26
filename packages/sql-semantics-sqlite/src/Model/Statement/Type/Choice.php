<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Type;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A type that is one of several known types, decided by values at run time.
 *
 * @visibility SqlSemantics
 */
final class Choice implements TypeFact
{
    use Snapshot;

    /**
     * @var list<TypeDescriptor> The possible types, without duplicates, in rule order
     */
    public readonly array $alternatives;

    /**
     * @param list<TypeDescriptor> $alternatives At least two possible types
     */
    public function __construct(array $alternatives)
    {
        $this->alternatives = Check::listOf($alternatives, TypeDescriptor::class, 'A type choice holds at least two type descriptors.', 2);
    }
}
