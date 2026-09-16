<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Bison;

use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\GrammarBuilder;

/**
 * Reads the declarations section of a Bison grammar into a builder.
 *
 * Only the declarations that shape the parse table are interpreted: the
 * tokens, the precedence groups, the start symbol and the expected conflict
 * count. Everything else, from type tags to parser parameters, is skipped
 * together with its arguments.
 *
 * @visibility root
 */
final class BisonDeclarations
{
    /**
     * Reads declarations up to and including the section marker.
     *
     * @param BisonTokens $tokens Tokens positioned at the start of the file
     * @param GrammarBuilder $builder Builder to declare into
     *
     * @throws GrammarSourceException When a declaration lacks its argument
     */
    public function read(BisonTokens $tokens, GrammarBuilder $builder): void
    {
        while (($token = $tokens->next()) !== null) {
            if ($token->kind === BisonTokenKind::Section) {
                return;
            }
            if ($token->kind !== BisonTokenKind::Directive) {
                continue;
            }
            $associativity = $this->associativity($token->text);
            if ($associativity !== null) {
                $builder->precedence($this->symbols($tokens), $associativity);
            } elseif ($token->text === 'token' || $token->text === 'term') {
                foreach ($this->symbols($tokens) as $name) {
                    $builder->terminal($name);
                }
            } elseif ($token->text === 'start') {
                $builder->start($tokens->take(BisonTokenKind::Identifier, 'a start symbol')->text);
            } elseif ($token->text === 'expect') {
                $builder->expect((int) $tokens->take(BisonTokenKind::Number, 'an expected conflict count')->text);
            } else {
                $this->skipArguments($tokens);
            }
        }
    }

    /**
     * Answers the associativity a precedence directive declares.
     *
     * @param string $directive Directive name without the percent sign
     *
     * @return Associativity|null The associativity, or null for any other directive
     */
    public function associativity(string $directive): ?Associativity
    {
        return match ($directive) {
            'left' => Associativity::Left,
            'right' => Associativity::Right,
            'nonassoc' => Associativity::NonAssoc,
            'precedence' => Associativity::Precedence,
            default => null,
        };
    }

    /**
     * Reads the symbols a token or precedence directive lists.
     *
     * A type tag before the list, and the token numbers and string aliases
     * between the names, are accepted and ignored.
     *
     * @param BisonTokens $tokens Tokens positioned after the directive
     *
     * @return list<string> The symbol names in order
     */
    public function symbols(BisonTokens $tokens): array
    {
        $names = [];
        while (($token = $tokens->peek()) !== null) {
            if ($token->isSymbol()) {
                $names[] = $token->text;
            } elseif (!in_array($token->kind, [BisonTokenKind::Tag, BisonTokenKind::Number, BisonTokenKind::String], true)) {
                break;
            }
            $tokens->next();
        }

        return $names;
    }

    /**
     * Skips the arguments of a directive that does not shape the table.
     *
     * @param BisonTokens $tokens Tokens positioned after the directive
     */
    public function skipArguments(BisonTokens $tokens): void
    {
        while (($token = $tokens->peek()) !== null) {
            if ($token->kind === BisonTokenKind::Directive || $token->kind === BisonTokenKind::Section) {
                return;
            }
            $tokens->next();
        }
    }
}
