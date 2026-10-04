<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Explain;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the options of EXPLAIN in MySQL 8.0 and later.
 *
 * Rule: MYSQL-EXPLAIN-OPTION-LOWERING-001. Scope: opt_explain_options,
 * opt_explain_format, opt_explain_into, opt_explain_for_schema. The format
 * is a name the server compares without regard to case; INTO names a user
 * variable; FOR DATABASE names a database. Terminates: unit productions
 * over strict subtrees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class OptionRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `opt_explain_options` into the format, ANALYZE and the variable of INTO.
     *
     * @return array{Name|null, bool, UserVariable|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $options): array
    {
        $form = $this->lowering->form($options);

        return match ($form->signature) {
            'opt_explain_options: opt_explain_format' => [$this->format($form->node(0)), false, null],
            'opt_explain_options: ANALYZE_SYM opt_explain_format' => [$this->format($form->node(1)), true, null],
            'opt_explain_options: opt_explain_format opt_explain_into' => [$this->format($form->node(0)), false, $this->into($form->node(1))],
            'opt_explain_options: ANALYZE_SYM opt_explain_format opt_explain_into' => [$this->format($form->node(1)), true, $this->into($form->node(2))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a node of `opt_explain_format`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function format(Node $format): ?Name
    {
        $form = $this->lowering->form($format);

        return match ($form->signature) {
            'opt_explain_format:' => null,
            'opt_explain_format: FORMAT_SYM EQ ident_or_text' => $this->lowering->names->identifier($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a node of `opt_explain_into`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function into(Node $into): ?UserVariable
    {
        $form = $this->lowering->form($into);

        return match ($form->signature) {
            'opt_explain_into:' => null,
            'opt_explain_into: INTO @ ident_or_text' => $this->lowering->variables->user($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a node of `opt_explain_for_schema`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function database(Node $database): ?Name
    {
        $form = $this->lowering->form($database);

        return match ($form->signature) {
            'opt_explain_for_schema:' => null,
            'opt_explain_for_schema: FOR_SYM DATABASE ident_or_text' => $this->lowering->names->identifier($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }
}
