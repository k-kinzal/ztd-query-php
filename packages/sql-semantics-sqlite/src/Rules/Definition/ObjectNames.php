<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;

/**
 * Writes the name of a schema object with its optional schema qualifier.
 *
 * Rule: SQLITE-OBJECT-NAME-001. An object name is `schema.name` or `name`;
 * SQLite has no catalog level. Source: https://sqlite.org/lang_naming.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ObjectNames
{
    /**
     * Writes the schema qualifier, when there is one, and the object name.
     */
    public function write(Output $out, QualifiedName $name, NameUse $use = NameUse::Relation): void
    {
        if ($name->schema !== null) {
            $out->name($name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($name->name, $use);
    }
}
