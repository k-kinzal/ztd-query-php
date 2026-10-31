<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

/**
 * The written cast request: CAST ... AS or typed CONVERT.
 *
 * CAST and CONVERT have the same conversion semantics but distinct
 * reconstruction and item names. `CAST ... AT LOCAL AS` is not a form: no
 * release executes it, the server refuses it while parsing
 * (ER_NOT_SUPPORTED_YET), so the lowering refuses the input.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility public
 * @example Naming the typed CONVERT request
 *     \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm::Convert->name // => 'Convert'
 */
enum CastForm
{
    case Cast;
    case Convert;
}
