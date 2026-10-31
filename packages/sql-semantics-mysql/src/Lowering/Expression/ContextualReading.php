<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Call\FunctionRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

/**
 * Decodes temporal and system-variable leaves in their contextualization order.
 *
 * Only typed values and input ranges escape. MySQL 5.6 reads these leaves in
 * written order; later releases visit TRIM's subject and TIMESTAMPADD's date
 * before the other operand. Grammar diagnostics remain a separate earlier phase.
 *
 * @visibility public
 * @example Reading a typed temporal operand without retaining its source tree
 *     $platform = new \SqlSemantics\Platform\MySql\Platform();
 *     $profile = $platform->profile('8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native);
 *     $tree = $platform->parser($profile)->parse("SELECT DATE '2026-10-20'");
 *     (new \SqlSemantics\Platform\MySql\Lowering\Expression\ContextualReading())->read($tree, $profile)[0][2]->text // => '2026-10-20'
 */
final class ContextualReading
{
    /**
     * @return list<array{int, int, TemporalLiteral|SystemVariable|UserVariable, bool}> Start, end, decoded value and view-body context; no retained source tree
     * @throws \SqlSemantics\Construction\RejectedInput When a completed operand is one the server refuses while reading
     */
    public function read(Node $tree, LanguageProfile $profile, ?Charset $client = null): array
    {
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new Leaves(), $profile, client: $client);
        $lowering->parameters($tree->tokens());
        $pending = [new ReadingVisit($tree)];
        $values = [];
        while ($pending !== []) {
            $visit = array_pop($pending);
            $node = $visit->node;
            $after = $visit->after;
            $view = $visit->view || $node->name === 'view_tail';
            $value = $this->value($node, $lowering);
            $assignment = $value instanceof UserVariable && count($node->children) > 2 && $profile->grammar !== GrammarRelease::MySql5651;
            if ($assignment && !$after) {
                $pending[] = new ReadingVisit($node, $view, true);
            }
            if ($value !== null && (!$assignment || $after)) {
                $range = (new SourceRanges())->span($node);
                if ($range !== null) {
                    $values[] = [$range[0], $range[1], $value, $view];
                }
                if (!$value instanceof UserVariable || $after) {
                    continue;
                }
            }
            foreach (array_reverse($this->children($node, $lowering)) as $child) {
                $pending[] = new ReadingVisit($child, $view);
            }
        }
        return $values;
    }

    /**
     * Decodes a complete leaf, retaining invalid temporal operands.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When a system variable is unknown to the release
     */
    public function value(Node $node, Lowering $lowering): TemporalLiteral|SystemVariable|UserVariable|null
    {
        if ($node->name === 'temporal_literal') {
            return $lowering->literals->temporal($lowering->form($node));
        }
        if (in_array($node->name, ['variable_aux', 'rvalue_system_or_user_variable', 'in_expression_user_variable_assignment'], true)) {
            $form = $lowering->form($node);
            $user = match ($form->signature) {
                'variable_aux: ident_or_text', 'variable_aux: ident_or_text SET_VAR expr' => 0,
                'rvalue_system_or_user_variable: @ ident_or_text', 'in_expression_user_variable_assignment: @ ident_or_text SET_VAR expr' => 1,
                default => null,
            };
            if ($user !== null) {
                return $lowering->variables->user($form->node($user));
            }
            $value = $lowering->variables->variable($node);
            assert($value instanceof SystemVariable);
            return $value;
        }
        return null;
    }

    /**
     * @return list<Node> Transient child nodes in the order their values are interpreted
     */
    public function children(Node $node, Lowering $lowering): array
    {
        $children = array_values(array_filter($node->children, static fn ($child): bool => $child instanceof Node));
        if ($lowering->profile->grammar === GrammarRelease::MySql5651 || !in_array($node->name, ['function_call_keyword', 'function_call_nonkeyword'], true)) {
            return $children;
        }
        $form = $lowering->form($node);
        $order = (new FunctionRule($lowering))->trimOrder($form);
        if ($form->signature === 'function_call_nonkeyword: TIMESTAMP_ADD ( interval_time_stamp , expr , expr )') {
            $order = [6, 4];
        }
        return $order === null ? $children : array_map($form->node(...), $order);
    }
}
