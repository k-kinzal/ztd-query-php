<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Keeps grammar conditions ahead of modern temporal-literal interpretation.
 *
 * Modern releases finish reading grammar operands before interpreting temporal
 * text. A grammar refusal is left to ordinary semantic analysis so its other
 * conditions are preserved. Otherwise all completed syntax warnings precede
 * the temporal error, including warnings written after that literal.
 * MySQL 5.6 interprets literals during parsing; 5.7 still reports them before
 * contextual collation lookup, but after completed CAST precision checks.
 * Observed on native MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class TemporalInput
{
    /**
     * Checks literal text without publishing the input tree or source text.
     *
     * @throws SqlError When the first temporal refusal follows successful grammar interpretation
     */
    public function check(Node $tree, string $statement, int $end, Session $session): void
    {
        try {
            (new Syntax(Charset::named((string) $session->variables->read('character_set_client'))))->temporals($tree, $session->modes(), $session, $statement, $end);
        } catch (SqlError $failure) {
            $parser = $session->semantics()->parser();
            Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
            $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile());
            foreach ($notices as [, $notice]) {
                if ($notice instanceof ParseFailure && !$session->settings()->legacy()) {
                    return;
                }
            }
            if ($session->settings()->legacy()) {
                $literal = $session->settings()->release() === GrammarRelease::MySql5651 ? $this->before($statement, $end, $session) : null;
                foreach ($notices as [$offset, $notice]) {
                    if ($notice instanceof ParseFailure && $notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision && ($literal === null || $offset < $literal[0])) {
                        return;
                    }
                }
                throw $failure;
            }
            foreach ((new ModernWarnings())->before($parser->tokenize($statement), $statement, $end, $parser) as $offset => $notice) {
                $notices[] = [$offset, $notice];
            }
            usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

            throw (new ModernWarnings())->recorded(array_column($notices, 1), $failure, $session);
        }
    }

    /**
     * Finds a 5.6 literal refusal completed before a later syntax failure.
     *
     * @return array{int, SqlError}|null The literal start and its value error
     */
    public function before(string $statement, int $end, Session $session): ?array
    {
        if ($session->settings()->release() !== GrammarRelease::MySql5651) {
            return null;
        }
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $found = null;
        try {
            $parser->parse($statement, static function (Node $node, \SqlParser\Lexer\Token $lookahead) use ($end, $session, &$found): void {
                if ($found !== null || $node->name !== 'temporal_literal' || $lookahead->offset > $end) {
                    return;
                }
                try {
                    (new Syntax(Charset::named((string) $session->variables->read('character_set_client'))))->temporals($node, $session->modes());
                } catch (SqlError $failure) {
                    $found = [$node->tokens()[0]->offset, $failure];
                }
            });
        } catch (\SqlParser\Lexer\SourceException) {
            // The caller keeps the original syntax refusal if no earlier literal failed.
        }

        return $found;
    }
}
