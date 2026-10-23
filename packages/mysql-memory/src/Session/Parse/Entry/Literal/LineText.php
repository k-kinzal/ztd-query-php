<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Literal;

use MySqlMemory\Error\DiagnosticText;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * Formats the decoded operand of a forbidden-line-feed refusal.
 *
 * @visibility MySqlMemory
 */
final class LineText
{
    /**
     * Preserves the release-specific bound and the operand's NUL boundary.
     */
    public function error(Text $text, Session $session): SqlError
    {
        $client = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'));
        $decoded = (new \SqlSemantics\Platform\MySql\Rules\Text\NameEncoding())->decode($text->value, $client);
        $value = (new \SqlSemantics\Platform\MySql\Rules\Replication\ChannelName())->value(new Text($decoded));
        $display = (new DiagnosticText())->bytes($value, 128, $session->settings()->legacy());

        return DataError::WrongValue->error('argument contains not-allowed LF', $display);
    }
}
