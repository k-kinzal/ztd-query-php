<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the options of EXPLAIN, VACUUM, ANALYZE, CLUSTER and REINDEX.
 *
 * Rule: PG-UTILITY-OPTION-LOWER-001. Scope: `utility_option_list`,
 * `utility_option_elem`, `utility_option_name`, `utility_option_arg`,
 * `opt_verbose`, `opt_full`, `opt_freeze`, `opt_analyze`, `analyze_keyword`.
 * Constructor: `UtilityOption`. A word of the older syntax becomes the
 * option of its name without a value, as the grammar action does; the
 * spelling ANALYSE is kept. Termination: the list is flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-explain.html, https://www.postgresql.org/docs/17/sql-vacuum.html,
 * https://www.postgresql.org/docs/17/sql-analyze.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class OptionRule
{
    /**
     * The option each word production of the older syntax names; null when the word is absent.
     */
    private const WORDS = [
        'opt_full: FULL' => 'full', 'opt_full:' => null,
        'opt_freeze: FREEZE' => 'freeze', 'opt_freeze:' => null,
        'opt_verbose: VERBOSE' => 'verbose', 'opt_verbose:' => null,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `utility_option_list`: the options in the order written.
     *
     * @return list<UtilityOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $list): array
    {
        $options = [];
        foreach ($this->lowering->items($list, 'utility_option_list: utility_option_elem', 'utility_option_list: utility_option_list , utility_option_elem') as $element) {
            $form = $this->lowering->productions->form($element);
            if ($form->signature !== 'utility_option_elem: utility_option_name utility_option_arg') {
                throw ImplementationGap::production($form);
            }
            $options[] = new UtilityOption($this->name($form->node(0)), $this->argument($form->node(1)));
        }

        return $options;
    }

    /**
     * Lowers `utility_option_name`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $name): Name|OptionKeyword
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'utility_option_name: NonReservedWord' => $this->lowering->names->name($form->node(0)),
            'utility_option_name: analyze_keyword' => $this->keyword($form->node(0)),
            'utility_option_name: FORMAT_LA' => OptionKeyword::Format,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `utility_option_arg`; no value is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function argument(Node $argument): Word|StringConstant|Toggle|SignedNumber|null
    {
        $form = $this->lowering->productions->form($argument);
        $value = match ($form->signature) {
            'utility_option_arg: opt_boolean_or_string' => $this->lowering->options->value($form->node(0)),
            'utility_option_arg: NumericOnly' => $this->lowering->literals->signed($form->node(0)),
            'utility_option_arg:' => null,
            default => throw ImplementationGap::production($form),
        };
        if ($value === null || $value instanceof Word || $value instanceof StringConstant || $value instanceof Toggle || $value instanceof SignedNumber) {
            return $value;
        }

        throw ImplementationGap::production($form);
    }

    /**
     * Lowers `analyze_keyword`: the spelling of ANALYZE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $keyword): OptionKeyword
    {
        $form = $this->lowering->productions->form($keyword);

        return match ($form->signature) {
            'analyze_keyword: ANALYZE' => OptionKeyword::Analyze,
            'analyze_keyword: ANALYSE' => OptionKeyword::Analyse,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `analyze_keyword` written as an option word of the older syntax.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function analyze(Node $keyword): UtilityOption
    {
        return new UtilityOption($this->keyword($keyword));
    }

    /**
     * Answers the option a fixed word of the older syntax names.
     */
    public function word(string $option): UtilityOption
    {
        return new UtilityOption(new Name($option));
    }

    /**
     * Lowers the optional words of the older syntax, `opt_full`, `opt_freeze`, `opt_verbose` and `opt_analyze`, into the options written.
     *
     * @return list<UtilityOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function words(Node ...$words): array
    {
        $options = [];
        foreach ($words as $word) {
            $form = $this->lowering->productions->form($word);
            if ($form->signature === 'opt_analyze: analyze_keyword') {
                $options[] = $this->analyze($form->node(0));
                continue;
            }
            if ($form->signature === 'opt_analyze:') {
                continue;
            }
            if (!array_key_exists($form->signature, self::WORDS)) {
                throw ImplementationGap::production($form);
            }
            $option = self::WORDS[$form->signature];
            if ($option !== null) {
                $options[] = $this->word($option);
            }
        }

        return $options;
    }
}
