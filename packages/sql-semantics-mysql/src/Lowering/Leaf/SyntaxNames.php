<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Interprets completed name lookups before a later syntax refusal.
 *
 * A named character set or definition collation stops parsing when unknown.
 * In an expression, a nonempty unknown collation
 * records an error and parsing continues; an empty collation is resolved
 * later. Observed on native MySQL 8.0.44, 8.4.7 and 9.1.0 connections.
 * Decoding uses the same name rules and lexical profile as full lowering.
 * No syntax tree or input text is retained in the returned notice.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxNames
{
    /**
     * Answers the diagnostic of a completed charset or collation name.
     */
    public function completed(Node $node, Lowering $lowering): ParseFailure|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated|null
    {
        $deprecated = $this->unquoted($node, $lowering->profile->grammar);
        if ($deprecated !== null) {
            return $deprecated;
        }
        $first = $node->children[0] ?? null;
        if ($lowering->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5744 && $first instanceof \SqlParser\Lexer\Token && $first->text === '.') {
            return match ($node->name) {
                'table_ident' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotTable,
                'simple_ident_q' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotColumn,
                'field_ident' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotDefinitionName,
                default => null,
            };
        }
        if ($node->name === 'simple_expr') {
            $form = $lowering->productions->form($node);
            if ($form->signature !== 'simple_expr: simple_expr COLLATE_SYM ident_or_text') {
                return null;
            }
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CollationLookup())->parsed($lowering->names->identifier($form->node(2)), $lowering->profile->grammar);

            return $problem === null ? null : new ParseFailure($problem);
        }
        if (!in_array($node->name, ['collation_name', 'charset_name', 'old_or_new_charset_name'], true)) {
            return null;
        }
        $name = $lowering->charsets->name($node)?->value;
        if ($name === null) {
            return null;
        }
        if ($node->name === 'collation_name') {
            return Collation::forName($name, $lowering->profile->grammar) === null ? new ParseFailure(new UnknownCollation($name), true) : null;
        }

        return Charset::named($name) === null ? new ParseFailure(new UnknownCharset($name), true) : null;
    }
    /**
     * Answers the warning of the keyword-as-identifier production itself.
     * Quoted and qualified identifier tokens never use this production.
     */
    public function unquoted(Node $node, \SqlSemantics\Contract\GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated
    {
        $token = $node->children[0] ?? null;
        $deprecated = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull;

        return $node->name === 'ident_keywords_unambiguous' && $token instanceof \SqlParser\Lexer\Token && $token->name === 'FULL' && $deprecated->warnedIn($release) ? $deprecated : null;
    }

}
