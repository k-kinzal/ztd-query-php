<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source;

use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Model\UnknownSymbolException;
use SqlFaker\Grammar\Source\Bison\Ast\BisonAst;
use SqlFaker\Grammar\Source\Bison\Ast\BisonRuleNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolForm;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDefinition;

/**
 * Compiles a Grammar from a BisonAst.
 *
 * This compiler works for any Bison grammar (MySQL, PostgreSQL, etc.)
 * by transforming the Bison AST into a formal grammar structure.
 */
final class GrammarCompiler
{
    /**
     * Compile a BisonAst into a Grammar.
     *
     * @throws UnknownSymbolException When a production names a symbol the grammar never declares
     */
    public function compile(BisonAst $ast): Grammar
    {
        $ruleTable = $this->ruleTable($ast);
        $declarationTable = $this->declarationTable($ast);

        /** @var array<string, ProductionRule> $ruleMap */
        $ruleMap = [];
        foreach ($ast->rules as $ruleNode) {
            $productions = [];
            foreach ($ruleNode->alternatives as $altNode) {
                $productions[] = new Production($this->symbols($altNode->symbols, $ruleTable, $declarationTable));
            }

            $alternatives = isset($ruleMap[$ruleNode->name])
                ? [...$ruleMap[$ruleNode->name]->alternatives, ...$productions]
                : $productions;
            $ruleMap[$ruleNode->name] = new ProductionRule($ruleNode->name, $alternatives);
        }

        return new Grammar($ast->startSymbol, $ruleMap);
    }

    /**
     * Answers which names the grammar defines a rule for.
     *
     * @param BisonAst $ast Grammar as it was read
     *
     * @return array<string, BisonRuleNode> Rule name => the rule
     */
    public function ruleTable(BisonAst $ast): array
    {
        $ruleTable = [];
        foreach ($ast->rules as $rule) {
            $ruleTable[$rule->name] = $rule;
        }

        return $ruleTable;
    }

    /**
     * Answers which names the grammar declares as tokens.
     *
     * @param BisonAst $ast Grammar as it was read
     *
     * @return array<string, BisonTokenDefinition> Token name => the declaration
     */
    public function declarationTable(BisonAst $ast): array
    {
        $declarationTable = [];
        foreach ($ast->declarations as $declaration) {
            if (!$declaration instanceof BisonTokenDeclaration) {
                continue;
            }
            foreach ($declaration->tokens as $token) {
                $declarationTable[$token->name] = $token;
            }
        }

        return $declarationTable;
    }

    /**
     * Answers what an alternative's symbols are, once each is known to be one thing or the other.
     *
     * A name the grammar defines a rule for is a non-terminal; a name it only
     * declares as a token, and any character written literally, is a terminal.
     * A name that is neither is a symbol the grammar never declares.
     *
     * @param list<BisonSymbolNode> $symbolNodes Symbols as the alternative wrote them
     * @param array<string, BisonRuleNode> $ruleTable Names the grammar defines a rule for
     * @param array<string, BisonTokenDefinition> $declarationTable Names the grammar declares as tokens
     *
     * @return list<Symbol> The symbols
     *
     * @throws UnknownSymbolException When a production names a symbol the grammar never declares
     */
    public function symbols(array $symbolNodes, array $ruleTable, array $declarationTable): array
    {
        $symbols = [];
        foreach ($symbolNodes as $symNode) {
            $name = $symNode->value;
            $symbols[] = match (true) {
                $symNode->type === BisonSymbolForm::CharLiteral => new Terminal($name),
                isset($ruleTable[$name]) => new NonTerminal($name),
                isset($declarationTable[$name]) => new Terminal($name),
                default => throw new UnknownSymbolException($name),
            };
        }

        return $symbols;
    }
}
