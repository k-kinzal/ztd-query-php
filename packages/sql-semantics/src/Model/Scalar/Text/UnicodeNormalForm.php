<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

/**
 * A Unicode normalization form of PostgreSQL's NORMALIZE and IS NORMALIZED, spelled as its SQL keyword.
 * @visibility public
 * @example Reading the compatibility composition form
 *     \SqlSemantics\Model\Scalar\Text\UnicodeNormalForm::Nfkc->value // => 'NFKC'
 */
enum UnicodeNormalForm: string
{
    case Nfc = 'NFC';
    case Nfd = 'NFD';
    case Nfkc = 'NFKC';
    case Nfkd = 'NFKD';
}
