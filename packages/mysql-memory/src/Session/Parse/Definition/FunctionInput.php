<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\Call\SyntaxCalls;

/**
 * Resolves implicit routine databases at completed legacy function calls.
 *
 * MySQL 5.6 reports an absent function without a default database, and 5.7
 * reports the missing database only after a complete statement is read. In 5.7,
 * an unfinished expression or an enclosing signature refusal comes first.
 * Only typed routine names leave semantic lowering.
 *
 * @visibility MySqlMemory
 */
final class FunctionInput
{
    /**
     * Answers an unresolved implicit function database before a later refusal.
     */
    public function before(string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->variables->database !== '' || !$session->settings()->legacy()) {
            return null;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            $completed = $this->completed($parser, $statement, $end);
            if ($completed === 0 && (new SyntaxCalls())->dependencies($parser, $statement, $end, $semantics->profile(), enclosing: true) !== []) {
                return QueryError::NoDatabase->error();
            }
            $end = $completed;
        }
        foreach ((new SyntaxCalls())->dependencies($parser, $statement, $end, $semantics->profile()) as [$offset, $name]) {
            return $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? ProgramError::RoutineMissing->error('FUNCTION', $name->value) : QueryError::NoDatabase->error();
        }
        return null;
    }
    /**
     * Answers the completed statement boundary before a later syntax failure.
     * Fully parsed input is contextualized by ordinary semantic lowering instead.
     */
    public function completed(ReductionParser $parser, string $statement, int $end): int
    {
        $completed = 0;
        try {
            $parser->parse($statement, static function (\SqlParser\Parser\Node $node, \SqlParser\Lexer\Token $lookahead) use ($end, &$completed): void {
                if ($node->name === 'statement' && $lookahead->offset <= $end) {
                    $completed = $lookahead->offset;
                }
            });
        } catch (\SqlParser\Lexer\SourceException) {
            return $completed;
        }
        return 0;
    }

}
