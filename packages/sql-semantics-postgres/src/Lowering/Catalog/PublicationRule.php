<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\AlterPublicationMembers;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\AlterPublicationOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\CreatePublication;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationAction;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationMember;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationTable;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the publication commands.
 *
 * Rule: PG-PUBLICATION-LOWER-001. Scope: `CreatePublicationStmt`,
 * `PublicationObjSpec`, `pub_obj_list`, `AlterPublicationStmt`.
 * Constructors: `CreatePublication`, `AlterPublicationOptions`,
 * `AlterPublicationMembers`, `PublicationTable`, `PublicationSchema`. An item
 * without keywords is read with the kind of the item before it, as
 * PG-PUBLICATION-LIST-001 states: a bare name after a schema is a schema,
 * otherwise a table. A table name with an indirection takes field selections
 * only, at most three parts. Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createpublication.html, https://www.postgresql.org/docs/17/sql-alterpublication.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class PublicationRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a publication command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $name = $this->lowering->names->name($form->node(2));
        $options = $this->lowering->options;

        return match ($form->signature) {
            'CreatePublicationStmt: CREATE PUBLICATION name opt_definition' => new CreatePublication($name, false, [], $options->definitions($form->node(3))),
            'CreatePublicationStmt: CREATE PUBLICATION name FOR ALL TABLES opt_definition' => new CreatePublication($name, true, [], $options->definitions($form->node(6))),
            'CreatePublicationStmt: CREATE PUBLICATION name FOR pub_obj_list opt_definition' => new CreatePublication($name, false, $this->objects($form->node(4)), $options->definitions($form->node(5))),
            'AlterPublicationStmt: ALTER PUBLICATION name SET definition' => new AlterPublicationOptions($name, $options->definitions($form->node(4))),
            'AlterPublicationStmt: ALTER PUBLICATION name ADD_P pub_obj_list' => new AlterPublicationMembers($name, PublicationAction::Add, $this->objects($form->node(4))),
            'AlterPublicationStmt: ALTER PUBLICATION name SET pub_obj_list' => new AlterPublicationMembers($name, PublicationAction::Set, $this->objects($form->node(4))),
            'AlterPublicationStmt: ALTER PUBLICATION name DROP pub_obj_list' => new AlterPublicationMembers($name, PublicationAction::Drop, $this->objects($form->node(4))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `pub_obj_list`, reading each item without keywords with the kind of the item before it.
     *
     * @return list<PublicationMember>
     */
    public function objects(Node $list): array
    {
        $objects = [];
        $previous = null;
        foreach ($this->lowering->items($list, 'pub_obj_list: PublicationObjSpec', 'pub_obj_list: pub_obj_list , PublicationObjSpec') as $item) {
            $previous = $this->object($this->lowering->productions->form($item), $previous);
            $objects[] = $previous;
        }

        return $objects;
    }

    /**
     * Lowers one `PublicationObjSpec` after the item before it.
     *
     * @throws AnalysisException When a table name has a subscript or more than three parts, which `makeRangeVarFromQualifiedName` called by the action of `PublicationObjSpec` in `gram.y` rejects
     * @throws ImplementationGap When the production has no rule
     */
    public function object(Form $form, ?PublicationMember $previous): PublicationMember
    {
        $names = $this->lowering->names;
        $tables = $this->lowering->tables;

        return match ($form->signature) {
            'PublicationObjSpec: TABLE relation_expr opt_column_list OptWhereClause' => new PublicationTable($this->lowering->queries->relation($form->node(1)), $names->names($form->node(2)), $tables->predicate($form->node(3)), true, $this->star($form->node(1))),
            'PublicationObjSpec: TABLES IN_P SCHEMA ColId' => new PublicationSchema($names->name($form->node(3))),
            'PublicationObjSpec: TABLES IN_P SCHEMA CURRENT_SCHEMA' => new PublicationSchema(null),
            'PublicationObjSpec: ColId opt_column_list OptWhereClause' => $this->continued($form, $previous),
            'PublicationObjSpec: ColId indirection opt_column_list OptWhereClause' => new PublicationTable(
                new RelationReference((new DottedName([$names->name($form->node(0)), ...$names->fields($form->node(1))]))->qualified() ?? throw new AnalysisException('improper qualified name (too many dotted names)')),
                $names->names($form->node(2)),
                $tables->predicate($form->node(3)),
                false,
            ),
            'PublicationObjSpec: extended_relation_expr opt_column_list OptWhereClause' => new PublicationTable($this->lowering->queries->relation($form->node(0)), $names->names($form->node(1)), $tables->predicate($form->node(2)), false, $this->star($form->node(0))),
            'PublicationObjSpec: CURRENT_SCHEMA' => new PublicationSchema(null, false),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a bare name item: a schema after a schema item when it has no column list or row filter, otherwise a table.
     */
    public function continued(Form $form, ?PublicationMember $previous): PublicationMember
    {
        $name = $this->lowering->names->name($form->node(0));
        $columns = $this->lowering->names->names($form->node(1));
        $where = $this->lowering->tables->predicate($form->node(2));
        if ($previous instanceof PublicationSchema && $columns === [] && $where === null) {
            return new PublicationSchema($name, false);
        }

        return new PublicationTable(new RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName($name)), $columns, $where, false);
    }

    /**
     * Tells whether a `relation_expr` or `extended_relation_expr` ends with the star that names the descendants explicitly.
     */
    public function star(Node $relation): bool
    {
        $current = $relation;
        while ($current->name === 'relation_expr' && count($current->children) === 1 && $current->children[0] instanceof Node) {
            $current = $current->children[0];
        }
        $last = $current->children[count($current->children) - 1] ?? null;

        return $current->name === 'extended_relation_expr' && $last instanceof Token && $last->text === '*';
    }
}
