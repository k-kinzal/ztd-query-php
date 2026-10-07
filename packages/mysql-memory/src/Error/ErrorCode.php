<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

/**
 * A server error number the emulator raises, with its SQLSTATE and message format.
 *
 * The formats are those of the server error reference; resources/errors.php holds them.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Building the error of a missing table
 *     $error = \MySqlMemory\Error\ErrorCode::NoSuchTable->error('shop', 'items');
 *     [$error->getCode(), $error->sqlState(), $error->getMessage()] // => [1146, '42S02', "Table 'shop.items' doesn't exist"]
 */
enum ErrorCode: int
{
    case DatabaseExists = 1007;
    case DatabaseMissing = 1008;
    case DatabaseAccessDenied = 1044;
    case NoDatabase = 1046;
    case UnknownCommand = 1047;
    case BadNull = 1048;
    case BadDatabase = 1049;
    case TableExists = 1050;
    case BadTable = 1051;
    case NonUniqueColumn = 1052;
    case BadField = 1054;
    case WrongFieldWithGroup = 1055;
    case WrongGroupField = 1056;
    case WrongValueCount = 1058;
    case TooLongIdentifier = 1059;
    case DuplicateFieldName = 1060;
    case DuplicateKeyName = 1061;
    case DuplicateEntry = 1062;
    case WrongFieldSpec = 1063;
    case ParseError = 1064;
    case EmptyQuery = 1065;
    case NonUniqueTable = 1066;
    case InvalidDefault = 1067;
    case MultiplePrimaryKey = 1068;
    case TooLongKey = 1071;
    case KeyColumnMissing = 1072;
    case TooBigFieldLength = 1074;
    case WrongAutoKey = 1075;
    case CantRemoveAllFields = 1090;
    case CantDropFieldOrKey = 1091;
    case UpdateTableUsed = 1093;
    case NoTablesUsed = 1096;
    case BlobCantHaveDefault = 1101;
    case WrongDatabaseName = 1102;
    case WrongTableName = 1103;
    case UnknownError = 1105;
    case UnknownTable = 1109;
    case FieldSpecifiedTwice = 1110;
    case InvalidGroupFunctionUse = 1111;
    case TableMustHaveColumns = 1113;
    case UnknownCharacterSet = 1115;
    case WrongValueCountOnRow = 1136;
    case MixOfGroupFunctionAndFields = 1140;
    case NoSuchTable = 1146;
    case SyntaxError = 1149;
    case WrongColumnName = 1166;
    case PrimaryCantHaveNull = 1171;
    case TooManyRows = 1172;
    case KeyMissing = 1176;
    case UnknownSystemVariable = 1193;
    case WrongArguments = 1210;
    case WrongUsage = 1221;
    case WrongNumberOfColumnsInSelect = 1222;
    case SpecificAccessDenied = 1227;
    case LocalVariable = 1228;
    case GlobalVariable = 1229;
    case NoDefault = 1230;
    case WrongValueForVariable = 1231;
    case WrongTypeForVariable = 1232;
    case VariableCantBeRead = 1233;
    case CantUseOptionHere = 1234;
    case NotSupportedYet = 1235;
    case IncorrectGlobalLocalVariable = 1238;
    case OperandColumns = 1241;
    case SubqueryNotOneRow = 1242;
    case UnknownStatementHandler = 1243;
    case IllegalReference = 1247;
    case DerivedMustHaveAlias = 1248;
    case TableNameNotAllowedHere = 1250;
    case CollationCharsetMismatch = 1253;
    case CutByGroupConcat = 1260;
    case OutOfRange = 1264;
    case DataTruncated = 1265;
    case CantAggregateTwoCollations = 1267;
    case CantAggregateThreeCollations = 1270;
    case CantAggregateCollations = 1271;
    case UnknownCollation = 1273;
    case DeprecatedSyntax = 1287;
    case TruncatedWrongValue = 1292;
    case UnsupportedPreparedStatement = 1295;
    case RoutineMissing = 1305;
    case UndeclaredVariable = 1327;
    case WrongObject = 1347;
    case NoDefaultForField = 1364;
    case DivisionByZero = 1365;
    case TruncatedWrongValueForField = 1366;
    case DataTooLong = 1406;
    case WrongValueForType = 1411;
    case TooBigScale = 1425;
    case TooBigPrecision = 1426;
    case MBiggerThanD = 1427;
    case TooBigDisplayWidth = 1439;
    case DatetimeFunctionOverflow = 1441;
    case NonGroupingFieldUsed = 1463;
    case WrongValue = 1525;
    case WrongParameterCountToNativeFunction = 1582;
    case WrongParametersToNativeFunction = 1583;
    case VariableIsReadonly = 1621;
    case FunctionNameCollision = 1630;
    case DeprecatedSyntaxNoReplacement = 1681;
    case DataOutOfRange = 1690;
    case WrongVariableTypeInLimit = 1691;
    case FieldInOrderNotSelect = 3065;
    case GeneratedColumnValue = 3105;
    case InvalidJsonText = 3140;
    case InvalidJsonTextInParameter = 3141;
    case InvalidJsonPath = 3143;
    case RegexpError = 3685;
    case CheckConstraintViolated = 3819;

    /**
     * Answers the SQLSTATE of the error.
     *
     * @example A duplicate key
     *     \MySqlMemory\Error\ErrorCode::DuplicateEntry->sqlState() // => '23000'
     */
    public function sqlState(): string
    {
        return ErrorCatalog::instance()->entry($this->value)[0];
    }

    /**
     * Answers the message with the arguments filled into the format of the error.
     *
     * @example A missing column
     *     \MySqlMemory\Error\ErrorCode::BadField->message('a', 'field list') // => "Unknown column 'a' in 'field list'"
     */
    public function message(string|int ...$arguments): string
    {
        $format = ErrorCatalog::instance()->entry($this->value)[1];
        if (!str_contains($format, '%') && $arguments !== []) {
            return (string) $arguments[0];
        }

        return vsprintf($format, $arguments);
    }

    /**
     * Answers the error with the arguments filled into its message.
     *
     * @example No database selected
     *     \MySqlMemory\Error\ErrorCode::NoDatabase->error()->getMessage() // => 'No database selected'
     */
    public function error(string|int ...$arguments): SqlError
    {
        return new SqlError($this, $this->message(...$arguments));
    }
}
