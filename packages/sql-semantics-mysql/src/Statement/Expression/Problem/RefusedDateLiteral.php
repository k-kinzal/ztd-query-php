<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Decoded DATE input outside every supported calendar mode's representation domain.
 *
 * @visibility public
 * @example Retaining the decoded operand without source syntax
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedDateLiteral('bad'))->text // => 'bad'
 */
final class RefusedDateLiteral implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $text The decoded literal bytes
     */
    public function __construct(public readonly string $text)
    {
    }

    /**
     * Describes the invalid operand's value domain.
     */
    public function message(): string
    {
        return 'Incorrect DATE value.';
    }
}
