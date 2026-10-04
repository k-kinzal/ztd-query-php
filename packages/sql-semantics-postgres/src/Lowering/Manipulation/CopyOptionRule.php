<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyAllColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyArguments;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyFlag;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForce;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForceKind;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyOption;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyText;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTextKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the options of COPY.
 *
 * Rule: PG-COPY-OPTION-LOWER-001. Scope: `copy_options`, `copy_opt_list`,
 * `copy_opt_item`, `copy_generic_opt_list`, `copy_generic_opt_elem`,
 * `copy_generic_opt_arg`, `copy_generic_opt_arg_list`,
 * `copy_generic_opt_arg_list_item`. Constructors: `CopyFlag`, `CopyText`,
 * `CopyForce`, `CopyOption`, `CopyAllColumns`, `CopyArguments`. AS between
 * an old-syntax keyword and its string is a noise word. DEFAULT as an
 * argument (PostgreSQL 17) is the keyword word `default`.
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CopyOptionRule
{
    /**
     * The keyword option of each `copy_opt_item` production written as one keyword.
     */
    private const FLAGS = [
        'copy_opt_item: BINARY' => CopyFlag::Binary,
        'copy_opt_item: FREEZE' => CopyFlag::Freeze,
        'copy_opt_item: CSV' => CopyFlag::Csv,
        'copy_opt_item: HEADER_P' => CopyFlag::Header,
    ];

    /**
     * The string option and the position of the string of each `copy_opt_item` production that sets a string.
     */
    private const TEXTS = [
        'copy_opt_item: DELIMITER opt_as Sconst' => [CopyTextKind::Delimiter, 2],
        'copy_opt_item: NULL_P opt_as Sconst' => [CopyTextKind::Null, 2],
        'copy_opt_item: QUOTE opt_as Sconst' => [CopyTextKind::Quote, 2],
        'copy_opt_item: ESCAPE opt_as Sconst' => [CopyTextKind::Escape, 2],
        'copy_opt_item: ENCODING Sconst' => [CopyTextKind::Encoding, 1],
    ];

    /**
     * The FORCE option and the position of the column list (null for `*`) of each FORCE production.
     */
    private const FORCES = [
        'copy_opt_item: FORCE QUOTE columnList' => [CopyForceKind::Quote, 2],
        'copy_opt_item: FORCE QUOTE *' => [CopyForceKind::Quote, null],
        'copy_opt_item: FORCE NOT NULL_P columnList' => [CopyForceKind::NotNull, 3],
        'copy_opt_item: FORCE NOT NULL_P *' => [CopyForceKind::NotNull, null],
        'copy_opt_item: FORCE NULL_P columnList' => [CopyForceKind::Null, 2],
        'copy_opt_item: FORCE NULL_P *' => [CopyForceKind::Null, null],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `copy_options`: the options of the old syntax and the parenthesized options, one of them empty.
     *
     * @return array{list<CopyFlag|CopyText|CopyForce>, list<CopyOption>}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $options): array
    {
        $form = $this->lowering->productions->form($options);
        if ($form->signature === 'copy_options: ( copy_generic_opt_list )') {
            $generic = [];
            foreach ($this->lowering->items($form->node(1), 'copy_generic_opt_list: copy_generic_opt_elem', 'copy_generic_opt_list: copy_generic_opt_list , copy_generic_opt_elem') as $element) {
                $generic[] = $this->option($element);
            }

            return [[], $generic];
        }
        if ($form->signature !== 'copy_options: copy_opt_list') {
            throw ImplementationGap::production($form);
        }
        $legacy = [];
        foreach ($this->lowering->items($form->node(0), 'copy_opt_list: copy_opt_list copy_opt_item', 'copy_opt_list:') as $item) {
            $legacy[] = $this->item($item);
        }

        return [$legacy, []];
    }

    /**
     * Lowers `copy_opt_item`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function item(Node $item): CopyFlag|CopyText|CopyForce
    {
        $form = $this->lowering->productions->form($item);
        if (isset(self::FLAGS[$form->signature])) {
            return self::FLAGS[$form->signature];
        }
        if (isset(self::TEXTS[$form->signature])) {
            [$kind, $position] = self::TEXTS[$form->signature];

            return new CopyText($kind, $this->lowering->literals->string($form->node($position)));
        }
        if (!isset(self::FORCES[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        [$kind, $position] = self::FORCES[$form->signature];

        return $position === null ? new CopyForce($kind, [], true) : new CopyForce($kind, $this->lowering->names->names($form->node($position)));
    }

    /**
     * Lowers `copy_generic_opt_elem`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $element): CopyOption
    {
        $form = $this->lowering->productions->form($element);
        if ($form->signature !== 'copy_generic_opt_elem: ColLabel copy_generic_opt_arg') {
            throw ImplementationGap::production($form);
        }

        return new CopyOption($this->lowering->names->name($form->node(0)), $this->argument($form->node(1)));
    }

    /**
     * Lowers `copy_generic_opt_arg`; no argument is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function argument(Node $argument): ?OptionArgument
    {
        $form = $this->lowering->productions->form($argument);

        return match ($form->signature) {
            'copy_generic_opt_arg: opt_boolean_or_string' => $this->lowering->options->value($form->node(0)),
            'copy_generic_opt_arg: NumericOnly' => $this->lowering->literals->signed($form->node(0)),
            'copy_generic_opt_arg: *' => new CopyAllColumns(),
            'copy_generic_opt_arg: DEFAULT' => new KeywordWord(new Name('default')),
            'copy_generic_opt_arg: ( copy_generic_opt_arg_list )' => $this->arguments($form->node(1)),
            'copy_generic_opt_arg:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `copy_generic_opt_arg_list`.
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function arguments(Node $list): CopyArguments
    {
        $items = [];
        foreach ($this->lowering->items($list, 'copy_generic_opt_arg_list: copy_generic_opt_arg_list_item', 'copy_generic_opt_arg_list: copy_generic_opt_arg_list , copy_generic_opt_arg_list_item') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'copy_generic_opt_arg_list_item: opt_boolean_or_string') {
                throw ImplementationGap::production($form);
            }
            $items[] = $this->lowering->options->value($form->node(0));
        }

        return new CopyArguments($items);
    }
}
