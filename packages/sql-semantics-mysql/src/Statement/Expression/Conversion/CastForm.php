<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

/**
 * The written cast request: CAST ... AS, typed CONVERT, or CAST ... AT LOCAL AS.
 *
 * AT LOCAL retains its unsupported request and all operands. CAST and CONVERT
 * have the same conversion semantics but distinct reconstruction and item names.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility public
 * @example Retaining the unsupported cast request
 *     \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm::AtLocal->name // => 'AtLocal'
 */
enum CastForm
{
    case Cast;
    case Convert;
    case AtLocal;
}
