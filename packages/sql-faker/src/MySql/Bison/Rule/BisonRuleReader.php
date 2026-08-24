<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Rule;

use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Reads the rules section of a Bison grammar.
 *
 * A rule is an identifier followed by a colon, and nothing else in the section
 * looks like that. Anything that does not is skipped one token at a time rather
 * than aborting the section, because MySQL's grammar carries constructs this
 * parser has no model for and losing the rules after one of them would be worse
 * than losing the construct itself.
 */
final class BisonRuleReader
{
    /** @readonly */
    private BisonAlternativeReader $alternatives;

    /**
     * @param BisonAlternativeReader|null $alternatives Reads the right-hand side of each rule
     */
    public function __construct(?BisonAlternativeReader $alternatives = null)
    {
        $this->alternatives = $alternatives ?? new BisonAlternativeReader();
    }

    /**
     * Reads every rule up to the end of the section.
     *
     * @param BisonTokenStream $stream Stream positioned at the start of the rules section
     *
     * @return list<BisonRuleNode> The rules, in the order they were declared
     */
    public function readAll(BisonTokenStream $stream): array
    {
        $rules = [];

        while ($stream->peek()->type !== BisonLexeme::Eof) {
            if ($stream->peek()->type === BisonLexeme::PercentPercent) {
                $stream->next();
                break;
            }

            if ($stream->peek()->type !== BisonLexeme::Identifier) {
                $stream->next();
                continue;
            }

            $rule = $this->read($stream);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Reads one rule and its alternatives.
     *
     * @param BisonTokenStream $stream Stream positioned on a candidate rule name
     *
     * @return BisonRuleNode|null The rule, or null when the identifier did not open one
     */
    public function read(BisonTokenStream $stream): ?BisonRuleNode
    {
        if ($stream->peekN(2)->type !== BisonLexeme::Colon) {
            $stream->next();

            return null;
        }

        $name = $stream->nextString();
        $stream->next();

        return new BisonRuleNode($name, $this->alternatives->readAll($stream));
    }
}
