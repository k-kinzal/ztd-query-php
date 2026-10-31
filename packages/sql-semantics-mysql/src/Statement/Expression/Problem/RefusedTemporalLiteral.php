<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Decoded temporal input outside its representation domain in every supported session mode.
 *
 * @visibility public
 * @example Retaining the decoded operand without source syntax
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral('bad'))->text // => 'bad'
 */
final class RefusedTemporalLiteral implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $text The decoded literal bytes
     * @param \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm $form The temporal keyword
     */
    public function __construct(public readonly string $text, public readonly \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm $form = \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date)
    {
    }

    /**
     * Describes the invalid operand's value domain.
     */
    public function message(): string
    {
        return 'Incorrect ' . ($this->form === \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp ? 'DATETIME' : $this->form->value) . ' value.';
    }
}
