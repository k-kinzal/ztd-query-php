<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The THREAD_PRIORITY of a resource group: a number with an optional minus sign.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html.
 *
 * @visibility public
 * @example Holding a negative priority
 *     $priority = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority(true, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('5'));
 *     $priority->describe() // => '-5'
 */
final class ThreadPriority implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param bool $negative Whether a minus sign is written
     * @param Numeral $number The number as written
     */
    public function __construct(public readonly bool $negative, public readonly Numeral $number, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        \SqlSemantics\Diagnostic\Check::input($number->isIntegerToken(), 'A thread priority uses a decimal integer token no greater than 2147483647.');
    }

    /**
     * Answers the priority as a person reads it.
     */
    public function describe(): string
    {
        return ($this->negative ? '-' : '') . $this->number->text;
    }

    /**
     * Answers the signed numeric value, independently of leading zeros.
     */
    public function value(): int
    {
        $magnitude = (int) $this->number->text;

        return $this->negative ? -$magnitude : $magnitude;
    }

    /**
     * Tells whether the priority belongs to the declared group's range.
     */
    public function fits(ResourceGroupKind $kind): bool
    {
        $value = $this->value();

        return $kind === ResourceGroupKind::System ? $value >= -20 && $value <= 0 : $value >= 0 && $value <= 19;
    }

    /**
     * Writes the priority.
     */
    public function render(Output $out): void
    {
        if ($this->negative) {
            $out->symbol('-')->glue();
        }
        $out->node($this->number);
    }
}
