<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;

/**
 * Preserves completed replication value checks before later input errors.
 *
 * The notice retains the decoded operand needed to format its error. No SQL,
 * parser node or execution state survives the lowering call.
 * Observed through isolated MySQL 8.0, 8.4 and 9.1 servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxSettings
{
    /**
     * Returns the refusal of an option outside its public constructor domain.
     */
    public function rejected(Node $node, Lowering $lowering): ?\SqlSemantics\Construction\RejectedInput
    {
        $setting = $this->completed($node, $lowering);
        if ($setting?->problem instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber || ($setting?->problem instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting && in_array($setting->problem->error, [\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::InvalidUuid, \SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::HeartbeatOutOfRange], true))) {
            return new \SqlSemantics\Construction\RejectedInput($setting->problem, (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0, $setting->aborts);
        }

        return null;
    }

    /**
     * Retains system-string conversion before a later input or channel refusal.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion>
     */
    public function converted(Node $node, Lowering $lowering): array
    {
        if ($node->name !== 'TEXT_STRING_sys_nonewline') {
            return [];
        }
        $notice = (new \SqlSemantics\Platform\MySql\Rules\Replication\ChannelName())->conversion($lowering->literals->text($node), $lowering->profile->grammar);

        return $notice === null ? [] : [$notice];
    }

    /**
     * Returns a completed value condition. An excessive delay continues parsing;
     * a refused UUID, heartbeat or password stops before subsequent options.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (in_array($node->name, ['source_def', 'master_def'], true)) {
            try {
                $option = (new SourceOptionRule($lowering))->option($node, (new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->source($lowering->profile->grammar));
                $error = (new \SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings())->error($option, $lowering->profile->grammar);
                if (in_array($error, [\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::DelayOutOfRange, \SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::HeartbeatOutOfRange, \SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::PasswordTooLong], true)) {
                    return new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting($error, $option->value instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Numeral ? $option->value : null), $error !== \SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::DelayOutOfRange);
                }
            } catch (\SqlSemantics\Construction\RejectedInput $failure) {
                return new ParseFailure($failure->problem, $failure->aborts);
            }
        }
        if ($node->name === 'TEXT_STRING_sys_nonewline') {
            $text = $lowering->literals->text($node);

            $problem = (new \SqlSemantics\Platform\MySql\Rules\Replication\ChannelName())->problem($text);

            return $problem === null ? null : new ParseFailure($problem, true);
        }
        if ($node->name !== 'source_reset_options') {
            return null;
        }
        $number = (new ResetRule($lowering))->first($node);
        $problem = ResetBinaryLogs::problem($number);

        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
