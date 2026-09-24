<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Conversion;

/**
 * A PostgreSQL character set encoding, spelled by its canonical server name.
 * @visibility public
 * @example Reading the encodings of a conversion
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'unicode' TO 'iso-8859-1' FROM utf8_to_iso8859_1");
 *     $statement->sourceEncoding // => \SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding::Utf8
 *     $statement->targetEncoding // => \SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding::Latin1
 */
enum ServerEncoding: string
{
    case SqlAscii = 'SQL_ASCII';
    case EucJp = 'EUC_JP';
    case EucCn = 'EUC_CN';
    case EucKr = 'EUC_KR';
    case EucTw = 'EUC_TW';
    case EucJis2004 = 'EUC_JIS_2004';
    case Utf8 = 'UTF8';
    case MuleInternal = 'MULE_INTERNAL';
    case Latin1 = 'LATIN1';
    case Latin2 = 'LATIN2';
    case Latin3 = 'LATIN3';
    case Latin4 = 'LATIN4';
    case Latin5 = 'LATIN5';
    case Latin6 = 'LATIN6';
    case Latin7 = 'LATIN7';
    case Latin8 = 'LATIN8';
    case Latin9 = 'LATIN9';
    case Latin10 = 'LATIN10';
    case Win1256 = 'WIN1256';
    case Win1258 = 'WIN1258';
    case Win866 = 'WIN866';
    case Win874 = 'WIN874';
    case Koi8R = 'KOI8R';
    case Win1251 = 'WIN1251';
    case Win1252 = 'WIN1252';
    case Iso88595 = 'ISO_8859_5';
    case Iso88596 = 'ISO_8859_6';
    case Iso88597 = 'ISO_8859_7';
    case Iso88598 = 'ISO_8859_8';
    case Win1250 = 'WIN1250';
    case Win1253 = 'WIN1253';
    case Win1254 = 'WIN1254';
    case Win1255 = 'WIN1255';
    case Win1257 = 'WIN1257';
    case Koi8U = 'KOI8U';
    case Sjis = 'SJIS';
    case Big5 = 'BIG5';
    case Gbk = 'GBK';
    case Uhc = 'UHC';
    case Gb18030 = 'GB18030';
    case Johab = 'JOHAB';
    case ShiftJis2004 = 'SHIFT_JIS_2004';
}
