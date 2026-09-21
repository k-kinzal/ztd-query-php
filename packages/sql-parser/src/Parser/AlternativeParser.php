<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ParseTable;

/**
 * Retries only unranked grammar conflicts after the preferred LALR derivation fails.
 *
 * Explicit precedence, associativity, nonassociative errors, and successful LALR
 * trees remain authoritative. Failed configurations are memoized across branches.
 *
 * @visibility root
 */
final class AlternativeParser
{
    /**
     * @param ParseTable $table Table carrying unresolved shift/reduce and reduce/reduce alternatives
     */
    public function __construct(public readonly ParseTable $table)
    {
    }

    /**
     * @param list<Token> $tokens Complete token stream
     * @return Node|null A complete grammar derivation, or null if all alternatives fail
     */
    public function parse(array $tokens): ?Node
    {
        $end = new Token(0, '$end', '', 0);
        $pending = [new ParseBranch()];
        $seen = [];
        while ($pending !== []) {
            $branch = array_pop($pending);
            while (true) {
                $state = $branch->states[count($branch->states) - 1];
                $token = $tokens[$branch->index] ?? $end;
                $key = serialize([$branch->index, $branch->states, $branch->forced]);
                if (isset($seen[$key])) {
                    break;
                }
                $seen[$key] = true;
                $action = $branch->forced ?? $this->table->action($state, $token->symbol);
                if ($branch->forced === null) {
                    foreach (array_reverse($this->table->alternatives[$state][$token->symbol] ?? []) as $alternative) {
                        $pending[] = new ParseBranch($branch->states, $branch->nodes, $branch->index, $alternative);
                    }
                }
                $branch->forced = null;
                if ($action === ActionCode::ERROR) {
                    break;
                }
                $result = $branch->advance($this->table, $action, $token);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }
}
