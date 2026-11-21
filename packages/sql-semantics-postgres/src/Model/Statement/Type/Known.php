<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Type;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A type the rules of the language profile determine exactly.
 *
 * @visibility public
 * @example Reading a known type
 *     $operation = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze("SELECT 'a'");
 *     $operation->field(0)->type instanceof \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known // => true
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
