<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Corresponding child and parent columns with one checked native comparison domain.
 * @visibility public
 * @example Inspecting both roles of a resolved pair
 *     $child = new \SqlSemantics\Platform\MySql\Ir\Identifier('owner_id');
 *     $parent = new \SqlSemantics\Platform\MySql\Ir\Identifier('id');
 *     $domain = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain(\SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Standard, false);
 *     $pair = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair($child, $parent, $domain, true);
 *     [$pair->child->value, $pair->parent->value, $pair->nullable] // => ['owner_id', 'id', true]
 */
final class ForeignKeyPair
{
    use Snapshot;

    /**
     * Records the explicit native comparison between the two checked fields.
     */
    public function __construct(
        public readonly Identifier $child,
        public readonly Identifier $parent,
        public readonly ForeignKeyIntegerDomain|ForeignKeyScalarDomain|ForeignKeyStringDomain $domain,
        public readonly bool $nullable,
        public readonly bool $descending = false,
    ) {
        Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $child->value) === 1
            && preg_match('/\A.{1,64}(?<! )\z/us', $parent->value) === 1, 'A foreign-key pair requires native column names.');
    }
}
