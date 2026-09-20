<?php

declare(strict_types=1);

namespace SqlParser\Compiler;

use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\Declaration\Expect;
use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Declaration\Symbols\Associativity as BisonAssociativity;
use BisonParser\Ast\Declaration\Symbols\PrecedenceDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolClass;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Predicate;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Parser;
use BisonParser\SyntaxException;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\UnknownSymbolException;

/**
 * Turns a Bison grammar file into the grammar the automaton is built from.
 *
 * The file is read with bison-parser; this class keeps what the automaton
 * needs. `%token` names terminals, `%left`, `%right`, `%nonassoc` and
 * `%precedence` rank them, `%start` and `%expect` are recorded, and every
 * alternative becomes a rule. A mid-rule action becomes a hidden `$@n`
 * nonterminal deriving the empty string, numbered before the rule it
 * appears in, exactly as Bison numbers it. Character literals and strings
 * on a right-hand side are terminals named by their value.
 *
 * @visibility public
 *
 * @example Reading a small grammar
 *     $grammar = (new \SqlParser\Compiler\BisonGrammarReader())->read("%token NUM\n%left '+'\n%%\nexpr: expr '+' expr | NUM ;\n");
 *     [count($grammar->rules), $grammar->symbols->name($grammar->startSymbol())] // => [3, "expr"]
 * @example Numbering a mid-rule action as Bison does
 *     $grammar = (new \SqlParser\Compiler\BisonGrammarReader())->read("%token A B\n%%\ns: A { act(); } B ;\n");
 *     array_map(static fn (\SqlParser\Grammar\Rule $rule): string => $grammar->symbols->name($rule->lhs), $grammar->rules) // => ['$accept', '$@1', 's']
 */
final class BisonGrammarReader
{
    private int $midRuleCount = 0;

    /**
     * @param Parser $parser Reads Bison grammar files
     */
    public function __construct(private readonly Parser $parser = new Parser())
    {
    }

    /**
     * Reads a grammar file.
     *
     * @param string $source The text of the `.y` or `.yy` file
     *
     * @return Grammar The grammar with Bison's precedence policy
     *
     * @throws SyntaxException When the file is not a grammar Bison accepts
     * @throws GrammarException When the grammar is inconsistent
     * @throws UnknownSymbolException When a rule uses an undeclared symbol
     */
    public function read(string $source): Grammar
    {
        $file = $this->parser->parse($source);
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::LastTerminal);
        $builder->terminal('error');
        foreach ($file->allDeclarations() as $declaration) {
            $this->declaration($declaration, $builder);
        }
        $this->midRuleCount = 0;
        foreach ($file->rules() as $rule) {
            foreach ($rule->alternatives as $alternative) {
                $this->alternative($rule->name->value, $alternative, $builder);
            }
        }

        return $builder->build();
    }

    /**
     * Records what a declaration says about symbols, precedence, the start symbol or expected conflicts.
     *
     * @param Declaration $declaration The declaration
     * @param GrammarBuilder $builder Collects the grammar
     */
    public function declaration(Declaration $declaration, GrammarBuilder $builder): void
    {
        if ($declaration instanceof PrecedenceDeclaration) {
            $names = [];
            foreach ($declaration->entries as $entry) {
                $names[] = $entry->symbol->value;
            }
            $builder->precedence($names, $this->associativity($declaration->associativity));
        } elseif ($declaration instanceof SymbolDeclaration && $declaration->class === SymbolClass::Token) {
            foreach ($declaration->entries as $entry) {
                $builder->terminal($entry->symbol->value);
            }
        } elseif ($declaration instanceof Start) {
            $builder->start($declaration->symbols[0]->value);
        } elseif ($declaration instanceof Expect && !$declaration->reduceReduce) {
            $builder->expect($declaration->count);
        }
    }

    /**
     * Maps Bison's associativity onto the grammar's.
     *
     * @param BisonAssociativity $associativity As declared
     *
     * @return Associativity As the automaton resolves conflicts
     */
    public function associativity(BisonAssociativity $associativity): Associativity
    {
        return match ($associativity) {
            BisonAssociativity::Left => Associativity::Left,
            BisonAssociativity::Right => Associativity::Right,
            BisonAssociativity::NonAssoc => Associativity::NonAssoc,
            BisonAssociativity::Precedence => Associativity::Precedence,
        };
    }

    /**
     * Adds one alternative as a rule, with hidden rules for its mid-rule actions.
     *
     * @param string $lhs The nonterminal being defined
     * @param Alternative $alternative The alternative
     * @param GrammarBuilder $builder Collects the grammar
     */
    public function alternative(string $lhs, Alternative $alternative, GrammarBuilder $builder): void
    {
        $symbols = [];
        $precedence = null;
        foreach ($alternative->items as $index => $item) {
            if ($item instanceof SymbolItem) {
                if (!$item->symbol->isIdentifier()) {
                    $builder->terminal($item->symbol->value);
                }
                $symbols[] = $item->symbol->value;
            } elseif ($item instanceof Action && $this->continues($alternative, $index)) {
                $symbols[] = $this->midRule($builder);
            } elseif ($item instanceof PrecItem) {
                $precedence = $item->symbol->value;
            }
        }
        $builder->rule($lhs, $symbols, $precedence);
    }

    /**
     * Reports whether a symbol, action or predicate follows an item, which makes an action there a mid-rule action.
     *
     * @param Alternative $alternative The alternative
     * @param int $index The item's index
     *
     * @return bool True when the alternative continues after the item
     */
    public function continues(Alternative $alternative, int $index): bool
    {
        foreach (array_slice($alternative->items, $index + 1) as $item) {
            if ($item instanceof SymbolItem || $item instanceof Action || $item instanceof Predicate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds the hidden empty rule that stands for a mid-rule action.
     *
     * @param GrammarBuilder $builder Collects the grammar
     *
     * @return string The `$@n` nonterminal
     */
    public function midRule(GrammarBuilder $builder): string
    {
        $name = '$@' . ++$this->midRuleCount;
        $builder->rule($name, [], null, true);

        return $name;
    }
}
