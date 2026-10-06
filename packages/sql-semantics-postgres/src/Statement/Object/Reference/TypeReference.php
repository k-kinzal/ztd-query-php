<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A type or domain named as an object with a full type name, as DROP TYPE, DROP DOMAIN and COMMENT ON TYPE write it.
 *
 * The type name may be written in any form a column type can take; the
 * object is the type it denotes, so modifiers and array bounds name the same
 * object as the bare name.
 * Source: https://www.postgresql.org/docs/17/sql-droptype.html.
 *
 * @visibility public
 * @example Reading the type name
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('mood')])));
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TypeReference($type))->type === $type // => true
 */
final class TypeReference implements ObjectReference
{
    use Snapshot;

    /**
     * @param TypeName $type The type name
     */
    public function __construct(public readonly TypeName $type)
    {
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes the type name.
     */
    public function render(Output $out): void
    {
        $out->node($this->type);
    }
}
