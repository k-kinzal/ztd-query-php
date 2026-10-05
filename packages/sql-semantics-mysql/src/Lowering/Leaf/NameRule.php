<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Identifiers;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers the identifier productions of every release into decoded names.
 *
 * Rule: MYSQL-NAME-001. Scope: ident, IDENT_sys, label_ident, role_ident,
 * lvalue_ident, ident_or_text, role_ident_or_text, schema, the
 * keyword-as-identifier rules, opt_ident, ident_or_empty, opt_component,
 * storage_engines, known_storage_engines, table_ident, table_name,
 * table_ident_nodb, table_ident_opt_wild, sp_name, table_list,
 * opt_table_list, ident_string_list, simple_ident, simple_ident_nospvar,
 * simple_ident_q, field_ident, table_wild. An identifier token is decoded by
 * MYSQL-IDENTIFIER-DECODE-001; a keyword at a keyword-as-identifier
 * production is the name it spells, letter case kept; a string or a host
 * name at a name position is the name it holds. The leading dot of
 * `.table` and `.table.column` names the default database and is not kept.
 * Every name is recorded as an operand leaf. Constructs: Name,
 * QualifiedName, ColumnUse, ColumnName, TableWildcard. Terminates: unit
 * productions are followed in a loop over strict subtrees and lists are
 * flattened iteratively. Source: https://dev.mysql.com/doc/refman/8.4/en/identifiers.html,
 * https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html,
 * https://dev.mysql.com/doc/refman/8.4/en/keywords.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NameRule
{
    /**
     * The unit productions that pass a name on to their only child.
     */
    private const FORWARD = [
        'ident: IDENT_sys' => true, 'ident: keyword' => true, 'ident: ident_keyword' => true, 'keyword: keyword_sp' => true,
        'label_ident: IDENT_sys' => true, 'label_ident: keyword_sp' => true, 'label_ident: label_keyword' => true,
        'role_ident: IDENT_sys' => true, 'role_ident: role_keyword' => true, 'lvalue_ident: IDENT_sys' => true, 'lvalue_ident: lvalue_keyword' => true,
        'ident_or_text: ident' => true, 'ident_or_text: TEXT_STRING_sys' => true, 'role_ident_or_text: role_ident' => true,
        'role_ident_or_text: TEXT_STRING_sys' => true, 'schema: ident' => true, 'opt_ident: ident' => true,
        'ident_or_empty: ident' => true, 'storage_engines: ident_or_text' => true, 'known_storage_engines: ident_or_text' => true,
        'ident_keyword: ident_keywords_unambiguous' => true, 'ident_keyword: ident_keywords_ambiguous_1_roles_and_labels' => true,
        'ident_keyword: ident_keywords_ambiguous_2_labels' => true, 'ident_keyword: ident_keywords_ambiguous_3_roles' => true,
        'ident_keyword: ident_keywords_ambiguous_4_system_variables' => true, 'label_keyword: ident_keywords_unambiguous' => true,
        'label_keyword: ident_keywords_ambiguous_3_roles' => true, 'label_keyword: ident_keywords_ambiguous_4_system_variables' => true,
        'role_keyword: ident_keywords_unambiguous' => true, 'role_keyword: ident_keywords_ambiguous_2_labels' => true,
        'role_keyword: ident_keywords_ambiguous_4_system_variables' => true, 'lvalue_keyword: ident_keywords_unambiguous' => true,
        'lvalue_keyword: ident_keywords_ambiguous_1_roles_and_labels' => true, 'lvalue_keyword: ident_keywords_ambiguous_2_labels' => true,
        'lvalue_keyword: ident_keywords_ambiguous_3_roles' => true, 'table_name: table_ident' => true, 'simple_ident: simple_ident_q' => true,
        'simple_ident_nospvar: simple_ident_q' => true,
    ];

    /**
     * The productions whose only token is a name, by how the token holds it.
     */
    private const TOKENS = [
        'IDENT_sys: IDENT' => 'identifier', 'IDENT_sys: IDENT_QUOTED' => 'identifier',
        'ident_or_text: LEX_HOSTNAME' => 'word', 'role_ident_or_text: LEX_HOSTNAME' => 'word',
    ];

    /**
     * The qualified name productions, by the positions of the object name and of its qualifier.
     */
    private const QUALIFIED = [
        'table_ident: ident' => [0, null], 'table_ident: ident . ident' => [2, 0], 'table_ident: . ident' => [1, null],
        'table_ident_nodb: ident' => [0, null], 'sp_name: ident' => [0, null], 'sp_name: ident . ident' => [2, 0],
        'table_ident_opt_wild: ident opt_wild' => [0, null], 'table_ident_opt_wild: ident . ident opt_wild' => [2, 0],
    ];

    /**
     * The column productions, by the positions of the column, table and database names.
     */
    private const COLUMNS = [
        'simple_ident: ident' => [0, null, null], 'simple_ident_nospvar: ident' => [0, null, null],
        'simple_ident_q: ident . ident' => [2, 0, null], 'simple_ident_q: . ident . ident' => [3, 1, null],
        'simple_ident_q: ident . ident . ident' => [4, 2, 0], 'field_ident: ident' => [0, null, null],
        'field_ident: ident . ident . ident' => [4, 2, 0], 'field_ident: ident . ident' => [2, 0, null], 'field_ident: . ident' => [1, null, null],
    ];

    /**
     * The productions that hold no name.
     */
    private const ABSENT = ['opt_ident:' => true, 'ident_or_empty:' => true, 'opt_component:' => true];

    /**
     * @var array<string, true>|null
     */
    private static ?array $keywords = null;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a position that holds exactly one unqualified name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function identifier(Node $name): Name
    {
        $form = $this->lowering->productions->form($name);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        if ($form->node->name === 'TEXT_STRING_sys' || $form->node->name === 'TEXT_STRING_validated') {
            return $this->lowering->leaves->record(new Name($this->lowering->literals->bytes($form->node)));
        }
        $kind = self::TOKENS[$form->signature] ?? ($this->keyword($form->signature) ? 'word' : null);
        if ($kind === null) {
            throw ImplementationGap::production($form);
        }
        $text = $form->token(0)->text;

        return $this->lowering->leaves->record(new Name($kind === 'identifier' ? (new Identifiers())->decode($text) : $text));
    }

    /**
     * Tells whether a production reads a keyword as the identifier it spells.
     */
    public function keyword(string $signature): bool
    {
        self::$keywords ??= array_fill_keys([...LegacyKeywords::SIGNATURES, ...UnambiguousKeywords::SIGNATURES, ...AmbiguousKeywords::SIGNATURES], true);

        return isset(self::$keywords[$signature]);
    }

    /**
     * Lowers a position that holds a name or nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optional(Node $name): ?Name
    {
        $form = $this->lowering->productions->form($name);
        if (isset(self::ABSENT[$form->signature])) {
            return null;
        }

        return $form->signature === 'opt_component: . ident' ? $this->identifier($form->node(1)) : $this->identifier($name);
    }

    /**
     * Lowers the name of a table, view or routine with its optional database qualifier.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function qualified(Node $name): QualifiedName
    {
        $form = $this->lowering->productions->form($name);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        $positions = self::QUALIFIED[$form->signature] ?? null;
        if ($positions === null) {
            throw ImplementationGap::production($form);
        }
        $schema = $positions[1] === null ? null : $this->identifier($form->node($positions[1]));

        return new QualifiedName($this->identifier($form->node($positions[0])), $schema);
    }

    /**
     * Tells whether a table name is written after a leading dot, `.t` (MySQL 5.6 and 5.7).
     */
    public function dotted(Node $name): bool
    {
        $form = $this->lowering->productions->form($name);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }

        return $form->signature === 'table_ident: . ident';
    }

    /**
     * Lowers a comma-separated list of table names; an absent list is empty.
     *
     * @return list<QualifiedName>
     * @throws ImplementationGap When a production has no rule
     */
    public function qualifiedList(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'opt_table_list:') {
            return [];
        }
        $spine = $form->signature === 'opt_table_list: table_list' ? $form->node(0) : $list;
        $names = [];
        foreach ((new Lists())->items($spine) as $item) {
            $names[] = $this->qualified($item);
        }
        $this->claimed($this->lowering->productions->form($spine), [
            'table_list: table_name', 'table_list: table_list , table_name', 'table_list: table_ident', 'table_list: table_list , table_ident',
        ]);

        return $names;
    }

    /**
     * Lowers a comma-separated list of unqualified names.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function identifiers(Node $list): array
    {
        $this->claimed($this->lowering->productions->form($list), ['ident_string_list: ident', 'ident_string_list: ident_string_list , ident']);
        $names = [];
        foreach ((new Lists())->items($list) as $item) {
            $names[] = $this->identifier($item);
        }

        return $names;
    }

    /**
     * Lowers a name used as a value into a column use.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function column(Node $column): ColumnUse
    {
        $form = $this->lowering->productions->form($column);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        $positions = self::COLUMNS[$form->signature] ?? null;
        if ($positions === null || str_starts_with($form->signature, 'field_ident')) {
            throw ImplementationGap::production($form);
        }

        $dot = $form->signature === 'simple_ident_q: . ident . ident' ? OptionalWords::Written : OptionalWords::Omitted;

        return $this->lowering->leaves->record(new ColumnUse($this->identifier($form->node($positions[0])), $this->qualifier($form, $positions[1], $positions[2]), $dot));
    }

    /**
     * Lowers the name of a column at a position that defines or alters it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function columnName(Node $field): ColumnName
    {
        $form = $this->lowering->productions->form($field);
        $positions = self::COLUMNS[$form->signature] ?? null;
        if ($positions === null || !str_starts_with($form->signature, 'field_ident')) {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->leaves->record(new ColumnName($this->identifier($form->node($positions[0])), $this->qualifier($form, $positions[1], $positions[2])));
    }

    /**
     * Lowers the optional name of an index or constraint, which MySQL 5.x lets a table name qualify.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalColumnName(Node $name): ?ColumnName
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'opt_ident:' => null,
            'opt_ident: field_ident' => $this->columnName($form->node(0)),
            'opt_ident: ident' => $this->lowering->leaves->record(new ColumnName($this->identifier($form->node(0)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the table and database qualifier of a column production, when it has one.
     */
    public function qualifier(Form $form, ?int $table, ?int $schema): ?QualifiedName
    {
        if ($table === null) {
            return null;
        }

        return new QualifiedName($this->identifier($form->node($table)), $schema === null ? null : $this->identifier($form->node($schema)));
    }

    /**
     * Lowers a qualified star.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function wildcard(Node $wildcard): TableWildcard
    {
        $form = $this->lowering->productions->form($wildcard);

        return $this->lowering->leaves->record(match ($form->signature) {
            'table_wild: ident . *' => new TableWildcard(new QualifiedName($this->identifier($form->node(0)))),
            'table_wild: ident . ident . *' => new TableWildcard(new QualifiedName($this->identifier($form->node(2)), $this->identifier($form->node(0)))),
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Confirms that a list production is one this rule flattens.
     *
     * @param list<string> $signatures The list productions of the rule
     * @throws ImplementationGap When the production has no rule
     */
    public function claimed(Form $form, array $signatures): void
    {
        if (!in_array($form->signature, $signatures, true)) {
            throw ImplementationGap::production($form);
        }
    }
}
