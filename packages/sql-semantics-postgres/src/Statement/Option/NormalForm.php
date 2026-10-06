<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

/**
 * A Unicode normalization form named in `normalize()` and `IS NORMALIZED`.
 *
 * Source: https://www.postgresql.org/docs/17/functions-string.html.
 *
 * @visibility public
 * @example Spelling a normalization form
 *     \SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm::Nfkc->value // => 'NFKC'
 */
enum NormalForm: string
{
    case Nfc = 'NFC';
    case Nfd = 'NFD';
    case Nfkc = 'NFKC';
    case Nfkd = 'NFKD';
}
