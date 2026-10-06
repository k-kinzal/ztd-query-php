<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The name of an object that belongs to no schema, such as a schema, an extension, a language, a database or a role.
 *
 * The grammar writes these objects with a single `name`; no qualifier can be
 * written.
 * Source: https://www.postgresql.org/docs/17/sql-dropschema.html, https://www.postgresql.org/docs/17/sql-comment.html.
 *
 * @visibility public
 * @example Reading the name
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName(new \SqlSemantics\Statement\Identifier\Name('app')))->name->value // => 'app'
 */
final class UnqualifiedName implements ObjectReference
{
    use Snapshot;

    /**
     * @param Name $name The object name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives nothing: a name holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column);
    }
}
