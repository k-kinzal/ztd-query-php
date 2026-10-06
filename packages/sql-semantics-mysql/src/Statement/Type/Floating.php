<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * An approximate numeric type: FLOAT, REAL or DOUBLE with its optional precision and scale.
 *
 * `DOUBLE PRECISION` is `DOUBLE`. `FLOAT(p)` selects single or double
 * precision by `p`; `FLOAT(M,D)`, `REAL(M,D)` and `DOUBLE(M,D)` are the
 * nonstandard display forms. REAL is kept as its own kind because the
 * session mode REAL_AS_FLOAT, which the language profile does not fix,
 * decides whether it is DOUBLE or FLOAT.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/floating-point-types.html.
 *
 * @visibility public
 * @example Reading an approximate type
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Floating(\SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Float, '7', '3');
 *     [$type->name(), $type->precision, $type->scale] // => ['FLOAT', '7', '3']
 */
final class Floating implements TypeName
{
    use Snapshot;

    /**
     * @var list<NumericModifier> The attributes in the order written; an attribute may repeat
     */
    public readonly array $modifiers;

    /**
     * @param FloatingKind $kind The approximate type
     * @param string|null $precision The precision or display length exactly as written
     * @param string|null $scale The number of decimals exactly as written; requires a precision
     * @param list<NumericModifier> $modifiers The attributes in the order written
     */
    public function __construct(public readonly FloatingKind $kind, public readonly ?string $precision = null, public readonly ?string $scale = null, array $modifiers = [])
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
        return $this->kind->value;
    }

    /**
     * Writes the keyword, the precision and scale, and the attributes.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
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
