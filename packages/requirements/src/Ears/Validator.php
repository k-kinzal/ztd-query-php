<?php

declare(strict_types=1);

namespace Requirements\Ears;

use Requirements\Input\InvalidInputException;

/** Validates the clause grammar published at https://alistairmavin.com/ears/. */
final class Validator
{
    public function validate(string $statement): void
    {
        $text = $this->maskLiterals(trim($statement));
        $clauses = preg_split('/,\s*(?=(?:where|while|when|if|then\s+the|the)\b)/iu', $text);
        if ($clauses === false || $clauses === []) {
            throw new InvalidInputException('EARS: cannot read clauses.');
        }
        $main = array_pop($clauses);
        $rank = 0;
        $trigger = null;
        foreach ($clauses as $clause) {
            if (preg_match('/^(where|while|when|if)\s+(.+)$/isuD', trim($clause), $match) !== 1 || !$this->hasContent($match[2])) {
                throw new InvalidInputException('EARS: each condition needs Where, While, When or If and nonempty text.');
            }
            $keyword = strtolower($match[1]);
            $next = match ($keyword) {
                'where' => 1, 'while' => 2, default => 3
            };
            if ($next < $rank || ($next === 1 && $rank === 1) || ($next === 3 && $trigger !== null)) {
                throw new InvalidInputException('EARS: use optional feature, preconditions, then at most one trigger (When or If), in that order.');
            }
            if (preg_match('/\b(?:shall|then)\b/iu', $match[2]) === 1) {
                throw new InvalidInputException('EARS: shall belongs in the response; then must introduce the system clause after If.');
            }
            $rank = $next;
            if ($next === 3) {
                $trigger = $keyword;
            }
        }
        $prefix = $trigger === 'if' ? 'then\s+the' : 'the';
        if (preg_match('/^' . $prefix . '\s+(.+?)\s+shall\s+(.+)$/isuD', trim($main), $match) !== 1 || !$this->hasContent($match[1]) || !$this->hasContent($match[2])) {
            throw new InvalidInputException('EARS: expected ' . ($trigger === 'if' ? 'Then the' : 'The') . ' <system name> shall <system response>.');
        }
        if (preg_match('/\bshall\b/iu', $match[2]) === 1) {
            throw new InvalidInputException('EARS: use one system clause; combine responses after its shall.');
        }
    }

    private function hasContent(string $text): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', $text) === 1;
    }

    private function maskLiterals(string $text): string
    {
        $result = '';
        $quote = null;
        $length = strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $character = $text[$i];
            if ($quote !== null) {
                if ($character === '\\' && $i + 1 < $length) {
                    $result .= 'xx';
                    ++$i;
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                $result .= 'x';
                continue;
            }
            $apostrophe = $character === "'" && $i > 0 && ctype_alnum($text[$i - 1]);
            if (in_array($character, ['"', "'", '`'], true) && !$apostrophe) {
                $quote = $character;
                $result .= 'x';
            } else {
                $result .= $character;
            }
        }
        if ($quote !== null) {
            throw new InvalidInputException('EARS: close quoted or code literals.');
        }
        return $result;
    }
}
