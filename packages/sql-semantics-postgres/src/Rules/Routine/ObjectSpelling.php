<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Rendering\Output;

/**
 * Writes the keywords of an object kind in a generic object command.
 *
 * Rule: PG-OBJECT-SPELLING-001. Each kind is written with its keywords; a
 * domain constraint is written `CONSTRAINT` and its object adds `ON DOMAIN`.
 * The optional PROCEDURAL before LANGUAGE is a noise word and not written.
 * Source: https://www.postgresql.org/docs/17/sql-comment.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ObjectSpelling
{
    /**
     * Writes the keywords of the kind.
     */
    public function kind(Output $out, ObjectKind $kind): void
    {
        $out->keyword(...($kind === ObjectKind::DomainConstraint ? ['CONSTRAINT'] : $kind->keywords()));
    }
}
