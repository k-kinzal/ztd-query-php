<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Rules\Identifiers;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers identifier tokens into decoded names.
 *
 * Rule: SQLITE-NAME-001. Scope: nm and the identifier token classes. A name
 * is decoded by SQLITE-IDENTIFIER-DECODE-001 and recorded as an operand leaf.
 * Source: https://sqlite.org/lang_keywords.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class NameRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a name position.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $name): Name
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'nm: idj', 'nm: STRING' => $this->token($form->token(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Decodes one identifier token and records the name as an operand leaf.
     */
    public function token(Token $token): Name
    {
        return $this->lowering->leaves->record(new Name((new Identifiers())->decode($token->text)));
    }
}
