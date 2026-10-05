<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionAction;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the generic option machinery.
 *
 * Rule: PG-OPTION-001. Scope: `definition`, `def_list`, `def_elem`,
 * `def_arg`, `opt_definition`, `reloptions`, `opt_reloptions`,
 * `reloption_list`, `reloption_elem`, `create_generic_options`,
 * `generic_option_list`, `generic_option_elem`, `alter_generic_options`,
 * `alter_generic_option_list`, `alter_generic_option_elem`,
 * `opt_boolean_or_string`, `NonReservedWord_or_Sconst`, `var_value`,
 * `var_list`, `var_name`. Constructors: `Definition`, `GenericOption`,
 * `AlteredOption`, `Word`, `KeywordWord`, `Toggle`. An absent optional list is
 * empty. An option changed without an action is added, as the manual states.
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-STORAGE-PARAMETERS,
 * https://www.postgresql.org/docs/17/sql-alterforeigndatawrapper.html, https://www.postgresql.org/docs/17/sql-set.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Options
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `definition`, `opt_definition`, `reloptions` or `opt_reloptions`; an absent list is empty.
     *
     * @return list<Definition>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definitions(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        $items = match ($form->signature) {
            'opt_definition:', 'opt_reloptions:' => [],
            'opt_definition: WITH definition', 'opt_reloptions: WITH reloptions' => $this->definitions($form->node(1)),
            'definition: ( def_list )' => $this->lowering->items($form->node(1), 'def_list: def_elem', 'def_list: def_list , def_elem'),
            'reloptions: ( reloption_list )' => $this->lowering->items($form->node(1), 'reloption_list: reloption_elem', 'reloption_list: reloption_list , reloption_elem'),
            default => throw ImplementationGap::production($form),
        };
        $definitions = [];
        foreach ($items as $item) {
            $definitions[] = $item instanceof Definition ? $item : $this->definition($item);
        }

        return $definitions;
    }

    /**
     * Lowers `reloption_elem`, or `def_elem` through element().
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definition(Node $element): Definition
    {
        $form = $this->lowering->productions->form($element);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'reloption_elem: ColLabel' => new Definition($names->name($form->node(0))),
            'reloption_elem: ColLabel = def_arg' => new Definition($names->name($form->node(0)), $this->argument($form->node(2))),
            'reloption_elem: ColLabel . ColLabel' => new Definition($names->name($form->node(2)), null, $names->name($form->node(0))),
            'reloption_elem: ColLabel . ColLabel = def_arg' => new Definition($names->name($form->node(2)), $this->argument($form->node(4)), $names->name($form->node(0))),
            default => new Definition(...$this->element($element)),
        };
    }

    /**
     * Lowers `definition` into the name and the written value of each attribute, in written order.
     *
     * @return list<array{Name, TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null}>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function elements(Node $definition): array
    {
        $form = $this->lowering->productions->form($definition);
        if ($form->signature !== 'definition: ( def_list )') {
            throw ImplementationGap::production($form);
        }
        $elements = [];
        foreach ($this->lowering->items($form->node(1), 'def_list: def_elem', 'def_list: def_list , def_elem') as $element) {
            $elements[] = $this->element($element);
        }

        return $elements;
    }

    /**
     * Lowers `def_elem` into its name and written value.
     *
     * @return array{Name, TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function element(Node $element): array
    {
        $form = $this->lowering->productions->form($element);

        return match ($form->signature) {
            'def_elem: ColLabel' => [$this->lowering->names->name($form->node(0)), null],
            'def_elem: ColLabel = def_arg' => [$this->lowering->names->name($form->node(0)), $this->argument($form->node(2))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `def_arg`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function argument(Node $argument): TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant
    {
        $form = $this->lowering->productions->form($argument);

        return match ($form->signature) {
            'def_arg: func_type' => $this->lowering->types->functionType($form->node(0)),
            'def_arg: reserved_keyword' => new KeywordWord($this->lowering->leaves->record(new Name((new Keywords($this->lowering))->word($form->node(0))))),
            'def_arg: qual_all_Op' => $this->lowering->operators->operator($form->node(0)),
            'def_arg: NumericOnly' => $this->lowering->literals->signed($form->node(0)),
            'def_arg: Sconst' => $this->lowering->literals->string($form->node(0)),
            'def_arg: NONE' => new KeywordWord(new Name('none')),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `create_generic_options` or `generic_option_list`; an absent list is empty.
     *
     * @return list<GenericOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function genericOptions(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'create_generic_options:') {
            return [];
        }
        $options = [];
        $elements = $form->signature === 'create_generic_options: OPTIONS ( generic_option_list )' ? $form->node(2) : $list;
        foreach ($this->lowering->items($elements, 'generic_option_list: generic_option_elem', 'generic_option_list: generic_option_list , generic_option_elem') as $element) {
            $options[] = new GenericOption(...$this->genericOption($element));
        }

        return $options;
    }

    /**
     * Lowers `generic_option_elem` into its name and value.
     *
     * @return array{Name, StringConstant}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function genericOption(Node $element): array
    {
        $form = $this->lowering->productions->form($element);
        if ($form->signature !== 'generic_option_elem: generic_option_name generic_option_arg') {
            throw ImplementationGap::production($form);
        }

        return [$this->lowering->names->name($form->node(0)), $this->lowering->literals->string($form->node(1))];
    }

    /**
     * Lowers `alter_generic_options`.
     *
     * @return list<AlteredOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alteredOptions(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'alter_generic_options: OPTIONS ( alter_generic_option_list )') {
            throw ImplementationGap::production($form);
        }
        $options = [];
        foreach ($this->lowering->items($form->node(2), 'alter_generic_option_list: alter_generic_option_elem', 'alter_generic_option_list: alter_generic_option_list , alter_generic_option_elem') as $element) {
            $change = $this->lowering->productions->form($element);
            $options[] = match ($change->signature) {
                'alter_generic_option_elem: generic_option_elem' => new AlteredOption(OptionAction::Add, ...$this->genericOption($change->node(0))),
                'alter_generic_option_elem: ADD_P generic_option_elem' => new AlteredOption(OptionAction::Add, ...$this->genericOption($change->node(1))),
                'alter_generic_option_elem: SET generic_option_elem' => new AlteredOption(OptionAction::Set, ...$this->genericOption($change->node(1))),
                'alter_generic_option_elem: DROP generic_option_name' => new AlteredOption(OptionAction::Drop, $this->lowering->names->name($change->node(1))),
                default => throw ImplementationGap::production($change),
            };
        }

        return $options;
    }

    /**
     * Lowers `NonReservedWord_or_Sconst`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function wordOrString(Node $value): Word|StringConstant
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'NonReservedWord_or_Sconst: NonReservedWord' => new Word($this->lowering->names->name($form->node(0))),
            'NonReservedWord_or_Sconst: Sconst' => $this->lowering->literals->string($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_boolean_or_string` or `var_value`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function value(Node $value): OptionArgument
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'opt_boolean_or_string: TRUE_P' => Toggle::True,
            'opt_boolean_or_string: FALSE_P' => Toggle::False,
            'opt_boolean_or_string: ON' => Toggle::On,
            'opt_boolean_or_string: NonReservedWord_or_Sconst' => $this->wordOrString($form->node(0)),
            'var_value: opt_boolean_or_string' => $this->value($form->node(0)),
            'var_value: NumericOnly' => $this->lowering->literals->signed($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `var_list`.
     *
     * @return list<OptionArgument>
     */
    public function values(Node $list): array
    {
        $values = [];
        foreach ($this->lowering->items($list, 'var_list: var_value', 'var_list: var_list , var_value') as $value) {
            $values[] = $this->value($value);
        }

        return $values;
    }

    /**
     * Lowers `var_name`: the parts of the dotted name of a configuration parameter, each of which the grammar reads as a `ColId`.
     *
     * @return list<Name>
     */
    public function variable(Node $name): array
    {
        $parts = [];
        foreach ($this->lowering->items($name, 'var_name: ColId', 'var_name: var_name . ColId') as $part) {
            $parts[] = $this->lowering->names->name($part);
        }

        return $parts;
    }
}
