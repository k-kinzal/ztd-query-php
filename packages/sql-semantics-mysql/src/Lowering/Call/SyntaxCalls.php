<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Interprets MySQL 5.6 native-call signature refusals at grammar completion.
 *
 * No SQL or syntax node escapes. Argument aliases and counts are read without
 * resolving argument values, preserving notices before an unfinished statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxCalls
{
    /**
     * Keeps a completed keyword's notice even when an argument is later refused.
     */
    public function deprecation(Node $node, \SqlSemantics\Contract\GrammarRelease $release): \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|null
    {
        $first = $node->children[0] ?? null;
        if (!$first instanceof \SqlParser\Lexer\Token) {
            return null;
        }
        if ($node->name === 'geometry_function' && $first->name === 'CONTAINS_SYM') {
            return \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::spatialWarning('CONTAINS', $release);
        }
        $construct = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::OldPassword;
        return in_array($node->name, ['function_call_conflict', 'text_or_password'], true) && $first->name === 'OLD_PASSWORD' && $construct->warnedIn($release) ? $construct : null;
    }

    /**
     * Answers a completed unqualified native call's refusal, if any.
     */
    public function completed(Node $node, Lowering $lowering): \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|ParseFailure|null
    {
        $notice = $this->deprecation($node, $lowering->profile->grammar);
        if ($notice !== null) {
            return $notice;
        }
        if ($node->name !== 'function_call_generic' || $lowering->profile->grammar !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return null;
        }
        $form = $lowering->form($node);
        if ($form->signature !== 'function_call_generic: IDENT_sys ( opt_udf_expr_list )') {
            return null;
        }
        $name = $lowering->names->identifier($form->node(0));
        $list = $lowering->form($form->node(2));
        $arguments = $list->signature === 'opt_udf_expr_list:' ? [] : (new Lists())->items($list->node(0));
        $named = false;
        foreach ($arguments as $argument) {
            $alias = $argument->children[count($argument->children) - 1] ?? null;
            $named = $named || ($alias instanceof Node && $alias->children !== []);
        }
        $problem = (new NativeFunctions())->problem($lowering->profile->grammar, $name, count($arguments), $named);

        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
