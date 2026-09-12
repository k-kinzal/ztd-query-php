<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Lemon;

use InvalidArgumentException;
use RuntimeException;
use SqlFaker\Compiler\GrammarParseException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

/**
 * Reads Lemon source declarations and production rules into a grammar.
 *
 * Lemon writes one alternative per line — `lhs(alias) ::= symbol ... .` — so
 * the alternatives of a rule are gathered by name rather than grouped in the
 * file. It declares its tokens in directives, spells them in capitals, and
 * carries the C its parser is built from alongside the rules. Reading the
 * declarations, reading the rules, and remembering what each name turned out
 * to be are three subjects, and this brings their answers together.
 *
 * @visibility root
 */
final class LemonParser
{
    /**
     * @param LemonText $text Removes what is not grammar
     * @param LemonDirectives $directives Reads the tokens the grammar declares
     * @param LemonRules $rules Reads the rules the grammar writes
     */
    public function __construct(
        private readonly LemonText $text = new LemonText(),
        private readonly LemonDirectives $directives = new LemonDirectives(),
        private readonly LemonRules $rules = new LemonRules(),
    ) {
    }

    /**
     * Reads a Lemon grammar as a grammar.
     *
     * The first rule written is the start symbol, which is what Lemon itself
     * assumes when no other is named.
     *
     * @param string $input Contents of the grammar file
     * @param list<string> $defines Named parser-build definitions
     *
     * @return Grammar Grammar the file describes
     *
     * @throws RuntimeException When source conditionals are malformed
     * @throws GrammarParseException When the file writes no rules
     * @throws InvalidArgumentException When a rule is filed under a name other than its own left-hand side
     */
    public function parse(string $input, array $defines = []): Grammar
    {
        $symbols = new LemonSymbols();
        $input = (new LemonPreprocessor(new LemonCondition($defines)))->process($this->text->withoutComments($input));
        $this->directives->declareInto($input, $symbols);
        $rules = $this->rules->readFrom($input, $symbols);
        $classes = $this->directives->tokenClasses($input);
        if ($rules === []) {
            throw GrammarParseException::noRulesParsed('Lemon');
        }

        /** @var string $startSymbol */
        $startSymbol = array_key_first($rules);

        /** @var array<string, ProductionRule> $ruleMap */
        $ruleMap = [];
        foreach ($rules as $lhs => $alternatives) {
            /** @var list<Production> $productions */
            $productions = [];
            foreach ($alternatives as $symbolNames) {
                /** @var list<Symbol> $written */
                $written = [];
                foreach ($symbolNames as $name) {
                    $written[] = $symbols->isTerminal($name) ? new Terminal($name) : new NonTerminal($name);
                }
                $productions[] = new Production($written);
            }
            $ruleMap[$lhs] = new ProductionRule($lhs, $productions);
        }

        foreach ($classes as $name => $tokens) {
            $ruleMap[$name] = new ProductionRule($name, array_map(
                static fn (string $token): Production => new Production([new Terminal($token)]),
                $tokens,
            ));
        }
        return new Grammar($startSymbol, $ruleMap);
    }

    /**
     * Reads a Lemon grammar file as a grammar.
     *
     * @param string $path Path of the grammar file
     *
     * @return Grammar Grammar the file describes
     *
     * @throws RuntimeException When the grammar file cannot be read
     * @throws GrammarParseException When the file writes no rules
     * @throws InvalidArgumentException When a rule is filed under a name other than its own left-hand side
     */
    public function parseFile(string $path): Grammar
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Failed to read: {$path}");
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read: {$path}");
        }

        return $this->parse($contents);
    }
}
