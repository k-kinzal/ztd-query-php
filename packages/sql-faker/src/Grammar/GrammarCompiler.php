<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use SqlFaker\MySql\Bison\Ast\BisonAst;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenInfo;

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
     */
    public function compile(BisonAst $ast): Grammar
    {
        /** @var array<string, BisonRuleNode> $ruleTable */
        $ruleTable = [];
        foreach ($ast->rules as $rule) {
            $ruleTable[$rule->name] = $rule;
        }

        /** @var array<string, BisonTokenInfo> $declarationTable */
        $declarationTable = [];
        foreach ($ast->declarations as $declaration) {
            if ($declaration instanceof BisonTokenDeclaration) {
                foreach ($declaration->tokens as $token) {
                    $declarationTable[$token->name] = $token;
                }
            }
        }

        /** @var array<string, ProductionRule> $ruleMap */
        $ruleMap = [];

        foreach ($ast->rules as $ruleNode) {
            /** @var list<Production> $productions */
            $productions = [];

            foreach ($ruleNode->alternatives as $altNode) {
                /** @var list<Symbol> $symbols */
                $symbols = [];

                foreach ($altNode->symbols as $symNode) {
                    if ($symNode->type === BisonSymbolType::CharLiteral) {
                        $symbols[] = new Terminal($symNode->value);
                    } elseif (isset($ruleTable[$symNode->value])) {
                        $symbols[] = new NonTerminal($symNode->value);
                    } elseif (isset($declarationTable[$symNode->value])) {
                        $symbols[] = new Terminal($symNode->value);
                    } else {
                        throw new UnknownSymbolException($symNode->value);
                    }
                }

                $productions[] = new Production($symbols);
            }

            if (isset($ruleMap[$ruleNode->name])) {
                $merged = array_merge(
                    $ruleMap[$ruleNode->name]->alternatives,
                    $productions
                );
                $ruleMap[$ruleNode->name] = new ProductionRule($ruleNode->name, $merged);
            } else {
                $ruleMap[$ruleNode->name] = new ProductionRule($ruleNode->name, $productions);
            }
        }

        return new Grammar($ast->startSymbol, $ruleMap);
    }
}
