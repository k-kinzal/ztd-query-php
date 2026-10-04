<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonExistsColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonNestedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonOrdinalityColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTableColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonValueColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOption;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlNamespace;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTableColumn;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node as StatementNode;

/**
 * Lowers XMLTABLE and JSON_TABLE in FROM.
 *
 * Rule: PG-TABLE-FUNCTION-LOWER-001. Scope: `xmltable`,
 * `xmltable_column_list`, `xmltable_column_el`,
 * `xmltable_column_option_list`, `xmltable_column_option_el`,
 * `xml_namespace_list`, `xml_namespace_el`, `json_table`,
 * `json_table_path_name_opt`, `json_table_column_definition_list`,
 * `json_table_column_definition`, `path_opt`,
 * `json_table_column_path_clause_opt`. Constructors: `XmlTable`,
 * `XmlNamespace`, `XmlTableColumn`, `XmlColumnOption`, `JsonTable`,
 * `JsonOrdinalityColumn`, `JsonValueColumn`, `JsonExistsColumn`,
 * `JsonNestedColumns`. The PASSING, FORMAT, wrapper, quotes and behavior
 * clauses are lowered by the invocation family. The PATH word of NESTED is
 * optional. Termination: lists are flattened iteratively; nested columns
 * recurse on the tree depth.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PROCESSING-XMLTABLE,
 * https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TableFunctionRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `xmltable` with its alias.
     *
     * @param array{Name|null, list<Name>} $alias
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function xml(Node $table, array $alias, bool $lateral): XmlTable
    {
        $form = $this->lowering->productions->form($table);
        $offset = match ($form->signature) {
            'xmltable: XMLTABLE ( c_expr xmlexists_argument COLUMNS xmltable_column_list )' => 0,
            'xmltable: XMLTABLE ( XMLNAMESPACES ( xml_namespace_list ) , c_expr xmlexists_argument COLUMNS xmltable_column_list )' => 5,
            default => throw ImplementationGap::production($form),
        };
        $namespaces = $offset === 0 ? [] : $this->namespaces($form->node(4));

        return new XmlTable(
            $this->lowering->expressions->expression($form->node(2 + $offset)),
            $this->lowering->invocations->xmlPassing($form->node(3 + $offset)),
            $this->xmlColumns($form->node(5 + $offset)),
            $namespaces,
            $alias[0],
            $alias[1],
            $lateral,
        );
    }

    /**
     * Lowers `xml_namespace_list`.
     *
     * @return list<XmlNamespace>
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function namespaces(Node $list): array
    {
        $namespaces = [];
        foreach ($this->lowering->items($list, 'xml_namespace_list: xml_namespace_el', 'xml_namespace_list: xml_namespace_list , xml_namespace_el') as $item) {
            $form = $this->lowering->productions->form($item);
            $namespaces[] = match ($form->signature) {
                'xml_namespace_el: b_expr AS ColLabel' => new XmlNamespace($this->lowering->expressions->expression($form->node(0)), $this->lowering->names->name($form->node(2))),
                'xml_namespace_el: DEFAULT b_expr' => new XmlNamespace($this->lowering->expressions->expression($form->node(1))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $namespaces;
    }

    /**
     * Lowers `xmltable_column_list`.
     *
     * @return list<XmlTableColumn>
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function xmlColumns(Node $list): array
    {
        $columns = [];
        foreach ($this->lowering->items($list, 'xmltable_column_list: xmltable_column_el', 'xmltable_column_list: xmltable_column_list , xmltable_column_el') as $item) {
            $form = $this->lowering->productions->form($item);
            $name = $this->lowering->names->name($form->node(0));
            $columns[] = match ($form->signature) {
                'xmltable_column_el: ColId Typename' => new XmlTableColumn($name, $this->lowering->types->typeName($form->node(1))),
                'xmltable_column_el: ColId Typename xmltable_column_option_list' => new XmlTableColumn($name, $this->lowering->types->typeName($form->node(1)), $this->options($form->node(2))),
                'xmltable_column_el: ColId FOR ORDINALITY' => new XmlTableColumn($name),
                default => throw ImplementationGap::production($form),
            };
        }

        return $columns;
    }

    /**
     * Lowers `xmltable_column_option_list`.
     *
     * @return list<XmlColumnOption>
     *
     * @throws ImplementationGap When an option has no rule
     */
    public function options(Node $list): array
    {
        $options = [];
        foreach ($this->lowering->items($list, 'xmltable_column_option_list: xmltable_column_option_el', 'xmltable_column_option_list: xmltable_column_option_list xmltable_column_option_el') as $item) {
            $form = $this->lowering->productions->form($item);
            $options[] = match ($form->signature) {
                'xmltable_column_option_el: IDENT b_expr' => new XmlColumnOption(XmlColumnOptionKind::Named, $this->lowering->expressions->expression($form->node(1)), $this->lowering->names->token($form->token(0))),
                'xmltable_column_option_el: DEFAULT b_expr' => new XmlColumnOption(XmlColumnOptionKind::Default, $this->lowering->expressions->expression($form->node(1))),
                'xmltable_column_option_el: PATH b_expr' => new XmlColumnOption(XmlColumnOptionKind::Path, $this->lowering->expressions->expression($form->node(1))),
                'xmltable_column_option_el: NOT NULL_P' => new XmlColumnOption(XmlColumnOptionKind::NotNull),
                'xmltable_column_option_el: NULL_P' => new XmlColumnOption(XmlColumnOptionKind::Null),
                default => throw ImplementationGap::production($form),
            };
        }

        return $options;
    }

    /**
     * Lowers `json_table` with its alias.
     *
     * @param array{Name|null, list<Name>} $alias
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function json(Node $table, array $alias, bool $lateral): JsonTable
    {
        $form = $this->lowering->productions->form($table);
        if ($form->signature !== 'json_table: JSON_TABLE ( json_value_expr , a_expr json_table_path_name_opt json_passing_clause_opt COLUMNS ( json_table_column_definition_list ) json_on_error_clause_opt )') {
            throw ImplementationGap::production($form);
        }
        $path = $this->lowering->productions->form($form->node(5));
        $invocations = $this->lowering->invocations;

        return new JsonTable(
            $invocations->jsonValue($form->node(2)),
            $this->lowering->expressions->expression($form->node(4)),
            $this->jsonColumns($form->node(9)),
            match ($path->signature) {
                'json_table_path_name_opt: AS name' => $this->lowering->names->name($path->node(1)),
                'json_table_path_name_opt:' => null,
                default => throw ImplementationGap::production($path),
            },
            $invocations->jsonPassing($form->node(6)),
            $this->clause($invocations->jsonBehavior($form->node(11)), $form),
            $alias[0],
            $alias[1],
            $lateral,
        );
    }

    /**
     * Lowers `json_table_column_definition_list`.
     *
     * @return list<JsonTableColumn>
     *
     * @throws ImplementationGap When a definition has no rule
     */
    public function jsonColumns(Node $list): array
    {
        $columns = [];
        foreach ($this->lowering->items($list, 'json_table_column_definition_list: json_table_column_definition', 'json_table_column_definition_list: json_table_column_definition_list , json_table_column_definition') as $item) {
            $form = $this->lowering->productions->form($item);
            $columns[] = match ($form->signature) {
                'json_table_column_definition: ColId FOR ORDINALITY' => new JsonOrdinalityColumn($this->lowering->names->name($form->node(0))),
                'json_table_column_definition: ColId Typename json_table_column_path_clause_opt json_wrapper_behavior json_quotes_clause_opt json_behavior_clause_opt' => $this->value($form, null, 2),
                'json_table_column_definition: ColId Typename json_format_clause json_table_column_path_clause_opt json_wrapper_behavior json_quotes_clause_opt json_behavior_clause_opt' => $this->value($form, $this->clause($this->lowering->invocations->jsonFormat($form->node(2)), $form), 3),
                'json_table_column_definition: ColId Typename EXISTS json_table_column_path_clause_opt json_on_error_clause_opt' => new JsonExistsColumn(
                    $this->lowering->names->name($form->node(0)),
                    $this->lowering->types->typeName($form->node(1)),
                    $this->path($form->node(3)),
                    $this->clause($this->lowering->invocations->jsonBehavior($form->node(4)), $form),
                ),
                'json_table_column_definition: NESTED path_opt Sconst COLUMNS ( json_table_column_definition_list )' => $this->nested($form, 5, null),
                'json_table_column_definition: NESTED path_opt Sconst AS name COLUMNS ( json_table_column_definition_list )' => $this->nested($form, 7, $this->lowering->names->name($form->node(4))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $columns;
    }

    /**
     * Lowers a value column whose path clause is at a position.
     */
    public function value(Form $form, ?Clause $format, int $path): JsonValueColumn
    {
        $invocations = $this->lowering->invocations;

        return new JsonValueColumn(
            $this->lowering->names->name($form->node(0)),
            $this->lowering->types->typeName($form->node(1)),
            $this->path($form->node($path)),
            $format,
            $this->clause($invocations->jsonWrapper($form->node($path + 1)), $form),
            $this->clause($invocations->jsonQuotes($form->node($path + 2)), $form),
            $this->clause($invocations->jsonBehavior($form->node($path + 3)), $form),
        );
    }

    /**
     * Lowers NESTED columns whose column list is at a position.
     *
     * @throws ImplementationGap When `path_opt` has no rule
     */
    public function nested(Form $form, int $columns, ?Name $name): JsonNestedColumns
    {
        $path = $this->lowering->productions->form($form->node(1));
        if ($path->signature !== 'path_opt: PATH' && $path->signature !== 'path_opt:') {
            throw ImplementationGap::production($path);
        }

        return new JsonNestedColumns($this->lowering->literals->string($form->node(2)), $this->jsonColumns($form->node($columns)), $name);
    }

    /**
     * Lowers `json_table_column_path_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function path(Node $clause): ?StringConstant
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'json_table_column_path_clause_opt: PATH Sconst' => $this->lowering->literals->string($form->node(1)),
            'json_table_column_path_clause_opt:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Answers a clause the invocation family lowered, refusing a node that is not a clause.
     *
     * @throws ImplementationGap When the node is not a clause
     */
    public function clause(?StatementNode $node, Form $form): ?Clause
    {
        if ($node !== null && !$node instanceof Clause) {
            throw ImplementationGap::production($form);
        }

        return $node;
    }
}
