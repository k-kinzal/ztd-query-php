<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison;

use LogicException;
use RuntimeException;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonAst;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonParamDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonSymbolNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenInfo;
use SqlFaker\MySql\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\MySql\Bison\Lexer\BisonLexer;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;

/**
 * Parser for Bison/Yacc grammar files (e.g. MySQL's sql_yacc.yy).
 */
final class BisonParser
{
    public function parse(string $input): BisonAst
    {
        $lexer = new BisonLexer($input);
        return $this->process($lexer);
    }

    public function parseFile(string $path): BisonAst
    {
        if (!is_file($path)) {
            throw new RuntimeException("Failed to read: {$path}");
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read: {$path}");
        }
        return $this->parse($contents);
    }

    private function process(BisonLexer $lexer): BisonAst
    {
        [$prologue, $declarations] = $this->processDeclarationsSection($lexer);
        $rules = $this->processRulesSection($lexer);
        $epilogue = $this->processEpilogueSection($lexer);

        if ($rules === []) {
            throw new LogicException('No grammar rules parsed.');
        }

        $startSymbol = $this->determineStartSymbol($declarations, $rules);

        return new BisonAst($startSymbol, $prologue, $declarations, $rules, $epilogue);
    }

    /**
     * @param list<BisonDeclaration> $declarations
     * @param list<BisonRuleNode> $rules
     */
    private function determineStartSymbol(array $declarations, array $rules): string
    {
        foreach ($declarations as $decl) {
            if ($decl instanceof BisonStartDeclaration) {
                return $decl->symbol;
            }
        }

        return $rules[0]->name;
    }

    /**
     * @return array{?string, list<BisonDeclaration>}
     */
    private function processDeclarationsSection(BisonLexer $lexer): array
    {
        $prologue = null;
        /** @var list<BisonDeclaration> $declarations */
        $declarations = [];

        while (($tok = $lexer->peek())->type !== BisonTokenType::Eof) {
            if ($tok->type === BisonTokenType::PercentPercent) {
                $lexer->next();
                break;
            }

            match ($tok->type) {
                BisonTokenType::Prologue => $prologue = $lexer->nextString(),
                BisonTokenType::Directive => ($decl = $this->processDirective($lexer)) !== null
                    ? $declarations[] = $decl
                    : null,
                default => $lexer->next(),
            };
        }

        return [$prologue, $declarations];
    }

    /**
     * @return list<BisonRuleNode>
     */
    private function processRulesSection(BisonLexer $lexer): array
    {
        /** @var list<BisonRuleNode> $rules */
        $rules = [];

        while (($tok = $lexer->peek())->type !== BisonTokenType::Eof) {
            if ($tok->type === BisonTokenType::PercentPercent) {
                $lexer->next();
                break;
            }

            match ($tok->type) {
                BisonTokenType::Identifier => ($rule = $this->processRule($lexer)) !== null
                    ? $rules[] = $rule
                    : null,
                default => $lexer->next(),
            };
        }

        return $rules;
    }

    private function processEpilogueSection(BisonLexer $lexer): ?string
    {
        $remaining = trim($lexer->consumeRemaining());
        return $remaining !== '' ? $remaining : null;
    }

    private function processDirective(BisonLexer $lexer): ?BisonDeclaration
    {
        $directive = $lexer->nextString();

        return match ($directive) {
            '%start' => $this->processStartDirective($lexer),
            '%token' => $this->processTokenDirective($lexer),
            '%type' => $this->processTypeDirective($lexer),
            '%left', '%right', '%nonassoc', '%precedence' => $this->processPrecedenceDirective($lexer, $directive),
            '%parse-param', '%lex-param' => $this->processParamDirective($lexer, $directive),
            '%expect' => $this->processExpectDirective($lexer),
            '%define' => $this->processDefineDirective($lexer),
            default => $this->processUnknownDirective($lexer, $directive),
        };
    }

    private function processStartDirective(BisonLexer $lexer): ?BisonStartDeclaration
    {
        $next = $lexer->peek();
        if ($next->type !== BisonTokenType::Identifier) {
            return null;
        }
        return new BisonStartDeclaration($lexer->nextString());
    }

    private function processTokenDirective(BisonLexer $lexer): BisonTokenDeclaration
    {
        $typeTag = null;
        if ($lexer->peek()->type === BisonTokenType::TypeTag) {
            $typeTag = $lexer->nextString();
        }

        /** @var list<BisonTokenInfo> $tokens */
        $tokens = [];

        while ($this->isDeclarationContent($lexer->peek()->type)) {
            if ($lexer->peek()->type !== BisonTokenType::Identifier) {
                $lexer->next();
                continue;
            }

            $name = $lexer->nextString();
            $number = null;
            $alias = null;

            $peek = $lexer->peek();
            if ($peek->type === BisonTokenType::Number) {
                $number = $lexer->nextInt();
                $peek = $lexer->peek();
            }
            if ($peek->type === BisonTokenType::StringLiteral) {
                $alias = $lexer->nextString();
            }

            $tokens[] = new BisonTokenInfo($name, $number, $alias);
        }

        return new BisonTokenDeclaration($typeTag, $tokens);
    }

    private function processTypeDirective(BisonLexer $lexer): ?BisonTypeDeclaration
    {
        if ($lexer->peek()->type !== BisonTokenType::TypeTag) {
            return null;
        }
        $typeTag = $lexer->nextString();

        /** @var list<string> $symbols */
        $symbols = [];

        while ($this->isDeclarationContent($lexer->peek()->type)) {
            $peek = $lexer->peek();
            if ($peek->type === BisonTokenType::Identifier) {
                $symbols[] = $lexer->nextString();
            } else {
                $lexer->next();
            }
        }

        return new BisonTypeDeclaration($typeTag, $symbols);
    }

