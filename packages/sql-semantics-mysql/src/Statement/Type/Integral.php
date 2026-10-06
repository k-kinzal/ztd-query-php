<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * An integer type with its optional display width and its attributes in the order written.
 *
 * `INT`, `INTEGER` and `INT4` are one keyword to the server, as are the other
 * numbered spellings; the kind names the type they denote.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/integer-types.html,
 * https://dev.mysql.com/doc/refman/8.4/en/numeric-type-attributes.html.
 *
 * @visibility public
 * @example Reading an integer type
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::BigInt, '20', [\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier::Unsigned]);
 *     [$type->name(), $type->width, $type->unsigned()] // => ['BIGINT', '20', true]
 */
final class Integral implements TypeName
{
    use Snapshot;

    /**
     * @var list<NumericModifier> The attributes in the order written; an attribute may repeat
     */
    public readonly array $modifiers;

    /**
     * @param IntegralKind $kind The integer type
     * @param string|null $width The display width exactly as written
     * @param list<NumericModifier> $modifiers The attributes in the order written
     */
    public function __construct(public readonly IntegralKind $kind, public readonly ?string $width = null, array $modifiers = [])
    {
        Check::input($width === null || preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)\z/', $width) === 1, 'A display width is an unsigned number.');
        $this->modifiers = Check::listOf($modifiers, NumericModifier::class, 'Numeric attributes are SIGNED, UNSIGNED and ZEROFILL.');
    }

    /**
     * Tells whether the type holds no negative value: UNSIGNED or ZEROFILL is written.
     */
    public function unsigned(): bool
    {
        return in_array(NumericModifier::Unsigned, $this->modifiers, true) || in_array(NumericModifier::Zerofill, $this->modifiers, true);
    }

    /**
     * Names the type by its keyword.
     */
    public function name(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the keyword, the display width and the attributes.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
        if ($this->width !== null) {
            $out->glue()->symbol('(')->spelled($this->width)->symbol(')');
        }
        foreach ($this->modifiers as $modifier) {
            $out->keyword($modifier->value);
        }
    }
}
