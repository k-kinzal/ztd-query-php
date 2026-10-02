<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The exact fixed-point type DECIMAL with its optional precision and scale.
 *
 * `DEC`, `NUMERIC` and `FIXED` are synonyms of `DECIMAL`. A precision without
 * a scale is written `DECIMAL(M)`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fixed-point-types.html.
 *
 * @visibility public
 * @example Reading a fixed-point type
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Decimal('10', '2');
 *     [$type->name(), $type->precision, $type->scale] // => ['DECIMAL', '10', '2']
 */
final class Decimal implements TypeName
{
    use Snapshot;

    /**
     * @var list<NumericModifier> The attributes in the order written; an attribute may repeat
     */
    public readonly array $modifiers;

    /**
     * @param string|null $precision The precision exactly as written
     * @param string|null $scale The scale exactly as written; requires a precision
     * @param list<NumericModifier> $modifiers The attributes in the order written
     */
    public function __construct(public readonly ?string $precision = null, public readonly ?string $scale = null, array $modifiers = [])
    {
        Check::input($precision === null || preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)\z/', $precision) === 1, 'A precision is an unsigned number.');
        Check::input($scale === null || ($precision !== null && preg_match('/\A[0-9]+\z/', $scale) === 1), 'A scale is an unsigned integer written after a precision.');
        $this->modifiers = Check::listOf($modifiers, NumericModifier::class, 'Numeric attributes are SIGNED, UNSIGNED and ZEROFILL.');
    }

    /**
     * Names the type by its keyword.
     */
    public function name(): string
    {
        return 'DECIMAL';
    }

    /**
     * Writes the keyword, the precision and scale, and the attributes.
     */
    public function render(Output $out): void
    {
        $out->keyword('DECIMAL');
        if ($this->precision !== null) {
            $out->glue()->symbol('(')->spelled($this->precision);
            if ($this->scale !== null) {
                $out->symbol(',')->spelled($this->scale);
            }
            $out->symbol(')');
        }
        foreach ($this->modifiers as $modifier) {
            $out->keyword($modifier->value);
        }
    }
}
