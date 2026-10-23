<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Explain;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat;

/**
 * Preserves a completed EXPLAIN format refusal before later malformed operands.
 *
 * The reduction supplies a decoded name; the published notice retains no syntax tree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxFormats
{
    /**
     * Returns the aborting format diagnostic of a completed FORMAT assignment.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name === 'opt_explain_for_schema' && in_array($lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql901, \SqlSemantics\Contract\GrammarRelease::MySql910], true)) {
            try {
                (new OptionRule($lowering))->database($node);
            } catch (\SqlSemantics\Construction\RejectedInput $failure) {
                return new ParseFailure($failure->problem, true);
            }
        }
        $into = $this->into($node, $lowering);
        if ($into !== null) {
            return new ParseFailure($into, true);
        }
        if (!in_array($node->name, ['opt_explain_format', 'opt_extended_describe'], true) || count($node->children) !== 3) {
            return null;
        }
        $form = $lowering->form($node);
        $name = $lowering->names->identifier($form->node(2));
        $refusal = (new ExplainFacts())->refusal($lowering->profile->grammar, strtoupper($name->value), false, false);

        return $refusal === null ? null : new ParseFailure(new UnknownExplainFormat($name), true);
    }
    /**
     * Retains a completed modifier's notice before later input can fail.
     */
    public function modifier(Node $node, Lowering $lowering): ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated
    {
        if ($node->name === 'opt_extended_describe' && count($node->children) === 1) {
            $modifier = match ($lowering->form($node)->signature) {
                'opt_extended_describe: EXTENDED_SYM' => \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Extended,
                'opt_extended_describe: PARTITIONS_SYM' => \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Partitions,
                default => null,
            };
            return $modifier?->deprecation($lowering->profile->grammar);
        }
        return null;
    }

    /**
     * Retains INTO's refusal at the options boundary, or at the completed statement in 8.1.
     * MySQL 8.1 reads later operand notices before rejecting INTO; 8.2 onward rejects
     * completed options before reading the explained operand.
     */
    public function into(Node $node, Lowering $lowering): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto|\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget|null
    {
        $legacy = $lowering->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql810;
        if ($node->name === 'opt_explain_into') {
            return $this->target((new OptionRule($lowering))->into($node), $lowering->profile->grammar);
        }
        if ($node->name !== ($legacy ? 'explain_stmt' : 'opt_explain_options')) {
            return null;
        }
        $form = $lowering->form($node);
        if ($legacy && $form->signature !== 'explain_stmt: describe_command opt_explain_options INTO @ ident_or_text explainable_stmt') {
            return null;
        }
        [$format, $analyze, $target] = (new OptionRule($lowering))->options($legacy ? $form->node(1) : $node);
        if ((!$legacy && $target === null) || !(new ExplainFacts())->known($format)) {
            return null;
        }
        $target = $legacy ? $lowering->variables->user($form->node(4)) : $target;
        $invalid = $this->target($target, $lowering->profile->grammar);
        if ($invalid !== null) {
            return $invalid;
        }
        return ($legacy && $analyze) || strcasecmp($format->value ?? '', 'JSON') !== 0 ? new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto($format, $legacy && $analyze) : null;
    }

    /**
     * Checks names during input interpretation only in releases that validate them there.
     */
    public function target(?\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable $target, \SqlSemantics\Contract\GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget
    {
        if ($target === null || in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql810, \SqlSemantics\Contract\GrammarRelease::MySql820, \SqlSemantics\Contract\GrammarRelease::MySql830], true) || (new ExplainFacts())->target($target)) {
            return null;
        }
        return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget($target);
    }
}
