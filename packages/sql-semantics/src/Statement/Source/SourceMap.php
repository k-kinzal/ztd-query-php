<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Source;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * Original input locations for occurrences whose lowering records them.
 *
 * Coverage is partial: an occurrence constructed directly, or lowered by a rule without source
 * tracking, has no location. Identity distinguishes equal expressions at different positions.
 * Rebinding an existing occurrence can read the input location it already owns.
 *
 * @visibility public
 * @example Looking up an occurrence by identity
 *     $literal = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
 *     $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($literal, 7, 1)]);
 *     $sources->of($literal)?->offset // => 7
 */
final class SourceMap
{
    use Snapshot;

    /**
     * @var list<Origin> The recorded occurrences
     */
    public readonly array $origins;

    /**
     * @var list<SourceNotice> Warnings of original spellings, separate from semantic values
     */
    public readonly array $notices;

    /**
     * @param list<Origin> $origins The recorded occurrences, each at most once
     * @param list<SourceNotice> $notices The warnings recorded while lowering the original input
     */
    public function __construct(array $origins = [], array $notices = [])
    {
        $this->origins = Check::listOf($origins, Origin::class, 'Source locations are origins.');
        $this->notices = Check::listOf($notices, SourceNotice::class, 'Input notices are source notice values.');
        $seen = [];
        foreach ($this->origins as $origin) {
            $id = spl_object_id($origin->node);
            Check::input(!isset($seen[$id]), 'An occurrence has at most one source location.');
            $seen[$id] = true;
        }
    }

    /**
     * Finds the original byte range of this occurrence, or null if no location was recorded.
     */
    public function of(Node $node): ?Origin
    {
        foreach ($this->origins as $origin) {
            if ($origin->node === $node) {
                return $origin;
            }
        }

        $span = $node->source();
        return $span === null ? null : new Origin($node, $span->offset, $span->length);
    }

    /**
     * Keeps spelling notices when the same SQL structure is bound to another context.
     * Derived warnings are recomputed; equal warnings at different occurrences remain distinct.
     */
    public function retainingInputNotices(\SqlSemantics\Statement\AnalyzedStatement $statement): self
    {
        if (!$statement->hasStatementInformation()) {
            return $this;
        }
        $notices = $this->notices;
        foreach ($statement->notices() as $notice) {
            if (!$notice->fromInput) {
                continue;
            }
            Check::invariant($notice->subject !== null && $notice->inputSource !== null, 'An input notice owns its subject and boundary.');
            foreach ($notices as $existing) {
                if ($existing->subject === $notice->subject && $existing->warning === $notice->warning && $existing->offset === $notice->inputSource->offset) {
                    continue 2;
                }
            }
            $notices[] = new SourceNotice($notice->subject, $notice->warning, $notice->inputSource->offset);
        }
        return new self($this->origins, $notices);
    }

}
