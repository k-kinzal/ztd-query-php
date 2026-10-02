<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A type that is one of several known types, decided by values at run time.
 *
 * @visibility public
 * @example Holding two possible types
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $type = $semantics->analyze("SELECT CASE WHEN 1 THEN 1 ELSE 'a' END")->field(0)->type;
 *     $type instanceof \SqlSemantics\Statement\Type\Choice // => true
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
