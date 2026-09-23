<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A remote relation selector passed to the foreign-data wrapper, without local name resolution.
 * @visibility public
 * @example Identifying a remote relation without loading it
 *     $relation = new \SqlSemantics\Model\Definition\Foreign\ForeignRelation(new \SqlSemantics\Model\Relation\QualifiedName(['app', 'users']), false);
 *     $relation->name->parts // => ['app', 'users']
 */
final class ForeignRelation
{
    /**
     * Retains optional qualification and the requested descendant scope.
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $name, public readonly bool $includeDescendants = true)
    {
        if (count($name->parts) > 3 || in_array('', $name->parts, true)) {
            throw new InvalidStructure('A remote relation requires one to three nonempty identifier components.');
        }
    }
}
