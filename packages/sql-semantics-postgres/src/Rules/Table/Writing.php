<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Writes the pieces the definitions of tables, indexes and their constraints share.
 *
 * Rule: PG-TABLE-WRITING-001. A name list is written in order, separated by
 * commas; a parenthesized list adds the parentheses the grammar requires; a
 * constraint name is written after CONSTRAINT; a storage parameter list is
 * written as `( name = value, ... )`. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Writing
{
    /**
     * Writes names separated by commas.
     *
     * @param list<Name> $names
     */
    public function names(Output $out, array $names, NameUse $use = NameUse::Column): void
    {
        foreach ($names as $position => $name) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($name, $use);
        }
    }

    /**
     * Writes names separated by commas between parentheses.
     *
     * @param list<Name> $names
     */
    public function parenthesized(Output $out, array $names): void
    {
        $out->symbol('(');
        $this->names($out, $names);
        $out->symbol(')');
    }

    /**
     * Writes CONSTRAINT and the name of a named constraint; nothing for an unnamed one.
     */
    public function constraintName(Output $out, ?Name $name): void
    {
        if ($name !== null) {
            $out->keyword('CONSTRAINT')->name($name);
        }
    }

    /**
     * Writes storage parameters or definitions between parentheses; nothing when there are none.
     *
     * @param list<Definition> $definitions
     */
    public function definitions(Output $out, array $definitions): void
    {
        if ($definitions !== []) {
            $out->symbol('(')->list($definitions)->symbol(')');
        }
    }

    /**
     * Writes nodes one after another, without separators.
     *
     * @param list<Node> $nodes
     */
    public function sequence(Output $out, array $nodes): void
    {
        foreach ($nodes as $node) {
            $out->node($node);
        }
    }

    /**
     * Writes NULLS DISTINCT, NULLS NOT DISTINCT or nothing.
     */
    public function nullTreatment(Output $out, ?bool $distinct): void
    {
        if ($distinct !== null) {
            $out->keyword(...($distinct ? ['NULLS', 'DISTINCT'] : ['NULLS', 'NOT', 'DISTINCT']));
        }
    }

    /**
     * Writes the index parameters of a key constraint: `WITH ( ... )` and `USING INDEX TABLESPACE name`, each when given.
     *
     * @param list<Definition> $options
     */
    public function indexParameters(Output $out, array $options, ?Name $tablespace): void
    {
        if ($options !== []) {
            $out->keyword('WITH');
            $this->definitions($out, $options);
        }
        if ($tablespace !== null) {
            $out->keyword('USING', 'INDEX', 'TABLESPACE')->name($tablespace);
        }
    }

    /**
     * Writes WITH DATA, WITH NO DATA or nothing.
     */
    public function withData(Output $out, ?bool $withData): void
    {
        if ($withData !== null) {
            $out->keyword(...($withData ? ['WITH', 'DATA'] : ['WITH', 'NO', 'DATA']));
        }
    }

    /**
     * Writes nodes separated by a keyword, such as OR or AND.
     *
     * @param list<Node> $nodes
     */
    public function separated(Output $out, array $nodes, string $keyword): void
    {
        foreach ($nodes as $position => $node) {
            if ($position > 0) {
                $out->keyword($keyword);
            }
            $out->node($node);
        }
    }
}
