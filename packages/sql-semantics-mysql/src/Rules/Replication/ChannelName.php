<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

/**
 * The decoded name domain shared by every FOR CHANNEL operand.
 *
 * Channel names are quoted text whose NUL-terminated name contains no LF.
 * Empty names identify the default channel. Input
 * lowering reports a typed refusal before publishing a statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/replication-channels.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ChannelName
{
    /**
     * Checks the optional channel operand before a structure is retained.
     */
    public function check(?Text $channel): void
    {
        Check::input($channel === null || $channel->radix === null, 'A replication channel is quoted text.');
        Check::input($this->problem($channel) === null, 'A replication channel cannot contain a line feed.');
    }

    /**
     * Answers the diagnostic of decoded channel text containing LF.
     */
    public function problem(?Text $channel): ?RefusedSetting
    {
        $value = $channel === null ? '' : $this->value($channel);

        return str_contains($value, "\n") ? new RefusedSetting(ReplicationError::LineFeed, text: $channel->value === $value ? $channel : new Text($value)) : null;
    }

    /**
     * Answers the name used for channel lookup; the literal retains all bytes for rendering.
     */
    public function value(Text $channel): string
    {
        $converted = preg_replace('/[\xF0-\xF4][\x80-\xBF]{3}/', '?', $channel->value) ?? $channel->value;

        return explode("\0", $converted, 2)[0];
    }

    /**
     * Describes supplementary Unicode conversion into the system character set.
     */
    public function conversion(Text $channel, \SqlSemantics\Contract\GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion
    {
        return preg_match('/[\xF0-\xF4][\x80-\xBF]{3}/', $channel->value) === 1
            ? new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(new \SqlSemantics\Statement\Identifier\Name($channel->value), in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true))
            : null;
    }
}
