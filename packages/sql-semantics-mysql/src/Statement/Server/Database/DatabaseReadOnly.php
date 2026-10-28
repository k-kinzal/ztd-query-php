<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;

/**
 * `READ ONLY [=] {DEFAULT | 0 | 1}`: whether the database may be modified (ALTER DATABASE, MySQL 8.0.22 and later).
 *
 * The option's converted value is either 0 or 1; DEFAULT means 0. Numeric
 * spelling is retained, including leading zeros and the fractional or exponent
 * suffix the server ignores in this position. The equals sign is optional.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-database.html.
 *
 * @visibility public
 * @example Making a database read-only
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER DATABASE d READ ONLY = 1');
 *     [$alter->toString(), $alter->options[0]->readOnly()] // => ['ALTER DATABASE d READ ONLY = 1', true]
 */
final class DatabaseReadOnly implements DatabaseOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Numeral|null $value A number converted to 0 or 1; null for DEFAULT
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly ?Numeral $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
        Check::input($value === null || (new Magnitudes())->atMost($value, '1'), 'A database READ ONLY option converts to 0 or 1, or uses DEFAULT.');
    }

    /**
     * Tells whether the option makes the database read-only: a number other than 0.
     */
    public function readOnly(): bool
    {
        return $this->value !== null && ltrim($this->value->hexadecimal ? $this->value->text : (string) preg_replace('/[.eE].*\z/s', '', $this->value->text), '0') !== '';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('READ', 'ONLY');
        if ($this->equals) {
            $out->symbol('=');
        }
        if ($this->value === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->node($this->value);
        }
    }
}
