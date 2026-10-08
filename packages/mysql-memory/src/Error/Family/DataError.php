<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about values: NULL in a NOT NULL column, duplicate keys, truncated or out-of-range values, conversions, collations and JSON.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/out-of-range-and-overflow.html.
 *
 * @visibility public
 * @example Building the error of a duplicate key
 *     \MySqlMemory\Error\Family\DataError::DuplicateEntry->error('1', 't.PRIMARY')->getMessage() // => "Duplicate entry '1' for key 't.PRIMARY'"
 */
enum DataError: int implements ErrorCode
{
    use CatalogedError;

    case BadNull = 1048;
    case DuplicateEntry = 1062;
    case InvalidUseOfNull = 1138;
    case RegexpLibraryError = 1139;
    case UncompressedTooBig = 1256;
    case ZlibBufferError = 1258;
    case ZlibDataError = 1259;
    case CutByGroupConcat = 1260;
    case OutOfRange = 1264;
    case DataTruncated = 1265;
    case CantAggregateTwoCollations = 1267;
    case CantAggregateThreeCollations = 1270;
    case CantAggregateCollations = 1271;
    case TruncatedWrongValue = 1292;
    case UnknownTimeZone = 1298;
    case InvalidTimestamp = 1299;
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
    case AesInvalidInitializationVector = 1882;
    case AesInvalidKdfName = 3235;
    case AesInvalidKdfIterations = 3236;
    case AesInvalidKdfOptionSize = 3238;
    case InvalidLogarithmArgument = 3020;
    case IncorrectType = 3064;
    case GeneratedColumnValue = 3105;
    case InvalidJsonText = 3140;
    case InvalidJsonTextInParameter = 3141;
    case InvalidJsonPath = 3143;
    case InvalidJsonCharset = 3144;
    case InvalidJsonType = 3146;
    case InvalidJsonPathWildcard = 3149;
    case JsonVacuousPath = 3153;
    case JsonBadOneOrAll = 3154;
    case NumericJsonValueOutOfRange = 3155;
    case InvalidJsonValueForCast = 3156;
    case JsonDocumentTooDeep = 3157;
    case JsonDocumentNullKey = 3158;
    case InvalidJsonPathArrayCell = 3165;
    case MissingJsonTableValue = 3665;
    case WrongJsonTableValue = 3666;
    case JsonTableValueOutOfRange = 3669;
    case InvalidJsonTypeRequired = 3853;
    case MissingJsonValue = 3966;
    case MultipleJsonValues = 3967;
    case BitwiseOperandsSize = 3513;
    case RegexpError = 3685;
    case RegexpIndexOutOfBounds = 3686;
    case RegexpRuleSyntax = 3688;
    case RegexpBadEscapeSequence = 3689;
    case RegexpMismatchedParenthesis = 3691;
    case RegexpBadInterval = 3692;
    case RegexpMaximumBelowMinimum = 3693;
    case RegexpInvalidBackReference = 3694;
    case RegexpLookBehindLimit = 3695;
    case RegexpMissingCloseBracket = 3696;
    case RegexpInvalidRange = 3697;
    case RegexpStackOverflow = 3698;
    case RegexpTimeOut = 3699;
    case RegexpPatternTooBig = 3700;
    case RegexpInvalidCaptureGroupName = 3887;
    case RegexpInvalidFlag = 3900;
    case RegexpNumberTooBig = 4007;
    case RegexpDefaultLocale = 4077;
    case CheckConstraintViolated = 3819;
    case CharacterSetMismatch = 3995;
    case TimeZoneCast = 3998;
    case InvalidVector = 6138;
    case UnsupportedFunctionCharset = 3987;
}
