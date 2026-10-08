<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

/**
 * A server error about values: NULL in a NOT NULL column, duplicate keys, truncated or out-of-range values, conversions, collations and JSON.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/out-of-range-and-overflow.html.
 *
 * @visibility public
 * @example Building the error of a duplicate key
 *     \MySqlMemory\Error\DataError::DuplicateEntry->error('1', 't.PRIMARY')->getMessage() // => "Duplicate entry '1' for key 't.PRIMARY'"
 */
enum DataError: int implements ErrorCode
{
    use CatalogedError;

    case BadNull = 1048;
    case DuplicateEntry = 1062;
    case InvalidUseOfNull = 1138;
    case CutByGroupConcat = 1260;
    case OutOfRange = 1264;
    case DataTruncated = 1265;
    case CantAggregateTwoCollations = 1267;
    case CantAggregateThreeCollations = 1270;
    case CantAggregateCollations = 1271;
    case TruncatedWrongValue = 1292;
    case UnknownTimeZone = 1298;
    case InvalidCharacterString = 1300;
    case AllowedPacketOverflowed = 1301;
    case NoDefaultForField = 1364;
    case DivisionByZero = 1365;
    case TruncatedWrongValueForField = 1366;
    case DataTooLong = 1406;
    case WrongValueForType = 1411;
    case DatetimeFunctionOverflow = 1441;
    case WrongStringLength = 1470;
    case WrongValue = 1525;
    case Base64DecodeFailed = 1575;
    case NonAsciiSeparator = 1638;
    case DataOutOfRange = 1690;
    case InvalidLogarithmArgument = 3020;
    case GeneratedColumnValue = 3105;
    case InvalidJsonText = 3140;
    case InvalidJsonTextInParameter = 3141;
    case InvalidJsonPath = 3143;
    case InvalidJsonCharset = 3144;
    case InvalidJsonType = 3146;
    case JsonDocumentTooDeep = 3157;
    case BitwiseOperandsSize = 3513;
    case RegexpError = 3685;
    case CheckConstraintViolated = 3819;
    case CharacterSetMismatch = 3995;
    case TimeZoneCast = 3998;
}
