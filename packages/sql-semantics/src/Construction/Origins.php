<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use SqlParser\Parser\Node as ParseNode;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;

/**
 * Records original byte ranges while a parse tree is lowered, then publishes an immutable map.
 *
 * @visibility SqlSemantics
 */
final class Origins
{
    /**
     * @var array<int, Origin> Locations by semantic occurrence identity
     */
    private array $origins = [];

    /**
     * @var list<\SqlSemantics\Statement\Source\SourceNotice> Warnings of the original input spelling
     */
    private array $notices = [];

    private readonly SourceRanges $ranges;

    /**
     * Shares transient source ranges across occurrence recordings.
     */
    public function __construct()
    {
        $this->ranges = new SourceRanges();
    }

    /**
     * Records the range from the first through the last source token, excluding empty synthetic tokens, and returns the occurrence.
     *
     * @template T of Node
     * @param T $value The lowered occurrence
     * @return T
     */
    public function record(Node $value, ParseNode $source): Node
    {
        $range = $this->ranges->span($source);
        if ($range !== null) {
            $this->origins[spl_object_id($value)] = new Origin($value, $range[0], $range[1] - $range[0]);
        }

        return $value;
    }

    /**
     * Removes the range of an intermediate occurrence replaced during lowering.
     *
     * Children retain their own ranges. Publication still checks that every
     * remaining range belongs to the final statement structure.
     */
    public function forget(Node $value): void
    {
        unset($this->origins[spl_object_id($value)]);
    }

    /**
     * Records a spelling-dependent warning after the final token of its input production.
     */
    public function notice(Node|\SqlSemantics\Statement\Identifier\Name $subject, \SqlSemantics\Statement\Fact\Warning $warning, ParseNode $source): void
    {
        $range = $this->ranges->span($source, true);
        if ($range !== null) {
            $this->noticeAt($subject, $warning, $range[1]);
        }
    }

    /**
     * Records a notice whose boundary is inside an input comment or token.
     */
    public function noticeAt(Node|\SqlSemantics\Statement\Identifier\Name $subject, \SqlSemantics\Statement\Fact\Warning $warning, int $offset): void
    {
        $this->notices[] = new \SqlSemantics\Statement\Source\SourceNotice($subject, $warning, $offset);
    }

    /**
     * Publishes a snapshot that later recordings cannot change.
     */
    public function publish(): SourceMap
    {
        return new SourceMap(array_values($this->origins), $this->notices);
    }
}
