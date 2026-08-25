<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

/**
 * The token substitutions PostgreSQL's parser frontend makes by looking ahead.
 *
 * A few words mean different things depending on what follows them: `NOT` before
 * `NULL` is not the `NOT` of an expression, `WITH` before `TIME` is not the
 * `WITH` of a CTE. PostgreSQL resolves this between its lexer and its parser by
 * rewriting the first token once the second is known.
 *
 * Generation and tokenizing both have to apply the same rewriting or they would
 * disagree about what the same text means, and realization has to be able to
 * walk it backwards to find the keyword a substituted token is spelled as.
 *
 * @phpstan-type LookaheadRule array{token: string, followed_by: list<string>}
 */
final class PgLookahead
{
    /**
     * @param array<string, LookaheadRule> $rules Substitution by the token that triggers it
     */
    public function __construct(private readonly array $rules)
    {
    }

    /**
     * Applies the substitutions to a token sequence.
     *
     * @param list<string> $tokens Tokens as read or as generated
     *
     * @return list<string> The sequence with each triggered substitution applied
     */
    public function applied(array $tokens): array
    {
        foreach ($tokens as $index => $token) {
            $rule = $this->rules[$token] ?? null;
            if ($rule !== null && in_array($tokens[$index + 1] ?? null, $rule['followed_by'], true)) {
                $tokens[$index] = $rule['token'];
            }
        }

        return $tokens;
    }

    /**
     * Settles each terminal on the spelling its neighbour calls for.
     *
     * Unlike applied(), this also walks a substitution backwards: a plan that
     * asked for the substituted token where the follower does not call for it
     * gets the base token, because that is what the text would read back as.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> The terminals with each substitution settled
     */
    public function normalized(array $terminals): array
    {
        foreach ($terminals as $index => $terminal) {
            foreach ($this->rules as $base => $rule) {
                if ($terminal !== $base && $terminal !== $rule['token']) {
                    continue;
                }

                $terminals[$index] = in_array($terminals[$index + 1] ?? null, $rule['followed_by'], true)
                    ? $rule['token']
                    : $base;
                break;
            }
        }

        return $terminals;
    }

    /**
     * Reports the token a substituted one is spelled as.
     *
     * A substituted token has no keyword of its own; it borrows the spelling of
     * the token it replaced, so realization has to ask which that was.
     *
     * @param string $terminal Terminal to look up
     *
     * @return string|null The token it substitutes for, or null when it substitutes for none
     */
    public function baseOf(string $terminal): ?string
    {
        foreach ($this->rules as $base => $rule) {
            if ($rule['token'] === $terminal) {
                return $base;
            }
        }

        return null;
    }
}
