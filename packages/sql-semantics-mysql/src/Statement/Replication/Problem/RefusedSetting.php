<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A value of a replication statement that the server refuses while it parses the statement.
 *
 * A refused UUID or newline-free string retains its decoded operand even
 * when lowering stops before a complete statement can be constructed.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility public
 * @example Describing a refused password
 *     (new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting(\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::PasswordTooLong))->message() // => 'A replication password is longer than 32 characters (ER_CHANGE_SOURCE_PASSWORD_LENGTH).'
 */
final class RefusedSetting implements Diagnostic
{
    use Snapshot;

    /**
     * @param ReplicationError $error The check the value fails
     * @param \SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null $number The refused binary log index or source delay, including when only a statement prefix was read
     * @param \SqlSemantics\Platform\MySql\Statement\Literal\Text|null $text The decoded refused UUID or operand containing a forbidden line feed
     */
    public function __construct(public readonly ReplicationError $error, public readonly ?\SqlSemantics\Platform\MySql\Statement\Literal\Numeral $number = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\Literal\Text $text = null)
    {
        \SqlSemantics\Diagnostic\Check::input(in_array($error, [ReplicationError::LineFeed, ReplicationError::InvalidUuid], true) === ($text !== null), 'A line-feed or UUID refusal retains its decoded text operand.');
        \SqlSemantics\Diagnostic\Check::input($text === null || $error !== ReplicationError::LineFeed || str_contains($text->value, "\n"), 'A refused line-feed operand contains a line feed.');
        \SqlSemantics\Diagnostic\Check::input($error !== ReplicationError::InvalidUuid || !\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::AssignGtidsToAnonymousTransactions->accepts($text), 'A refused UUID operand is outside the assignment domain.');
        \SqlSemantics\Diagnostic\Check::input($error === ReplicationError::DelayOutOfRange || (($error === ReplicationError::FileNumberOutOfRange) === ($number !== null)), 'A refused binary log index retains its numeric operand.');
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->error->value;
    }
}
