<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\Derivation;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminalInventory;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\PostgreSql\Grammar\PgGrammar;

/**
 * Derives PostgreSQL parser terminals and realizes them through the versioned lexer model.
 */
final class SqlGenerator
{
    private const LEXICAL_ATTEMPT_LIMIT = 32;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private LexicalGrammar $lexicalGrammar;
    private TerminationAnalyzer $terminationAnalyzer;

    public function __construct(
        Grammar $grammar,
        FakerGenerator $faker,
        ?string $version = null,
    ) {
        $this->grammar = $grammar;
        $this->faker = $faker;
        $this->lexicalGrammar = new LexicalGrammar(
            $faker,
            PgGrammar::resolveVersion($version),
            $version === null,
        );
        if ($version !== null) {
            $this->lexicalGrammar->assertTerminalsCovered(TerminalInventory::fromGrammar($this->grammar));
        }
        $this->terminationAnalyzer = new TerminationAnalyzer($grammar, $this->lexicalGrammar->supports(...));
    }

    /**
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
    public function generate(GenerationPlan $plan): string
    {
        if ($plan->lexicalTarget() !== null) {
            return $this->lexicalGrammar->generate($plan);
        }
        $lastException = null;
        for ($attempt = 0; $attempt < self::LEXICAL_ATTEMPT_LIMIT; $attempt++) {
            $terminals = (new Derivation($this->grammar, $this->faker, $this->terminationAnalyzer))
                ->of($plan->startRule() ?? 'stmtmulti', $plan);
            $terminalNames = $this->normalizeParserSemantics(array_map(
                static fn (Terminal $terminal): string => $terminal->value,
                $terminals,
            ));
            try {
                $sql = $this->lexicalGrammar->realize($terminalNames, $plan);
                if ($sql !== '' || !$plan->requiresNonEmpty()) {
                    return $sql;
                }
                $lastException = GenerationException::planRequiresNonEmptyOutput('PostgreSQL');
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? GenerationException::lexicalRealizationFailed('PostgreSQL');
    }

    /**
     * Applies constraints enforced by parser semantic actions rather than the lexer or Bison grammar.
     *
     * @param list<string> $terminals
     * @return list<string>
     */
    private function normalizeParserSemantics(array $terminals): array
    {
        $terminals = $this->truncateQualifiedNames($terminals);

        foreach ($terminals as $index => $terminal) {
            if ($terminal !== 'SET' || ($terminals[$index + 1] ?? null) !== '(') {
                continue;
            }
            $end = $this->matchingParen($terminals, $index + 1);
            if ($end === null) {
                continue;
            }
            $depth = 1;
            for ($cursor = $index + 2; $cursor < $end; $cursor++) {
                if ($terminals[$cursor] === '(') {
                    $depth++;
                } elseif ($terminals[$cursor] === ')') {
                    $depth--;
                }
                if ($depth === 1
                    && $this->isIdentifierTerminal($terminals[$cursor])
                    && in_array($terminals[$cursor + 1] ?? null, [',', ')'], true)
                    && ($terminals[$cursor - 1] ?? null) !== '='
                ) {
                    array_splice($terminals, $cursor + 1, 0, ['=', 'NONE']);
                    $end += 2;
                    $cursor += 2;
                }
            }
        }

        foreach ($terminals as $index => $terminal) {
            if ($terminal !== 'OPERATOR' || ($terminals[$index + 1] ?? null) === '(') {
                continue;
            }
            $start = array_search('(', array_slice($terminals, $index + 1), true);
            if ($start === false) {
                continue;
            }
            $start += $index + 1;
            $end = $this->matchingParen($terminals, $start);
            if ($end !== null && $end - $start === 2 && $terminals[$start + 1] !== ',') {
                array_splice($terminals, $start + 1, 0, ['NONE', ',']);
            }
        }

        return $this->lexicalGrammar->normalizeLookahead($terminals);
    }

    /**
     * @param list<string> $terminals
     * @return list<string>
     */
    private function truncateQualifiedNames(array $terminals): array
    {
        $result = [];
        $count = count($terminals);
        for ($index = 0; $index < $count; $index++) {
            $terminal = $terminals[$index];
            if (!$this->isIdentifierTerminal($terminal) || ($terminals[$index + 1] ?? null) !== '.') {
                $result[] = $terminal;
                continue;
            }

            $chain = [$terminal];
            while ($index + 2 < $count && $terminals[$index + 1] === '.') {
                $following = $terminals[$index + 2];
                if ($following === '*') {
                    $index += 2;
                    break;
                }
                if (!$this->isIdentifierTerminal($following)) {
                    break;
                }
                $chain[] = '.';
                $chain[] = $following;
                $index += 2;
            }
            array_push($result, ...array_slice($chain, 0, 5));
        }

        return $result;
    }

    /**
     * @param list<string> $terminals
     */
    private function matchingParen(array $terminals, int $open): ?int
    {
        $depth = 0;
        for ($index = $open; $index < count($terminals); $index++) {
            if ($terminals[$index] === '(') {
                $depth++;
            } elseif ($terminals[$index] === ')' && --$depth === 0) {
                return $index;
            }
        }

        return null;
    }

    private function isIdentifierTerminal(string $terminal): bool
    {
        return in_array($terminal, ['IDENT', 'UIDENT'], true)
            || preg_match('/^[a-z_][a-z0-9_]*$/', $terminal) === 1;
    }

}
