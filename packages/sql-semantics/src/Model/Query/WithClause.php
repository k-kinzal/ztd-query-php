<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Ordered definitions owned by this statement, distinct from inherited lexical visibility. @visibility public

 */
final class WithClause
{
    /**
     * @var non-empty-list<CommonTableExpression> Validated ordered operands
     */
    public readonly array $definitions;

    /**
     * @param list<CommonTableExpression> $definitions
     * @throws InvalidStructure
     */
    public function __construct(array $definitions, public readonly bool $recursive = false)
    {
        Collections::objects($definitions, CommonTableExpression::class);
        if ($definitions === []) {
            throw new InvalidStructure('WITH requires at least one named definition.');
        }
        $first = Collections::nonEmpty($definitions)[0];
        $identifiers = new \SqlSemantics\Ast\Identifiers($first->query->origin->dialect);
        $names = [];
        foreach ($definitions as $definition) {
            if ($definition->query->origin->dialect !== $first->query->origin->dialect) {
                throw new InvalidStructure('WITH definitions must use the same SQL dialect.');
            }
            foreach ($names as $name) {
                if ($identifiers->equal($name, $definition->name)) {
                    throw new InvalidStructure('A WITH clause cannot define the same relation name twice.');
                }
            }
            $names[] = $definition->name;
        }
        $this->definitions = Collections::nonEmpty($definitions);
    }
}
