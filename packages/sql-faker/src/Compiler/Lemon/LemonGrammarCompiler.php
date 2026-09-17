<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Lemon;

use LemonParser\Ast\Declaration\Fallback;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Declaration\TokenClass;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Declaration\Wildcard;
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Rule;
use LemonParser\Parser;
use LemonParser\SyntaxException;
use SqlFaker\Compiler\GrammarParseException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

/**
 * Turns a Lemon grammar file into the grammar the generators walk.
 *
 * The file is read with lemon-parser after its `%ifdef` regions are
 * settled; this class keeps what generation needs. A position shared by
 * several terminals, `A|B`, is spelled out into one alternative per
 * terminal, a `%token_class` becomes a rule with one alternative per
 * member, and the first rule is the start symbol. A name written in
 * capitals is a terminal unless a rule defines it; other names are
 * nonterminals unless `%token`, a precedence declaration, `%fallback`, a
 * token class or `%wildcard` declares them.
 *
 * @visibility public
 *
 * @example Compiling a small grammar
 *     $grammar = (new \SqlFaker\Compiler\Lemon\LemonGrammarCompiler())->compile("%token_class id ID|INDEXED.\nexpr(A) ::= expr(B) PLUS|MINUS term(C). { A = B + C; }\nexpr ::= term.\nterm ::= NUM.\nterm ::= id.\n");
 *     [$grammar->startSymbol, array_keys($grammar->ruleMap), count($grammar->ruleMap['expr']->alternatives)] // => ['expr', ['expr', 'term', 'id'], 3]
 */
final class LemonGrammarCompiler
{
    /**
     * @param Parser $parser Reads Lemon grammar files
     */
    public function __construct(private readonly Parser $parser = new Parser())
    {
    }

    /**
     * Compiles a grammar file.
     *
     * @param string $source The text of the `.y` file
     * @param list<string> $defines Names defined for `%ifdef`, as Lemon's `-D` option defines them
     *
     * @return Grammar The grammar
     *
     * @throws SyntaxException When the file is not a grammar Lemon accepts
     * @throws GrammarParseException When the file defines no rule
     */
    public function compile(string $source, array $defines = []): Grammar
    {
        $file = $this->parser->parse($source, $defines);
        $rules = $file->rules();
        if ($rules === []) {
            throw GrammarParseException::noRulesParsed('Lemon');
        }
        $terminals = $this->terminals($file);
        $grouped = [];
        foreach ($rules as $rule) {
            $name = $rule->lhs->name;
            $grouped[$name] = array_merge($grouped[$name] ?? [], $this->expand($rule));
        }
        $ruleMap = [];
        foreach ($grouped as $name => $alternatives) {
            $productions = [];
            foreach ($alternatives as $names) {
                $productions[] = new Production(array_map(static fn (string $symbol): Symbol => isset($terminals[$symbol]) ? new Terminal($symbol) : new NonTerminal($symbol), $names));
            }
            $ruleMap[$name] = new ProductionRule($name, $productions);
        }
        foreach ($file->declarations() as $declaration) {
            if ($declaration instanceof TokenClass) {
                $ruleMap[$declaration->name->name] = new ProductionRule($declaration->name->name, array_map(static fn ($token): Production => new Production([new Terminal($token->name)]), $declaration->tokens));
            }
        }

        return new Grammar($rules[0]->lhs->name, $ruleMap);
    }

    /**
     * Spells out a rule's shared positions into one sequence of names per combination.
     *
     * @param Rule $rule The rule
     *
     * @return list<list<string>> The sequences, the first terminal of each position first
     */
    public function expand(Rule $rule): array
    {
        $sequences = [[]];
        foreach ($rule->items as $item) {
            $expanded = [];
            foreach ($sequences as $sequence) {
                foreach ($item->symbols as $symbol) {
                    $expanded[] = [...$sequence, $symbol->name];
                }
            }
            $sequences = $expanded;
        }

        return $sequences;
    }

    /**
     * Decides which names are terminals.
     *
     * @param GrammarFile $file The tree
     *
     * @return array<string, true> The terminal names
     */
    public function terminals(GrammarFile $file): array
    {
        $tokens = [];
        $rules = [];
        foreach ($file->declarations() as $declaration) {
            if ($declaration instanceof PrecedenceDeclaration || $declaration instanceof TokenDeclaration || $declaration instanceof Fallback) {
                $this->declareTokens($tokens, $declaration->symbols);
            } elseif ($declaration instanceof TokenClass) {
                $rules[$declaration->name->name] = true;
                $this->declareTokens($tokens, $declaration->tokens);
            } elseif ($declaration instanceof Wildcard && $declaration->symbol !== null && !isset($tokens[$declaration->symbol->name])) {
                $tokens[$declaration->symbol->name] = true;
            }
        }
        foreach ($file->rules() as $rule) {
            $rules[$rule->lhs->name] = true;
            foreach ($rule->symbols() as $symbol) {
                if (self::isTokenName($symbol->name)) {
                    $tokens[$symbol->name] = true;
                } else {
                    $rules[$symbol->name] = true;
                }
            }
        }
        $terminals = $tokens;
        foreach ($file->rules() as $rule) {
            foreach ($rule->symbols() as $symbol) {
                if (!isset($tokens[$symbol->name]) && !isset($rules[$symbol->name]) && self::isTokenName($symbol->name)) {
                    $terminals[$symbol->name] = true;
                }
            }
        }

        return $terminals;
    }

    /**
     * Records the names of a declaration that are spelled as tokens.
     *
     * @param array<string, true> $tokens The tokens so far, added to
     * @param list<\LemonParser\Ast\Symbol> $symbols The declared symbols
     */
    public function declareTokens(array &$tokens, array $symbols): void
    {
        foreach ($symbols as $symbol) {
            if (self::isTokenName($symbol->name)) {
                $tokens[$symbol->name] = true;
            }
        }
    }

    /**
     * Reports whether a name is spelled as a token: capitals, digits and underscores, starting with a capital.
     *
     * @param string $name The name
     *
     * @return bool True for a token spelling
     */
    public static function isTokenName(string $name): bool
    {
        return preg_match('/^[A-Z][A-Z0-9_]*$/', $name) === 1;
    }
}
