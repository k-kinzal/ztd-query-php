<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison;

use RuntimeException;
use SqlFaker\Grammar\GrammarParseException;
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
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Parser for Bison/Yacc grammar files (e.g. MySQL's sql_yacc.yy).
 */
final class BisonParser
{
    public function parse(string $input): BisonAst
    {
        $stream = BisonTokenStream::over($input);
        return $this->process($stream);
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

    private function process(BisonTokenStream $stream): BisonAst
    {
        [$prologue, $declarations] = $this->processDeclarationsSection($stream);
        $rules = $this->processRulesSection($stream);
        $epilogue = $this->processEpilogueSection($stream);

        if ($rules === []) {
            throw GrammarParseException::noRulesParsed('Bison');
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
    private function processDeclarationsSection(BisonTokenStream $stream): array
    {
        $prologue = null;
        /** @var list<BisonDeclaration> $declarations */
        $declarations = [];

        while (($tok = $stream->peek())->type !== BisonLexeme::Eof) {
            if ($tok->type === BisonLexeme::PercentPercent) {
                $stream->next();
                break;
            }

            match ($tok->type) {
                BisonLexeme::Prologue => $prologue = $stream->nextString(),
                BisonLexeme::Directive => ($decl = $this->processDirective($stream)) !== null
                    ? $declarations[] = $decl
                    : null,
                default => $stream->next(),
            };
        }

        return [$prologue, $declarations];
    }

    /**
     * @return list<BisonRuleNode>
     */
    private function processRulesSection(BisonTokenStream $stream): array
    {
        /** @var list<BisonRuleNode> $rules */
        $rules = [];

        while (($tok = $stream->peek())->type !== BisonLexeme::Eof) {
            if ($tok->type === BisonLexeme::PercentPercent) {
                $stream->next();
                break;
            }

            match ($tok->type) {
                BisonLexeme::Identifier => ($rule = $this->processRule($stream)) !== null
                    ? $rules[] = $rule
                    : null,
                default => $stream->next(),
            };
        }

        return $rules;
    }

    private function processEpilogueSection(BisonTokenStream $stream): ?string
    {
        $remaining = trim($stream->consumeRemaining());
        return $remaining !== '' ? $remaining : null;
    }

    private function processDirective(BisonTokenStream $stream): ?BisonDeclaration
    {
        $directive = $stream->nextString();

        return match ($directive) {
            '%start' => $this->processStartDirective($stream),
            '%token' => $this->processTokenDirective($stream),
            '%type' => $this->processTypeDirective($stream),
            '%left', '%right', '%nonassoc', '%precedence' => $this->processPrecedenceDirective($stream, $directive),
            '%parse-param', '%lex-param' => $this->processParamDirective($stream, $directive),
            '%expect' => $this->processExpectDirective($stream),
            '%define' => $this->processDefineDirective($stream),
            default => $this->processUnknownDirective($stream, $directive),
        };
    }

    private function processStartDirective(BisonTokenStream $stream): ?BisonStartDeclaration
    {
        $next = $stream->peek();
        if ($next->type !== BisonLexeme::Identifier) {
            return null;
        }
        return new BisonStartDeclaration($stream->nextString());
    }

    private function processTokenDirective(BisonTokenStream $stream): BisonTokenDeclaration
    {
        $typeTag = null;
        if ($stream->peek()->type === BisonLexeme::TypeTag) {
            $typeTag = $stream->nextString();
        }

        /** @var list<BisonTokenInfo> $declared */
        $declared = [];

        while ($this->isDeclarationContent($stream->peek()->type)) {
            if ($stream->peek()->type !== BisonLexeme::Identifier) {
                $stream->next();
                continue;
            }

            $name = $stream->nextString();
            $number = null;
            $alias = null;

            $peek = $stream->peek();
            if ($peek->type === BisonLexeme::Number) {
                $number = $stream->nextInt();
                $peek = $stream->peek();
            }
            if ($peek->type === BisonLexeme::StringLiteral) {
                $alias = $stream->nextString();
            }

            $declared[] = new BisonTokenInfo($name, $number, $alias);
        }

        return new BisonTokenDeclaration($typeTag, $declared);
    }

    private function processTypeDirective(BisonTokenStream $stream): ?BisonTypeDeclaration
    {
        if ($stream->peek()->type !== BisonLexeme::TypeTag) {
            return null;
        }
        $typeTag = $stream->nextString();

        /** @var list<string> $symbols */
        $symbols = [];

        while ($this->isDeclarationContent($stream->peek()->type)) {
            $peek = $stream->peek();
            if ($peek->type === BisonLexeme::Identifier) {
                $symbols[] = $stream->nextString();
            } else {
                $stream->next();
            }
        }

        return new BisonTypeDeclaration($typeTag, $symbols);
    }

    private function processPrecedenceDirective(BisonTokenStream $stream, string $directive): BisonPrecedenceDeclaration
    {
        /** @var 'left'|'right'|'nonassoc'|'precedence' $associativity */
        $associativity = substr($directive, 1);

        $typeTag = null;
        if ($stream->peek()->type === BisonLexeme::TypeTag) {
            $typeTag = $stream->nextString();
        }

        /** @var list<string> $symbols */
        $symbols = [];

        while ($this->isDeclarationContent($stream->peek()->type)) {
            $peek = $stream->peek();
            if ($peek->type === BisonLexeme::Identifier || $peek->type === BisonLexeme::CharLiteral) {
                $symbols[] = $stream->nextString();
            } else {
                $stream->next();
            }
        }

        return new BisonPrecedenceDeclaration($associativity, $typeTag, $symbols);
    }

    private function processParamDirective(BisonTokenStream $stream, string $directive): ?BisonParamDeclaration
    {
        /** @var 'parse-param'|'lex-param' $kind */
        $kind = substr($directive, 1);

        if ($stream->peek()->type !== BisonLexeme::Action) {
            return null;
        }
        $code = $stream->nextString();

        return new BisonParamDeclaration($kind, $code);
    }

    private function processExpectDirective(BisonTokenStream $stream): ?BisonExpectDeclaration
    {
        if ($stream->peek()->type !== BisonLexeme::Number) {
            return null;
        }
        return new BisonExpectDeclaration($stream->nextInt());
    }

    private function processDefineDirective(BisonTokenStream $stream): ?BisonDefineDeclaration
    {
        if ($stream->peek()->type !== BisonLexeme::Identifier) {
            return null;
        }
        $name = $stream->nextString();

        $value = null;
        $peek = $stream->peek();
        if ($peek->type === BisonLexeme::Identifier
            || $peek->type === BisonLexeme::StringLiteral
            || $peek->type === BisonLexeme::Number) {
            $value = $stream->nextString();
        }

        return new BisonDefineDeclaration($name, $value);
    }

    private function processUnknownDirective(BisonTokenStream $stream, string $directive): BisonUnknownDeclaration
    {
        $parts = [];
        while ($this->isDeclarationContent($stream->peek()->type)) {
            $parts[] = $stream->nextString();
        }
        return new BisonUnknownDeclaration($directive, implode(' ', $parts));
    }

    private function isDeclarationContent(BisonLexeme $type): bool
    {
        return $type !== BisonLexeme::Directive
            && $type !== BisonLexeme::Prologue
            && $type !== BisonLexeme::PercentPercent
            && $type !== BisonLexeme::Eof;
    }

    private function processRule(BisonTokenStream $stream): ?BisonRuleNode
    {
        if ($stream->peekN(2)->type !== BisonLexeme::Colon) {
            $stream->next();
            return null;
        }

        $lhs = $stream->nextString();
        $stream->next();
        return new BisonRuleNode($lhs, $this->processAlternatives($stream));
    }

    /**
     * @return list<BisonAlternativeNode>
     */
    private function processAlternatives(BisonTokenStream $stream): array
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
            $tok = $stream->peek();

            if ($tok->type === BisonLexeme::Eof
                || $tok->type === BisonLexeme::PercentPercent
                || ($tok->type === BisonLexeme::Identifier && $stream->peekN(2)->type === BisonLexeme::Colon)) {
                $alternatives[] = new BisonAlternativeNode($symbols, $action, $prec, $dprec, $merge);
                break;
            }

            if ($tok->type === BisonLexeme::Semicolon) {
                $stream->next();
                $alternatives[] = new BisonAlternativeNode($symbols, $action, $prec, $dprec, $merge);
                break;
            }

            if ($tok->type === BisonLexeme::Pipe) {
                $stream->next();
                $alternatives[] = new BisonAlternativeNode($symbols, $action, $prec, $dprec, $merge);
                $symbols = [];
                $action = null;
                $prec = null;
                $dprec = null;
                $merge = null;
                continue;
            }

            match ($tok->type) {
                BisonLexeme::Action => $action = $stream->nextString(),
                BisonLexeme::Identifier => $symbols[] = new BisonSymbolNode(BisonSymbolType::Identifier, $stream->nextString()),
                BisonLexeme::CharLiteral => $symbols[] = new BisonSymbolNode(BisonSymbolType::CharLiteral, $stream->nextString()),
                BisonLexeme::Directive => match ($stream->nextString()) {
                    '%prec' => $prec = $this->processPrec($stream),
                    '%dprec' => $dprec = $this->processDprec($stream),
                    '%merge' => $merge = $this->processMerge($stream),
                    default => null,
                },
                default => $stream->next(),
            };
        }

        return $alternatives;
    }

    private function processPrec(BisonTokenStream $stream): ?string
    {
        $next = $stream->peek();
        if ($next->type === BisonLexeme::Identifier || $next->type === BisonLexeme::CharLiteral) {
            return $stream->nextString();
        }
        return null;
    }

    private function processDprec(BisonTokenStream $stream): ?int
    {
        if ($stream->peek()->type === BisonLexeme::Number) {
            return $stream->nextInt();
        }
        return null;
    }

    private function processMerge(BisonTokenStream $stream): ?string
    {
        if ($stream->peek()->type === BisonLexeme::TypeTag) {
            return $stream->nextString();
        }
        return null;
    }
}
