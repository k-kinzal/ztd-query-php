<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Alter;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\TableChange\Choices;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlterModifier;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the modifiers of ALTER TABLE and the ALGORITHM and LOCK options of CREATE INDEX and DROP INDEX.
 *
 * Rule: MYSQL-ALTER-MODIFIER-001. Scope: alter_algorithm_option,
 * alter_algorithm_option_value, alter_lock_option, alter_lock_option_value,
 * opt_index_lock_algorithm, opt_index_lock_and_algorithm,
 * alter_commands_modifier_list, alter_commands_modifier, opt_validation,
 * alter_opt_validation, opt_with_validation, with_validation. The `=` is
 * an optional word; the keyword DEFAULT is the absent name; the name of an
 * algorithm or lock level remains available when its definition condition is published
 * (MYSQL-ALTER-CHOICES-001). Constructs: AlgorithmOption,
 * LockOption, ValidationOption. Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class ModifierRule
{
    /**
     * The option productions, by whether they set the lock level and the position of the value.
     */
    private const OPTIONS = [
        'alter_algorithm_option: ALGORITHM_SYM opt_equal DEFAULT' => [false, null],
        'alter_algorithm_option: ALGORITHM_SYM opt_equal ident' => [false, 2],
        'alter_algorithm_option: ALGORITHM_SYM opt_equal alter_algorithm_option_value' => [false, 2],
        'alter_lock_option: LOCK_SYM opt_equal DEFAULT' => [true, null],
        'alter_lock_option: LOCK_SYM opt_equal ident' => [true, 2],
        'alter_lock_option: LOCK_SYM opt_equal alter_lock_option_value' => [true, 2],
    ];

    /**
     * The productions of the option pairs of CREATE INDEX and DROP INDEX, by the positions of their options.
     */
    private const PAIRS = [
        'opt_index_lock_algorithm:' => [], 'opt_index_lock_algorithm: alter_lock_option' => [0], 'opt_index_lock_algorithm: alter_algorithm_option' => [0],
        'opt_index_lock_algorithm: alter_lock_option alter_algorithm_option' => [0, 1], 'opt_index_lock_algorithm: alter_algorithm_option alter_lock_option' => [0, 1],
        'opt_index_lock_and_algorithm:' => [], 'opt_index_lock_and_algorithm: alter_lock_option' => [0], 'opt_index_lock_and_algorithm: alter_algorithm_option' => [0],
        'opt_index_lock_and_algorithm: alter_lock_option alter_algorithm_option' => [0, 1],
        'opt_index_lock_and_algorithm: alter_algorithm_option alter_lock_option' => [0, 1],
    ];

    /**
     * The validation productions, by the validation they request; null for none.
     */
    private const VALIDATIONS = [
        'opt_validation:' => null, 'opt_with_validation:' => null, 'alter_opt_validation: WITH VALIDATION_SYM' => true,
        'alter_opt_validation: WITHOUT_SYM VALIDATION_SYM' => false, 'with_validation: WITH VALIDATION_SYM' => true,
        'with_validation: WITHOUT_SYM VALIDATION_SYM' => false,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an ALGORITHM or LOCK option: a node of `alter_algorithm_option` or `alter_lock_option`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): AlgorithmOption|LockOption
    {
        $form = $this->lowering->form($option);
        [$lock, $value] = self::OPTIONS[$form->signature] ?? throw ImplementationGap::production($form);
        $equals = $this->lowering->options->present($form->node(1));
        $name = $value === null ? null : $this->value($form->node($value));

        return $lock ? new LockOption($name, equals: $equals) : new AlgorithmOption($name, equals: $equals);
    }

    /**
     * Preserves an unknown option's abort before later input or warnings.
     */
    public function completed(Node $node): ?ParseFailure
    {
        if (!in_array($node->name, ['alter_algorithm_option', 'alter_lock_option'], true)) {
            return null;
        }
        $option = $this->option($node);
        $lock = $option instanceof LockOption;
        $problem = (new Choices())->refusal($lock ? $option->lock : $option->algorithm, $lock, $this->lowering->profile->grammar);
        return $problem === null ? null : new ParseFailure($problem, true);
    }

    /**
     * Lowers the value of an option: a node of `ident`, `alter_algorithm_option_value` or `alter_lock_option_value`; DEFAULT is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Node $value): ?Name
    {
        if ($value->name === 'ident') {
            return $this->lowering->names->identifier($value);
        }
        $form = $this->lowering->form($value);

        return match ($form->signature) {
            'alter_algorithm_option_value: DEFAULT_SYM', 'alter_lock_option_value: DEFAULT_SYM' => null,
            'alter_algorithm_option_value: ident', 'alter_lock_option_value: ident' => $this->lowering->names->identifier($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the ALGORITHM and LOCK options of CREATE INDEX or DROP INDEX in the order written.
     *
     * @return list<AlgorithmOption|LockOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function pair(Node $options): array
    {
        $form = $this->lowering->form($options);
        $positions = self::PAIRS[$form->signature] ?? throw ImplementationGap::production($form);
        $lowered = [];
        foreach ($positions as $position) {
            $lowered[] = $this->option($form->node($position));
        }

        return $lowered;
    }

    /**
     * Lowers a comma-separated list of modifiers: a node of `alter_commands_modifier_list`.
     *
     * @return list<AlterModifier>
     * @throws ImplementationGap When a production has no rule
     */
    public function modifiers(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature !== 'alter_commands_modifier_list: alter_commands_modifier' && $form->signature !== 'alter_commands_modifier_list: alter_commands_modifier_list , alter_commands_modifier') {
            throw ImplementationGap::production($form);
        }
        $modifiers = [];
        foreach ((new Lists())->items($list) as $item) {
            $modifiers[] = $this->modifier($item);
        }

        return $modifiers;
    }

    /**
     * Lowers one modifier: a node of `alter_commands_modifier`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function modifier(Node $modifier): AlterModifier
    {
        $form = $this->lowering->form($modifier);

        return match ($form->signature) {
            'alter_commands_modifier: alter_algorithm_option', 'alter_commands_modifier: alter_lock_option' => $this->option($form->node(0)),
            'alter_commands_modifier: alter_opt_validation', 'alter_commands_modifier: with_validation' => $this->validation($form->node(0))
                ?? throw ImplementationGap::production($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a validation option: a node of `opt_validation`, `alter_opt_validation`, `opt_with_validation` or `with_validation`; none is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function validation(Node $validation): ?ValidationOption
    {
        $form = $this->lowering->form($validation);
        if ($form->signature === 'opt_validation: alter_opt_validation' || $form->signature === 'opt_with_validation: with_validation') {
            return $this->validation($form->node(0));
        }
        if (!array_key_exists($form->signature, self::VALIDATIONS)) {
            throw ImplementationGap::production($form);
        }
        $validate = self::VALIDATIONS[$form->signature];

        return $validate === null ? null : new ValidationOption($validate);
    }
}
