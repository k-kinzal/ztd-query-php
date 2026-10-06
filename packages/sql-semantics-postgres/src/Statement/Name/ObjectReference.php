<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * A reference to a catalog object in a command that addresses objects by kind.
 *
 * DROP, COMMENT, SECURITY LABEL, ALTER ... RENAME/OWNER/SET SCHEMA, GRANT and
 * ALTER EXTENSION name their objects with a dotted name, a routine signature,
 * an operator signature or a more specific form. The object kind is held next
 * to the reference by the command, as PostgreSQL's parse nodes do.
 *
 * @visibility public
 * @example Telling that a dotted name refers to an object
 *     $name = new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('t')]);
 *     $name instanceof \SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference // => true
 */
interface ObjectReference extends Clause
{
}
