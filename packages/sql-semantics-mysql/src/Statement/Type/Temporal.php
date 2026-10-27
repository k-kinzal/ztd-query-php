<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Rendering\Output;

/**
 * A date and time type with its optional fractional seconds precision, or YEAR with its display width.
 *
 * TIME, TIMESTAMP and DATETIME take a fractional seconds precision from 0 to
 * 6. YEAR takes a display width and, by the grammar, numeric attributes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-type-syntax.html.
 *
 * @visibility public
 * @example Reading a temporal type
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Temporal(\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::DateTime, '6');
 *     [$type->name(), $type->precision] // => ['DATETIME', '6']
 */
final class Temporal implements DeclaredName
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<NumericModifier> The numeric attributes written after YEAR, in the order written
     */
    public readonly array $modifiers;

    /**
     * @param TemporalKind $kind The temporal type
     * @param string|null $precision The fractional seconds precision, or the display width of YEAR, exactly as written; DATE takes none
     * @param list<NumericModifier> $modifiers The numeric attributes written after YEAR
     */
    public function __construct(public readonly TemporalKind $kind, public readonly ?string $precision = null, array $modifiers = [], public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($precision === null || $kind !== TemporalKind::Date, 'DATE takes no precision.');
        Check::input($precision === null || preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)\z/', $precision) === 1, 'A precision is an unsigned number.');
        Check::input($precision === null || $kind === TemporalKind::Year || (new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral($precision))->isIntegerToken(), 'A fractional seconds precision is a decimal NUM integer token.');
        $this->modifiers = Check::listOf($modifiers, NumericModifier::class, 'Numeric attributes are SIGNED, UNSIGNED and ZEROFILL.');
        Check::input($this->modifiers === [] || $kind === TemporalKind::Year, 'Only YEAR takes numeric attributes.');
    }

    /**
     * Names the type by its keyword.
     */
    public function name(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the keyword, the precision and the attributes.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
        if ($this->precision !== null) {
            $out->glue()->symbol('(')->spelled($this->precision)->symbol(')');
        }
        foreach ($this->modifiers as $modifier) {
            $out->keyword($modifier->value);
        }
    }
}
