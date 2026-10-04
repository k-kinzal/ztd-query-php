<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Rules\Identifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers identifiers and the name forms built from them.
 *
 * Rule: PG-NAME-001. Scope: `ColId`, `ColLabel`, `BareColLabel`,
 * `type_function_name`, `NonReservedWord`, `name`, `attr_name`, `param_name`,
 * `columnElem`, `generic_option_name`, `name_list`, `opt_name_list`,
 * `columnList`, `opt_column_list`, `qualified_name`, `qualified_name_list`,
 * `any_name`, `any_name_list`, `attrs`, `func_name`, `opt_single_name`,
 * `opt_qualified_name`, `opt_collate`, `opt_collate_clause`. Constructors:
 * `Name`, `QualifiedName`, `DottedName`. An identifier is decoded by
 * PG-IDENT-001, a keyword by PG-KEYWORD-NAME-001. A relation name and a
 * function name written with an indirection accept field selections only and
 * a relation name at most three parts; the server rejects anything else while
 * parsing, so it is SQL outside the grammar. Termination: lists are flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Names
{
    /**
     * How each single-name production reads its name: 0 an identifier token, 1 a keyword, 2 another name nonterminal.
     */
    private const FORMS = [
        'ColId: IDENT' => 0, 'ColId: unreserved_keyword' => 1, 'ColId: col_name_keyword' => 1,
        'type_function_name: IDENT' => 0, 'type_function_name: unreserved_keyword' => 1, 'type_function_name: type_func_name_keyword' => 1,
        'NonReservedWord: IDENT' => 0, 'NonReservedWord: unreserved_keyword' => 1, 'NonReservedWord: col_name_keyword' => 1, 'NonReservedWord: type_func_name_keyword' => 1,
        'ColLabel: IDENT' => 0, 'ColLabel: unreserved_keyword' => 1, 'ColLabel: col_name_keyword' => 1, 'ColLabel: type_func_name_keyword' => 1, 'ColLabel: reserved_keyword' => 1,
        'BareColLabel: IDENT' => 0, 'BareColLabel: bare_label_keyword' => 1,
        'name: ColId' => 2, 'attr_name: ColLabel' => 2, 'param_name: type_function_name' => 2, 'columnElem: ColId' => 2, 'generic_option_name: ColLabel' => 2,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a single name: `ColId`, `ColLabel`, `BareColLabel`, `type_function_name`, `NonReservedWord`, `name`, `attr_name`, `param_name`, `columnElem` or `generic_option_name`.
     */
    public function name(Node $name): Name
    {
        return $this->lowering->leaves->record(new Name($this->text($name)));
    }

    /**
     * Lowers an identifier token.
     */
    public function token(Token $identifier): Name
    {
        return $this->lowering->leaves->record(new Name((new Identifiers())->decode($identifier->text)));
    }

    /**
     * Reads the decoded text of a single name without creating an operand, for rules that decide by the word.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function text(Node $name): string
    {
        $current = $name;
        while (true) {
            $form = $this->lowering->productions->form($current);
            $kind = self::FORMS[$form->signature] ?? throw ImplementationGap::production($form);
            if ($kind === 0) {
                return (new Identifiers())->decode($form->token(0)->text);
            }
            if ($kind === 1) {
                return (new Keywords($this->lowering))->word($form->node(0));
            }
            $current = $form->node(0);
        }
    }

    /**
     * Lowers a name list: `name_list`, `opt_name_list`, `columnList` or `opt_column_list`; an absent optional list is empty.
     *
     * @return list<Name>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function names(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        $items = match ($form->signature) {
            'opt_name_list:', 'opt_column_list:' => [],
            'opt_name_list: ( name_list )', 'opt_column_list: ( columnList )' => [$form->node(1)],
            default => [$list],
        };
        $names = [];
        foreach ($items as $item) {
            $spine = match ($item->name) {
                'name_list' => ['name_list: name', 'name_list: name_list , name'],
                'columnList' => ['columnList: columnElem', 'columnList: columnList , columnElem'],
                default => throw ImplementationGap::production($form),
            };
            foreach ($this->lowering->items($item, ...$spine) as $element) {
                $names[] = $this->name($element);
            }
        }

        return $names;
    }

    /**
     * Lowers `attrs`: the names after the dots.
     *
     * @return list<Name>
     */
    public function attributes(Node $attributes): array
    {
        $names = [];
        foreach ($this->lowering->items($attributes, 'attrs: . attr_name', 'attrs: attrs . attr_name') as $attribute) {
            $names[] = $this->name($attribute);
        }

        return $names;
    }

    /**
     * Lowers an `indirection` that the grammar action accepts as field selections only.
     *
     * @return list<Name>
     *
     * @throws AnalysisException When the indirection holds a subscript, a slice or a star, which `check_qualified_name` and `check_func_name` of `gram.y` reject in a name
     */
    public function fields(Node $indirection): array
    {
        $names = [];
        foreach ($this->lowering->expressions->indirection($indirection) as $step) {
            $names[] = $step->field() ?? throw new AnalysisException('syntax error: a name takes field selections only');
        }

        return $names;
    }

    /**
     * Lowers `any_name` or `func_name`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function dotted(Node $name): DottedName
    {
        $form = $this->lowering->productions->form($name);

        return new DottedName(match ($form->signature) {
            'any_name: ColId', 'func_name: type_function_name' => [$this->name($form->node(0))],
            'any_name: ColId attrs' => [$this->name($form->node(0)), ...$this->attributes($form->node(1))],
            'func_name: ColId indirection' => [$this->name($form->node(0)), ...$this->fields($form->node(1))],
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Lowers `any_name_list`.
     *
     * @return list<DottedName>
     */
    public function dottedList(Node $list): array
    {
        $names = [];
        foreach ($this->lowering->items($list, 'any_name_list: any_name', 'any_name_list: any_name_list , any_name') as $name) {
            $names[] = $this->dotted($name);
        }

        return $names;
    }

    /**
     * Lowers `qualified_name`: a relation name of one to three parts.
     *
     * @throws AnalysisException When the name has more than three parts, which `makeRangeVarFromQualifiedName` of `gram.y` rejects
     * @throws ImplementationGap When the production has no rule
     */
    public function qualified(Node $name): QualifiedName
    {
        $form = $this->lowering->productions->form($name);
        $parts = match ($form->signature) {
            'qualified_name: ColId' => [$this->name($form->node(0))],
            'qualified_name: ColId indirection' => [$this->name($form->node(0)), ...$this->fields($form->node(1))],
            default => throw ImplementationGap::production($form),
        };

        return (new DottedName($parts))->qualified() ?? throw new AnalysisException('improper qualified name (too many dotted names)');
    }

    /**
     * Lowers `qualified_name_list`.
     *
     * @return list<QualifiedName>
     */
    public function qualifiedList(Node $list): array
    {
        $names = [];
        foreach ($this->lowering->items($list, 'qualified_name_list: qualified_name', 'qualified_name_list: qualified_name_list , qualified_name') as $name) {
            $names[] = $this->qualified($name);
        }

        return $names;
    }

    /**
     * Lowers `opt_single_name`: a name or nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optional(Node $name): ?Name
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'opt_single_name:' => null,
            'opt_single_name: ColId' => $this->name($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_qualified_name`, `opt_collate` or `opt_collate_clause`: a dotted name or nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalDotted(Node $name): ?DottedName
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'opt_qualified_name:', 'opt_collate:', 'opt_collate_clause:' => null,
            'opt_qualified_name: any_name' => $this->dotted($form->node(0)),
            'opt_collate: COLLATE any_name', 'opt_collate_clause: COLLATE any_name' => $this->dotted($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
