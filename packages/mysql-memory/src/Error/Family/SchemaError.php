<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about schema objects: databases, tables, columns, keys, constraints, views, partitions, tablespaces and spatial reference systems.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-data-definition-statements.html.
 *
 * @visibility public
 * @example Building the error of an existing table
 *     \MySqlMemory\Error\Family\SchemaError::TableExists->error('t')->getMessage() // => "Table 't' already exists"
 */
enum SchemaError: int implements ErrorCode
{
    use CatalogedError;

    case DatabaseExists = 1007;
    case DatabaseMissing = 1008;
    case FileNotFound = 1017;
    case IllegalHa = 1031;
    case TableExists = 1050;
    case BadTable = 1051;
    case TooLongIdentifier = 1059;
    case DuplicateFieldName = 1060;
    case DuplicateKeyName = 1061;
    case WrongFieldSpec = 1063;
    case InvalidDefault = 1067;
    case MultiplePrimaryKey = 1068;
    case TooManyKeyParts = 1070;
    case TooLongKey = 1071;
    case KeyColumnMissing = 1072;
    case TooBigFieldLength = 1074;
    case WrongAutoKey = 1075;
    case CantRemoveAllFields = 1090;
    case CantDropFieldOrKey = 1091;
    case BlobCantHaveDefault = 1101;
    case WrongDatabaseName = 1102;
    case WrongTableName = 1103;
    case TableMustHaveColumns = 1113;
    case UnknownCharacterSet = 1115;
    case WrongColumnName = 1166;
    case PrimaryCantHaveNull = 1171;
    case KeyMissing = 1176;
    case CantReopenTable = 1137;
    case EngineUnsupportedOperation = 1178;
    case FullTextIndexNotFound = 1191;
    case CollationCharsetMismatch = 1253;
    case UnknownCollation = 1273;
    case UnknownStorageEngine = 1286;
    case ConflictingDeclarations = 1302;
    case WrongObject = 1347;
    case KeyPartZeroLength = 1391;
    case ViewSelectClause = 1350;
    case ViewSelectVariable = 1351;
    case ViewSelectTemporary = 1352;
    case ViewWrongList = 1353;
    case ViewMergeUnavailable = 1354;
    case ViewInvalid = 1356;
    case TooBigScale = 1425;
    case TooBigPrecision = 1426;
    case MBiggerThanD = 1427;
    case TooBigDisplayWidth = 1439;
    case IllegalCreateOption = 1478;
    case FieldNotFoundInPartitionFunction = 1488;
    case PartitionManagementOnNonpartitioned = 1505;
    case FilegroupOptionOnlyOnce = 1527;
    case CreateFilegroupFailed = 1528;
    case DropFilegroupFailed = 1529;
    case WrongSizeNumber = 1531;
    case SizeOverflow = 1532;
    case AlterFilegroupFailed = 1533;
    case UnknownPartition = 1735;
    case PartitionClauseOnNonpartitioned = 1747;
    case UnknownAlterAlgorithm = 1800;
    case UnknownAlterLock = 1801;
    case TablespaceExists = 1813;
    case DuplicateIndex = 1831;
    case AlterOperationNotSupported = 1845;
    case AlterOperationNotSupportedReason = 1846;
    case WrongTablespaceName = 3119;
    case TablespaceNotEmpty = 3120;
    case WrongFileName = 3121;
    case InvalidEncryption = 3184;
    case SchemaMissing = 3503;
    case TablespaceMissing = 3510;
    case SrsParseError = 3517;
    case SrsNotFoundWarning = 3519;
    case PrimaryKeyInvisible = 3522;
    case SrsNotFound = 3548;
    case DuplicateTablespaceFile = 3606;
    case NoSdiFiles = 3608;
    case TablespaceFileMissing = 3629;
    case SrsMissingAttribute = 3708;
    case SrsRepeatedAttribute = 3709;
    case SrsBlankName = 3710;
    case SrsBlankOrganization = 3711;
    case SrsExists = 3712;
    case SrsExistsWarning = 3713;
    case SrsZeroUnmodifiable = 3714;
    case SrsReservedRange = 3715;
    case SrsInvalidCharacter = 3717;
    case SrsAttributeTooLong = 3718;
    case CheckConstraintNotFound = 3821;
    case SecondaryEngineFailed = 3889;
    case ConstraintNotFound = 3940;
    case AutoextendMultiple = 4023;
    case AutoextendSize = 4025;
    case NoVisibleColumn = 4028;
}
