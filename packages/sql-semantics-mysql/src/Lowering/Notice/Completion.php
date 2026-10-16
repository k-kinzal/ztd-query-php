<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Notice;

use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseStop;

/**
 * Tracks completed input notices and their stopping boundaries during lowering.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Completion
{
    /**
     * Whether a completed condition prevents any further input notices.
     */
    public bool $aborted = false;

    /**
     * Whether the current stored instruction must stop when it completes.
     */
    public bool $failed = false;

    /**
     * The completion offset of the most recent failure, before enclosing reductions.
     */
    public ?int $failedAt = null;

    /**
     * @param list<array{int, Deprecated|Deprecation|NameConversion|\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod|ParseFailure|ParseStop}> $warnings Completed notices and their input boundaries
     */
    public function __construct(public array $warnings = [])
    {
    }

    /**
     * Records a notice, including whether its instruction or the whole input stops.
     */
    public function record(int $end, Deprecated|Deprecation|NameConversion|\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod|ParseFailure|ParseStop $notice): void
    {
        $this->warnings[] = [$end, $notice];
        $this->failed = $this->failed || ($notice instanceof ParseFailure && $notice->endsProgramStatement);
        $this->failedAt = $notice instanceof ParseFailure ? $end : $this->failedAt;
        $this->aborted = $this->aborted || $notice instanceof ParseStop || (($notice instanceof ParseFailure || $notice instanceof Deprecation) && $notice->aborts);
    }
}
