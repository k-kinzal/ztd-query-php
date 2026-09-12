<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison;

use SqlFaker\Compiler\Bison\Ast\BisonAst;
use SqlFaker\Compiler\Bison\Ast\BisonRuleNode;
use SqlFaker\Compiler\Bison\Ast\BisonSymbolForm;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDefinition;
use SqlFaker\Compiler\UnknownSymbolException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

/**
 * Compiles a Grammar from a BisonAst.
 *
 * Transforms the supplied Bison AST into productions and symbols without
 * selecting a database dialect or interpreting its scanner actions.
 *
 * @visibility root
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
        /** @var array<string, BisonRuleNode> $ruleTable */
        $ruleTable = [];
        foreach ($ast->rules as $rule) {
            $ruleTable[$rule->name] = $rule;
        }

        $declarationTable = $this->declarations($ast);

        /** @var array<string, ProductionRule> $ruleMap */
        $ruleMap = [];

        foreach ($ast->rules as $ruleNode) {
            /** @var list<Production> $productions */
            $productions = [];

            foreach ($ruleNode->alternatives as $altNode) {
                /** @var list<Symbol> $symbols */
                $symbols = [];

                foreach ($altNode->symbols as $symNode) {
                    if ($symNode->type === BisonSymbolForm::CharLiteral) {
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

    /**
     * Indexes declared terminal tokens by name before compiling productions.
     *
     * @return array<string, BisonTokenDefinition>
     */
    public function declarations(BisonAst $ast): array
    {
        /** @var array<string, BisonTokenDefinition> $declarationTable */
        $declarationTable = [];
        foreach ($ast->declarations as $declaration) {
            if ($declaration instanceof BisonTokenDeclaration) {
                foreach ($declaration->tokens as $token) {
                    $declarationTable[$token->name] = $token;
                }
            }
        }

        return $declarationTable;
    }
}
