<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Command\View\WithText;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;

/**
 * Reports a stored-view separator failure from its resolved structure.
 *
 * No stored SQL is parsed or executed. The text is solely a diagnostic excerpt
 * derived from the declaration's immutable projection and lexical CTE uses.
 * Observed on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class StoredViewSyntax
{
    /**
     * Answers the native reopening condition for a trailing unused CTE.
     */
    public function error(View $view): ?SqlError
    {
        $text = new ViewText($view->created->facts, '', $view->database);
        $with = new WithText($text);
        foreach ((new ReadDependencies())->nodes($view->definition) as $node) {
            if (!$node instanceof QueryExpression || !$node->with instanceof With) {
                continue;
            }
            $used = $with->used($node);
            if ($used === [] || array_key_last($used) === count($node->with->tables) - 1) {
                continue;
            }
            $body = $text->query($node->body);
            if ($body !== null) {
                return StatementError::ParseError->error(mb_strcut($body . ($text->tail($node->orderBy, $node->limit) ?? ''), 0, 80, 'UTF-8'), 1);
            }
        }
        return null;
    }
}
