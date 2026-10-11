<?php

declare(strict_types=1);

namespace Fuzz\Target;

use SqlFaker\Generation\Coverage\GrammarCoverageInventory;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\Node;

/**
 * Measures the grammar alternatives actually present in a generated statement's concrete parse tree.
 *
 * Rewrites may introduce syntax with no surviving derivation provenance. Conversely, a chosen
 * keyword can tokenize as an identifier in context. Match the rule, original ordinal and complete
 * terminal/nonterminal right-hand side before crediting an inventory entry.
 */
final class ParsedCoverage
{
    /**
     * @var array<string, array<int, array<string, string>>> Inventory IDs by rule, ordinal and serialized RHS
     */
    private array $alternatives = [];

    /**
     * Indexes the selected grammar without changing its coverage denominator.
     *
     * @param MySqlParser $parser The parser for the same MySQL release and fixture lexical modes
     * @param GrammarCoverageInventory $inventory The generator's complete alternative inventory
     */
    public function __construct(public readonly MySqlParser $parser, GrammarCoverageInventory $inventory)
    {
        foreach ($inventory->entries as $id => $entry) {
            $this->alternatives[$entry['rule']][$entry['ordinal']][serialize($entry['rhs'])] = $id;
        }
    }

    /**
     * @return list<string>
     * @throws \SqlParser\Lexer\LexicalException When generated SQL cannot be tokenized
     * @throws \SqlParser\Parser\SyntaxException When generated SQL cannot be parsed
     */
    public function ids(string $sql): array
    {
        $pending = [$this->parser->parse($sql)];
        $ids = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            $rhs = array_map(static fn ($child): string => ($child instanceof Node ? 'N:' : 'T:') . $child->name, $node->children);
            $id = $this->alternatives[$node->name][$node->ordinal][serialize($rhs)] ?? null;
            if ($id !== null) {
                $ids[$id] = true;
            }
            foreach ($node->children as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return array_keys($ids);
    }
}
