<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\WeightRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Preserves a completed WEIGHT_STRING operand refusal before later invalid input.
 * The domain check is the same SQL Semantics lowering rule used for complete input.
 * Parser nodes and SQL text remain at the input boundary.
 *
 * @visibility MySqlMemory
 */
final class WeightInput
{
    /**
     * Answers a completed cast-length refusal preceding the original syntax error.
     */
    public function before(string $statement, int $end, Session $session): ?SqlError
    {
        if (stripos($statement, 'WEIGHT_STRING') === false) {
            return null;
        }
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $profile = $session->semantics()->profile();
        $rule = new WeightRule(new Lowering((new Platform())->productions($profile), new Leaves(), $profile));
        $found = null;
        try {
            $parser->parse($statement, static function (Node $node, Token $lookahead) use ($end, $session, $statement, $rule, &$found): void {
                if ($found !== null || $lookahead->offset > $end || !in_array($node->name, ['ws_nweights', 'ws_num_codepoints'], true)) {
                    return;
                }
                try {
                    $rule->length($node);
                } catch (AnalysisException $failure) {
                    $found = (new Syntax(Charset::named((string) $session->variables->read('character_set_client'))))->error($failure, $statement, $session->settings()->release(), $session->following);
                } catch (RejectedInput) {
                    // Fractional-number refusals have their own ordered semantic notice.
                }
            });
        } catch (SourceException) {
            // The caller retains the original failure when no earlier operand refused it.
        }

        return $found;
    }
}