    private function processPrecedenceDirective(BisonLexer $lexer, string $directive): BisonPrecedenceDeclaration
    {
        /** @var 'left'|'right'|'nonassoc'|'precedence' $associativity */
        $associativity = substr($directive, 1);

        $typeTag = null;
        if ($lexer->peek()->type === BisonTokenType::TypeTag) {
            $typeTag = $lexer->nextString();
        }

        /** @var list<string> $symbols */
        $symbols = [];

        while ($this->isDeclarationContent($lexer->peek()->type)) {
            $peek = $lexer->peek();
            if ($peek->type === BisonTokenType::Identifier || $peek->type === BisonTokenType::CharLiteral) {
                $symbols[] = $lexer->nextString();
            } else {
                $lexer->next();
            }
        }

        return new BisonPrecedenceDeclaration($associativity, $typeTag, $symbols);
    }

    private function processParamDirective(BisonLexer $lexer, string $directive): ?BisonParamDeclaration
    {
        /** @var 'parse-param'|'lex-param' $kind */
        $kind = substr($directive, 1);

        if ($lexer->peek()->type !== BisonTokenType::Action) {
            return null;
        }
        $code = $lexer->nextString();

        return new BisonParamDeclaration($kind, $code);
    }

    private function processExpectDirective(BisonLexer $lexer): ?BisonExpectDeclaration
    {
        if ($lexer->peek()->type !== BisonTokenType::Number) {
            return null;
        }
        return new BisonExpectDeclaration($lexer->nextInt());
    }

    private function processDefineDirective(BisonLexer $lexer): ?BisonDefineDeclaration
    {
        if ($lexer->peek()->type !== BisonTokenType::Identifier) {
            return null;
        }
        $name = $lexer->nextString();

        $value = null;
        $peek = $lexer->peek();
        if ($peek->type === BisonTokenType::Identifier
            || $peek->type === BisonTokenType::StringLiteral
            || $peek->type === BisonTokenType::Number) {
            $value = $lexer->nextString();
        }

        return new BisonDefineDeclaration($name, $value);
    }

    private function processUnknownDirective(BisonLexer $lexer, string $directive): BisonUnknownDeclaration
    {
        $parts = [];
        while ($this->isDeclarationContent($lexer->peek()->type)) {
            $parts[] = $lexer->nextString();
        }
        return new BisonUnknownDeclaration($directive, implode(' ', $parts));
    }

    private function isDeclarationContent(BisonTokenType $type): bool
    {
        return $type !== BisonTokenType::Directive
            && $type !== BisonTokenType::Prologue
            && $type !== BisonTokenType::PercentPercent
            && $type !== BisonTokenType::Eof;
    }

    private function processRule(BisonLexer $lexer): ?BisonRuleNode
    {
        if ($lexer->peekN(2)->type !== BisonTokenType::Colon) {
            $lexer->next();
            return null;
        }

        $lhs = $lexer->nextString();
        $lexer->next();
        return new BisonRuleNode($lhs, $this->processAlternatives($lexer));
    }

    /**
     * @return list<BisonAlternativeNode>
     */
    private function processAlternatives(BisonLexer $lexer): array
    {
        /** @var list<BisonAlternativeNode> $alternatives */
        $alternatives = [];

        /** @var list<BisonSymbolNode> $symbols */
        $symbols = [];
        $action = null;
        $prec = null;
        $dprec = null;
        $merge = null;

        while (true) {
            $tok = $lexer->peek();

            if ($tok->type === BisonTokenType::Eof
                || $tok->type === BisonTokenType::PercentPercent
                || ($tok->type === BisonTokenType::Identifier && $lexer->peekN(2)->type === BisonTokenType::Colon)) {
                $alternatives[] = new BisonAlternativeNode($symbols, $action, $prec, $dprec, $merge);
                break;
            }

            if ($tok->type === BisonTokenType::Semicolon) {
                $lexer->next();
                $alternatives[] = new BisonAlternativeNode($symbols, $action, $prec, $dprec, $merge);
                break;
            }

            if ($tok->type === BisonTokenType::Pipe) {
                $lexer->next();
                $alternatives[] = new BisonAlternativeNode($symbols, $action, $prec, $dprec, $merge);
                $symbols = [];
                $action = null;
                $prec = null;
                $dprec = null;
                $merge = null;
                continue;
            }

            match ($tok->type) {
                BisonTokenType::Action => $action = $lexer->nextString(),
                BisonTokenType::Identifier => $symbols[] = new BisonSymbolNode(BisonSymbolType::Identifier, $lexer->nextString()),
                BisonTokenType::CharLiteral => $symbols[] = new BisonSymbolNode(BisonSymbolType::CharLiteral, $lexer->nextString()),
                BisonTokenType::Directive => match ($lexer->nextString()) {
                    '%prec' => $prec = $this->processPrec($lexer),
                    '%dprec' => $dprec = $this->processDprec($lexer),
                    '%merge' => $merge = $this->processMerge($lexer),
                    default => null,
                },
                default => $lexer->next(),
            };
        }

        return $alternatives;
    }

    private function processPrec(BisonLexer $lexer): ?string
    {
        $next = $lexer->peek();
        if ($next->type === BisonTokenType::Identifier || $next->type === BisonTokenType::CharLiteral) {
            return $lexer->nextString();
        }
        return null;
    }

    private function processDprec(BisonLexer $lexer): ?int
    {
        if ($lexer->peek()->type === BisonTokenType::Number) {
            return $lexer->nextInt();
        }
        return null;
    }

    private function processMerge(BisonLexer $lexer): ?string
    {
        if ($lexer->peek()->type === BisonTokenType::TypeTag) {
            return $lexer->nextString();
        }
        return null;
    }
}
