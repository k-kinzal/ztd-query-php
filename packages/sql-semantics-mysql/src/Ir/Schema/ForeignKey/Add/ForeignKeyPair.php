<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Corresponding child and parent columns with one native integer comparison domain.
 * @visibility public
 * @example Inspecting both roles of a resolved pair
 *     $child = new \SqlSemantics\Platform\MySql\Ir\Identifier('owner_id');
 *     $parent = new \SqlSemantics\Platform\MySql\Ir\Identifier('id');
 *     $pair = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair($child, $parent, \SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Standard, false, true);
 *     [$pair->child->value, $pair->parent->value, $pair->nullable] // => ['owner_id', 'id', true]
 */
final class ForeignKeyPair
{
    use Snapshot;

    /**
     * Records a comparison between the checked native integer fields.
     */
    public function __construct(
        public readonly Identifier $child,
        public readonly Identifier $parent,
        public readonly IntegerStorage $storage,
        public readonly bool $unsigned,
        public readonly bool $nullable,
        public readonly bool $descending = false,
    ) {
        Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $child->value) === 1
            && preg_match('/\A.{1,64}(?<! )\z/us', $parent->value) === 1, 'A foreign-key pair requires native column names.');
    }
}
