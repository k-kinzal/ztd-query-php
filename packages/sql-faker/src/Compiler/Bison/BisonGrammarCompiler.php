<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison;

use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Declaration\Symbols\SymbolClass;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\SymbolKind;
use BisonParser\Parser;
use BisonParser\SyntaxException;
use SqlFaker\Compiler\UnknownSymbolException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

/**
 * Turns a Bison grammar file into the grammar the generators walk.
 *
 * The file is read with bison-parser; this class keeps what generation
 * needs: every alternative of every rule as a sequence of terminals and
 * nonterminals. A name is a nonterminal when a rule defines it and a
 * terminal when `%token` declares it; a character literal is a terminal
 * named by its character; actions, precedence marks, tags, aliases and
 * string literals are left out. Alternatives of a rule defined in several
 * places are joined in file order.
 *
 * @visibility public
 *
 * @example Compiling a small grammar
 *     $grammar = (new \SqlFaker\Compiler\Bison\BisonGrammarCompiler())->compile("%token NUM\n%start expr\n%%\nexpr: expr '+' term { \$\$ = \$1 + \$3; } | term ;\nterm: NUM ;\n");
 *     [$grammar->startSymbol, array_keys($grammar->ruleMap), count($grammar->ruleMap['expr']->alternatives)] // => ['expr', ['expr', 'term'], 2]
 */
final class BisonGrammarCompiler
{
    /**
     * @param Parser $parser Reads Bison grammar files
     */
    public function __construct(private readonly Parser $parser = new Parser())
    {
    }

    /**
     * Compiles a grammar file.
     *
     * @param string $source The text of the `.y` or `.yy` file
     *
     * @return Grammar The grammar
     *
     * @throws SyntaxException When the file is not a grammar Bison accepts, which includes a file without rules
     * @throws UnknownSymbolException When a rule uses a name that is neither a rule nor a declared token
     */
    public function compile(string $source): Grammar
    {
        $file = $this->parser->parse($source);
        $rules = $file->rules();
        $defined = [];
        foreach ($rules as $rule) {
            $defined[$rule->name->value] = true;
        }
        $declared = $this->declaredTokens($file);
        $ruleMap = [];
        foreach ($rules as $rule) {
            $productions = [];
            foreach ($rule->alternatives as $alternative) {
                $productions[] = new Production($this->symbols($alternative, $defined, $declared));
            }
            $name = $rule->name->value;
            $previous = isset($ruleMap[$name]) ? $ruleMap[$name]->alternatives : [];
            $ruleMap[$name] = new ProductionRule($name, array_merge($previous, $productions));
        }

        return new Grammar($this->startSymbol($file), $ruleMap);
    }

    /**
     * Collects the names `%token` declares.
     *
     * @param GrammarFile $file The tree
     *
     * @return array<string, true> The names
     */
    public function declaredTokens(GrammarFile $file): array
    {
        $declared = [];
        foreach ($file->allDeclarations() as $declaration) {
            if (!$declaration instanceof SymbolDeclaration || $declaration->class !== SymbolClass::Token) {
                continue;
            }
            foreach ($declaration->entries as $entry) {
                if ($entry->symbol->isIdentifier()) {
                    $declared[$entry->symbol->value] = true;
                }
            }
        }

        return $declared;
    }

    /**
     * Turns the symbols of an alternative into terminals and nonterminals.
     *
     * @param Alternative $alternative The alternative
     * @param array<string, true> $defined Names rules define
     * @param array<string, true> $declared Names `%token` declares
     *
     * @return list<Symbol> The symbols in order, string literals left out
     *
     * @throws UnknownSymbolException When a name is neither defined nor declared
     */
    public function symbols(Alternative $alternative, array $defined, array $declared): array
    {
        $symbols = [];
        foreach ($alternative->symbols() as $symbol) {
            if ($symbol->kind === SymbolKind::CharLiteral) {
                $symbols[] = new Terminal($symbol->value);
            } elseif ($symbol->kind === SymbolKind::String) {
                continue;
            } elseif (isset($defined[$symbol->value])) {
                $symbols[] = new NonTerminal($symbol->value);
            } elseif (isset($declared[$symbol->value])) {
                $symbols[] = new Terminal($symbol->value);
            } else {
                throw new UnknownSymbolException($symbol->value);
            }
        }

        return $symbols;
    }

    /**
     * Finds the start symbol: what `%start` names, or else the first rule.
     *
     * @param GrammarFile $file The tree, with at least one rule
     *
     * @return string The start symbol
     */
    public function startSymbol(GrammarFile $file): string
    {
        foreach ($file->allDeclarations() as $declaration) {
            if ($declaration instanceof Start) {
                return $declaration->symbols[0]->value;
            }
        }

        return $file->rules()[0]->name->value;
    }
}
