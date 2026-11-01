<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

/**
 * The kind of the value a SET_VAR hint assigns, as the hint comment writes it.
 *
 * Integer: decimal digits. Decimal: digits with a fractional part (`1.5`,
 * `.5`). Word: an identifier, such as `ON`, `16M` or `1e5`, which the
 * variable reads as a string. Text: a quoted string. The value is a scalar;
 * no expression, sign or variable is read (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-set-var.
 *
 * @visibility public
 * @example Reading the kind of a value
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral(\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind::Word, 'OFF'))->kind // => \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind::Word
 */
enum HintLiteralKind
{
    case Integer;
    case Decimal;
    case Word;
    case Text;
}
