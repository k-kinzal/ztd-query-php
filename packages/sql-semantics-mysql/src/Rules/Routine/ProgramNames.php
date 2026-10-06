<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Writes the names of stored programs and the labels of their statements.
 *
 * Rule: MYSQL-PROGRAM-NAMES-001. A program name is written with its
 * database qualifier when it has one; a label before a statement is
 * followed by a colon; a data type is followed by its COLLATE clause when
 * it has one. Terminates: constant work.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html,
 * https://dev.mysql.com/doc/refman/8.4/en/statement-labels.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProgramNames
{
    /**
     * Writes a name with its optional database qualifier.
     */
    public function qualified(Output $out, QualifiedName $name, NameUse $use = NameUse::Routine): void
    {
        if ($name->schema !== null) {
            $out->name($name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($name->name, $use);
    }

    /**
     * Writes the label that begins a block or loop, when there is one.
     */
    public function label(Output $out, ?Name $label): void
    {
        if ($label !== null) {
            $out->name($label, NameUse::Label)->glue()->symbol(':');
        }
    }

    /**
     * Writes the label that ends a block or loop, when there is one.
     */
    public function end(Output $out, ?Name $label): void
    {
        if ($label !== null) {
            $out->name($label, NameUse::Label);
        }
    }

    /**
     * Writes a data type and its COLLATE clause, when there is one.
     */
    public function type(Output $out, TypeName $type, ?CollationName $collation): void
    {
        $out->node($type);
        if ($collation !== null) {
            $out->keyword('COLLATE')->node($collation);
        }
    }
}
