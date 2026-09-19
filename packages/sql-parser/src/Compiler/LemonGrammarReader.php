<?php

declare(strict_types=1);

namespace SqlParser\Compiler;

use LemonParser\Ast\Declaration\Associativity as LemonAssociativity;
use LemonParser\Ast\Declaration\Declaration;
use LemonParser\Ast\Declaration\Directive;
use LemonParser\Ast\Declaration\DirectiveKeyword;
use LemonParser\Ast\Declaration\Fallback;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Declaration\TokenClass;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Declaration\Wildcard;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Parser;
use LemonParser\SyntaxException;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\UnknownSymbolException;

/**
 * Turns a Lemon grammar file into the grammar the automaton is built from.
 *
 * The file is read with lemon-parser after its `%ifdef` regions are settled
 * with the names given; this class keeps what the automaton needs. `%token`
 * names terminals, `%left`, `%right` and `%nonassoc` rank them,
 * `%token_class` declarations and `A|B` positions become token classes,
 * `%fallback`, `%wildcard` and `%start_symbol` are recorded for the parser,
 * a `[PREC]` mark names a rule's precedence, and any upper-case name on a
 * right-hand side is a terminal.
 *
 * @visibility public
 *
 * @example Reading a small grammar
 *     $grammar = (new \SqlParser\Compiler\LemonGrammarReader())->read("%left PLUS.\nexpr ::= expr PLUS|MINUS expr.\nexpr ::= NUM.\n");
 *     [count($grammar->rules), $grammar->symbols->name($grammar->startSymbol())] // => [3, "expr"]
 * @example Settling a conditional region
 *     $source = "%ifndef OMIT\ncmd ::= EXTRA.\n%endif\ncmd ::= SELECT.\n";
 *     [count((new \SqlParser\Compiler\LemonGrammarReader())->read($source)->rules), count((new \SqlParser\Compiler\LemonGrammarReader())->read($source, ['OMIT'])->rules)] // => [3, 2]
 */
final class LemonGrammarReader
{
    /**
     * @param Parser $parser Reads Lemon grammar files
     */
    public function __construct(private readonly Parser $parser = new Parser())
    {
    }

    /**
     * Reads a grammar file.
     *
     * @param string $source The text of the `.y` file
     * @param list<string> $defines Names defined for `%ifdef`, as Lemon's `-D` option defines them
     *
     * @return Grammar The grammar with Lemon's precedence policy
     *
     * @throws SyntaxException When the file is not a grammar Lemon accepts
     * @throws GrammarException When the grammar is inconsistent
     * @throws UnknownSymbolException When a declaration names an unknown symbol
     */
    public function read(string $source, array $defines = []): Grammar
    {
        $file = $this->parser->parse($source, $defines);
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::FirstRankedTerminal);
        foreach ($file->items as $item) {
            if ($item instanceof Rule) {
                $this->rule($item, $builder);
            } else {
                $this->declaration($item, $builder);
            }
        }

        return $builder->build();
    }

    /**
     * Records what a declaration says about terminals, precedence, token classes, fallbacks, the wildcard or the start symbol.
     *
     * @param Declaration $declaration The declaration
     * @param GrammarBuilder $builder Collects the grammar
     */
    public function declaration(Declaration $declaration, GrammarBuilder $builder): void
    {
        if ($declaration instanceof PrecedenceDeclaration) {
            $builder->precedence($this->names($declaration->symbols), $this->associativity($declaration->associativity));
        } elseif ($declaration instanceof TokenDeclaration) {
            foreach ($declaration->symbols as $symbol) {
                $builder->terminal($symbol->name);
            }
        } elseif ($declaration instanceof Fallback && $declaration->fallback() !== null) {
            $builder->fallback($declaration->fallback()->name, $this->names($declaration->tokens()));
        } elseif ($declaration instanceof Wildcard && $declaration->symbol !== null) {
            $builder->wildcard($declaration->symbol->name);
        } elseif ($declaration instanceof TokenClass) {
            $builder->tokenClass($declaration->name->name, $this->names($declaration->tokens));
        } elseif ($declaration instanceof Directive && $declaration->keyword === DirectiveKeyword::StartSymbol) {
            $builder->start($declaration->value);
        }
    }

    /**
     * Maps Lemon's associativity onto the grammar's.
     *
     * @param LemonAssociativity $associativity As declared
     *
     * @return Associativity As the automaton resolves conflicts
     */
    public function associativity(LemonAssociativity $associativity): Associativity
    {
        return match ($associativity) {
            LemonAssociativity::Left => Associativity::Left,
            LemonAssociativity::Right => Associativity::Right,
            LemonAssociativity::NonAssoc => Associativity::NonAssoc,
        };
    }

    /**
     * Adds a rule, turning a position shared by several terminals into a token class.
     *
     * @param Rule $rule The rule
     * @param GrammarBuilder $builder Collects the grammar
     */
    public function rule(Rule $rule, GrammarBuilder $builder): void
    {
        $symbols = [];
        foreach ($rule->items as $item) {
            $members = $this->names($item->symbols);
            $name = implode('|', $members);
            if ($item->isMultiTerminal() && !$builder->isTerminal($name)) {
                $builder->tokenClass($name, $members);
            } elseif (!$item->isMultiTerminal() && $item->symbols[0]->isTerminal() && !$builder->isTerminal($name)) {
                $builder->terminal($name);
            }
            $symbols[] = $name;
        }
        $builder->rule($rule->lhs->name, $symbols, $rule->precedence?->name);
    }

    /**
     * Lists the names of symbols.
     *
     * @param list<Symbol> $symbols The symbols
     *
     * @return list<string> Their names in order
     */
    public function names(array $symbols): array
    {
        return array_map(static fn (Symbol $symbol): string => $symbol->name, $symbols);
    }
}
