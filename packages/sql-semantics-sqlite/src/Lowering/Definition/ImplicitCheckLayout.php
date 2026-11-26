<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlSemantics\Platform\Sqlite\Model\Construction\Layouts;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;

/**
 * Captures the transient three-token form that can determine an implicit CHECK label.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ImplicitCheckLayout
{
    /**
     * Reads the label presentation when the outer trivia is trimmed by SQLite.
     */
    public function capture(Form $form): ?Layout
    {
        $expression = $form->node(2);
        $tokens = $expression->tokens();
        if (count($tokens) !== 3 || preg_match('/\A(?:[<>]=?|<>|!?=|==)\z/', $tokens[1]->text) !== 1
            || preg_match('/\A[0-9]+\z/', $tokens[2]->text) !== 1) {
            return null;
        }
        $open = $tokens[0]->leading;
        $close = $form->token(3)->leading;
        if (preg_match('/\A[ \t\n\r\f]*\z/', $open) !== 1 || preg_match('/\A[ \t\n\r\f]*\z/', $close) !== 1) {
            return null;
        }

        return (new Layouts())->of($expression);
    }
}
