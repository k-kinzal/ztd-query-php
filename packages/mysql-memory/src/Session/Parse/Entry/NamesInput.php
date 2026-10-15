<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use RuntimeException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment;

/**
 * Formats the input-only refusal of NAMES = expression from typed positions.
 *
 * @visibility MySqlMemory
 */
final class NamesInput
{
    /**
     * Preserves the assignment operator or the legacy unread tail in the displayed excerpt.
     */
    public function error(RefusedNamesAssignment $problem, Session $session): SqlError
    {
        if ($problem->shadowed) {
            return ProgramError::VariableShadow->error('names');
        }
        $offset = $problem->offset;
        if ($session->settings()->release() === GrammarRelease::MySql5651) {
            $offset = strlen($session->text);
            try {
                $tokens = $session->semantics()->parser()->tokenize($session->text);
            } catch (\SqlParser\Lexer\SourceException $failure) {
                $tokens = $session->semantics()->parser()->tokenize(substr($session->text, 0, $failure->offset));
            }
            foreach ($tokens as $token) {
                if ($token->offset >= $problem->end) {
                    $offset = $token->name === ',' ? $token->end() : $token->offset;
                    break;
                }
            }
        }

        return (new Syntax())->near($offset, $session->text, $session->following, new RuntimeException($problem->message()));
    }
}
