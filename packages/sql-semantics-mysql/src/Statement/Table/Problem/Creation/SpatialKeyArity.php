<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation;

use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A retained spatial index has more than its single permitted key part.
 *
 * The first part identifies its owning declaration without retaining syntax.
 * Observed through SQL on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Reading the resolved key condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity(new \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart(new \SqlSemantics\Statement\Identifier\Name('a'))))->message() // => 'A spatial index requires exactly one key part.'
 */
final class SpatialKeyArity implements Diagnostic
{
    use Snapshot;

    /**
     * @param KeyPart $owner The first key part of the complete retained index
     */
    public function __construct(public readonly KeyPart $owner)
    {
    }

    /**
     * Describes the index's fixed arity independently of the destination engine.
     */
    public function message(): string
    {
        return 'A spatial index requires exactly one key part.';
    }
}
