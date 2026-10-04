<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateComposite;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateEnum;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateRange;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\CopyCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Define;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Statement\Statement;

/**
 * Lowers DefineStmt: CREATE AGGREGATE, OPERATOR, TYPE, TEXT SEARCH objects and COLLATION.
 *
 * Rule: PG-DEFINE-LOWER-001. Scope: `DefineStmt`, `old_aggr_definition`,
 * `old_aggr_list`, `old_aggr_elem`. Constructors: `Define`, `CopyCollation`,
 * and the catalog family's `CreateComposite`, `CreateEnum` and `CreateRange` for
 * `CREATE TYPE ... AS ( ... )`, `AS ENUM` and `AS RANGE`, which PostgreSQL
 * represents with their own nodes. Termination: lists are flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-createaggregate.html,
 * https://www.postgresql.org/docs/17/sql-createoperator.html, https://www.postgresql.org/docs/17/sql-createtype.html,
 * https://www.postgresql.org/docs/17/sql-createcollation.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DefineRule
{
    /**
     * The kind of each text search definition production.
     */
    private const SEARCH = [
        'DefineStmt: CREATE TEXT_P SEARCH PARSER any_name definition' => ObjectKind::TextSearchParser,
        'DefineStmt: CREATE TEXT_P SEARCH DICTIONARY any_name definition' => ObjectKind::TextSearchDictionary,
        'DefineStmt: CREATE TEXT_P SEARCH TEMPLATE any_name definition' => ObjectKind::TextSearchTemplate,
        'DefineStmt: CREATE TEXT_P SEARCH CONFIGURATION any_name definition' => ObjectKind::TextSearchConfiguration,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `DefineStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function define(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        $options = $this->lowering->options;
        if (isset(self::SEARCH[$form->signature])) {
            return new Define(self::SEARCH[$form->signature], $names->dotted($form->node(4)), $options->definitions($form->node(5)));
        }

        return match ($form->signature) {
            'DefineStmt: CREATE opt_or_replace AGGREGATE func_name aggr_args definition' => new Define(ObjectKind::Aggregate, $names->dotted($form->node(3)), $options->definitions($form->node(5)), (new SignatureRule($this->lowering))->aggregateArguments($form->node(4)), $this->lowering->flags->present($form->node(1))),
            'DefineStmt: CREATE opt_or_replace AGGREGATE func_name old_aggr_definition' => new Define(ObjectKind::Aggregate, $names->dotted($form->node(3)), $this->old($form->node(4)), null, $this->lowering->flags->present($form->node(1))),
            'DefineStmt: CREATE OPERATOR any_operator definition' => new Define(ObjectKind::Operator, $this->lowering->operators->operator($form->node(2)), $options->definitions($form->node(3))),
            'DefineStmt: CREATE TYPE_P any_name definition' => new Define(ObjectKind::Type, $names->dotted($form->node(2)), $options->definitions($form->node(3))),
            'DefineStmt: CREATE TYPE_P any_name' => new Define(ObjectKind::Type, $names->dotted($form->node(2)), null),
            default => $this->type($statement),
        };
    }

    /**
     * Lowers the composite, enum, range and collation forms of `DefineStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function type(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        $options = $this->lowering->options;

        return match ($form->signature) {
            'DefineStmt: CREATE TYPE_P any_name AS ( OptTableFuncElementList )' => new CreateComposite($names->dotted($form->node(2)), $this->lowering->types->typedColumns($form->node(5))),
            'DefineStmt: CREATE TYPE_P any_name AS ENUM_P ( opt_enum_val_list )' => new CreateEnum($names->dotted($form->node(2)), $this->lowering->catalogs->enumValues($form->node(6))),
            'DefineStmt: CREATE TYPE_P any_name AS RANGE definition' => new CreateRange($names->dotted($form->node(2)), $options->definitions($form->node(5))),
            'DefineStmt: CREATE COLLATION any_name definition' => new Define(ObjectKind::Collation, $names->dotted($form->node(2)), $options->definitions($form->node(3))),
            'DefineStmt: CREATE COLLATION IF_P NOT EXISTS any_name definition' => new Define(ObjectKind::Collation, $names->dotted($form->node(5)), $options->definitions($form->node(6)), null, false, true),
            'DefineStmt: CREATE COLLATION any_name FROM any_name' => new CopyCollation($names->dotted($form->node(2)), $names->dotted($form->node(4))),
            'DefineStmt: CREATE COLLATION IF_P NOT EXISTS any_name FROM any_name' => new CopyCollation($names->dotted($form->node(5)), $names->dotted($form->node(7)), true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `old_aggr_definition`.
     *
     * @return list<Definition>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function old(Node $definition): array
    {
        $form = $this->lowering->productions->form($definition);
        if ($form->signature !== 'old_aggr_definition: ( old_aggr_list )') {
            throw ImplementationGap::production($form);
        }
        $attributes = [];
        foreach ($this->lowering->items($form->node(1), 'old_aggr_list: old_aggr_elem', 'old_aggr_list: old_aggr_list , old_aggr_elem') as $element) {
            $item = $this->lowering->productions->form($element);
            if ($item->signature !== 'old_aggr_elem: IDENT = def_arg') {
                throw ImplementationGap::production($item);
            }
            $attributes[] = new Definition($this->lowering->names->token($item->token(0)), $this->lowering->options->argument($item->node(2)));
        }

        return $attributes;
    }
}
