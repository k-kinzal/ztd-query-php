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
    case FileStat = 13;
    case DatabaseExists = 1007;
    case DatabaseMissing = 1008;
    case GetErrno = 1030;
    case IllegalHa = 1031;
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
    case TooManyKeyParts = 1070;
    case TooLongKey = 1071;
    case KeyColumnMissing = 1072;
    case TooBigFieldLength = 1074;
    case WrongAutoKey = 1075;
    case WrongFieldTerminators = 1083;
    case CantRemoveAllFields = 1090;
    case CantDropFieldOrKey = 1091;
    case UpdateTableUsed = 1093;
    case NoSuchThread = 1094;
    case NoTablesUsed = 1096;
    case TableNotLockedForWrite = 1099;
    case TableNotLocked = 1100;
    case BlobCantHaveDefault = 1101;
    case WrongDatabaseName = 1102;
    case WrongTableName = 1103;
    case UnknownError = 1105;
    case UnknownTable = 1109;
    case FieldSpecifiedTwice = 1110;
    case InvalidGroupFunctionUse = 1111;
    case TableMustHaveColumns = 1113;
    case UnknownCharacterSet = 1115;
    case PathsForbidden = 1124;
    case FunctionExists = 1125;
    case CantOpenLibrary = 1126;
    case FunctionNotDefined = 1128;
    case PasswordNoMatch = 1133;
    case WrongValueCountOnRow = 1136;
    case InvalidUseOfNull = 1138;
    case MixOfGroupFunctionAndFields = 1140;
    case NonexistingGrant = 1141;
    case IllegalGrantForTable = 1144;
    case NoSuchTable = 1146;
    case NonexistingTableGrant = 1147;
    case SyntaxError = 1149;
    case WrongColumnName = 1166;
    case PrimaryCantHaveNull = 1171;
    case TooManyRows = 1172;
    case KeyMissing = 1176;
    case EngineUnsupportedOperation = 1178;
    case LockedOrActiveTransaction = 1192;
    case UnknownSystemVariable = 1193;
    case ReplicaNotConfigured = 1200;
    case FullTextIndexNotFound = 1191;
    case WrongArguments = 1210;
    case CommandFailed = 1220;
    case WrongUsage = 1221;
    case WrongNumberOfColumnsInSelect = 1222;
    case DuplicateArgument = 1225;
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
    case RevokeGrants = 1269;
    case CantAggregateThreeCollations = 1270;
    case CantAggregateCollations = 1271;
    case UnknownCollation = 1273;
    case BadReplicaUntil = 1277;
    case UnknownKeyCache = 1284;
    case HostnameWontWork = 1285;
    case UnknownStorageEngine = 1286;
    case DeprecatedSyntax = 1287;
    case NonUpdatableTable = 1288;
    case OptionPreventsStatement = 1290;
    case TruncatedWrongValue = 1292;
    case UnsupportedPreparedStatement = 1295;
    case UnknownTimeZone = 1298;
    case InvalidCharacterString = 1300;
    case AllowedPacketOverflowed = 1301;
    case FeatureDisabled = 1289;
    case ConflictingDeclarations = 1302;
    case RecursiveCreate = 1303;
    case RoutineExists = 1304;
    case RoutineMissing = 1305;
    case LabelMissing = 1308;
    case LabelRedefined = 1309;
    case EndLabelMismatch = 1310;
    case ReturnOutsideFunction = 1313;
    case ProgramStatement = 1314;
    case RoutineArgumentCount = 1318;
    case UndefinedCondition = 1319;
    case MissingReturn = 1320;
    case FunctionWithoutReturn = 1321;
    case UndefinedCursor = 1324;
    case UndeclaredVariable = 1327;
    case NoData = 1329;
    case DuplicateParameter = 1330;
    case DuplicateVariable = 1331;
    case DuplicateCondition = 1332;
    case DuplicateCursor = 1333;
    case FunctionStatement = 1336;
    case DeclarationAfterHandler = 1337;
    case CursorAfterHandler = 1338;
    case WrongObject = 1347;
    case NonUpdatableColumn = 1348;
    case ViewSelectVariable = 1351;
    case ViewSelectTemporary = 1352;
    case ViewWrongList = 1353;
    case ViewMergeUnavailable = 1354;
    case IncompleteViewKey = 1355;
    case ViewInvalid = 1356;
    case NestedProgramChange = 1357;
    case TriggerExists = 1359;
    case TriggerMissing = 1360;
    case TriggerOnView = 1361;
    case TriggerRowChange = 1362;
    case TriggerRowMissing = 1363;
    case NoDefaultForField = 1364;
    case DivisionByZero = 1365;
    case TruncatedWrongValueForField = 1366;
    case UnknownTargetBinlog = 1373;
    case CannotUser = 1396;
    case XaUnknownXid = 1397;
    case XaInvalidArguments = 1398;
    case XaWrongState = 1399;
    case XaWorkOutside = 1400;
    case NonexistingRoutineGrant = 1403;
    case DataTooLong = 1406;
    case BadSqlState = 1407;
    case CantCreateUserWithGrant = 1410;
    case WrongValueForType = 1411;
    case DuplicateHandler = 1413;
    case ResultSetFromProgram = 1415;
    case UnsafeRoutine = 1418;
    case CommitInFunction = 1422;
    case TooBigScale = 1425;
    case TooBigPrecision = 1426;
    case MBiggerThanD = 1427;
    case TriggerInWrongSchema = 1435;
    case TooBigDisplayWidth = 1439;
    case XaDuplicateXid = 1440;
    case DatetimeFunctionOverflow = 1441;
    case NoSuchUser = 1449;
    case NonGroupingFieldUsed = 1463;
    case WrongStringLength = 1470;
    case NonInsertableTable = 1471;
    case ForeignServerExists = 1476;
    case ForeignServerMissing = 1477;
    case IllegalCreateOption = 1478;
    case FieldNotFoundInPartitionFunction = 1488;
    case PartitionManagementOnNonpartitioned = 1505;
    case PluginIsNotLoaded = 1524;
    case WrongValue = 1525;
    case FilegroupOptionOnlyOnce = 1527;
    case CreateFilegroupFailed = 1528;
    case DropFilegroupFailed = 1529;
    case WrongSizeNumber = 1531;
    case SizeOverflow = 1532;
    case AlterFilegroupFailed = 1533;
    case EventExists = 1537;
    case EventMissing = 1539;
    case IntervalNotPositive = 1542;
    case EndsBeforeStarts = 1543;
    case EventDisabledInPast = 1544;
    case SameEventName = 1551;
    case Base64DecodeFailed = 1575;
    case EventRecursion = 1576;
    case EventDroppedInPast = 1588;
    case WrongParameterCountToNativeFunction = 1582;
    case WrongParametersToNativeFunction = 1583;
    case WrongParametersToStoredFunction = 1584;
    case NoFormatDescriptionEvent = 1609;
    case PermanentPlugin = 1619;
    case VariableIsReadonly = 1621;
    case HeartbeatOutOfRange = 1624;
    case FunctionNameCollision = 1630;
    case NonAsciiSeparator = 1638;
    case DuplicateSignalItem = 1641;
    case SignalWarning = 1642;
    case SignalNotFound = 1643;
    case SignalException = 1644;
    case ResignalWithoutHandler = 1645;
    case SignalConditionKind = 1646;
    case ConditionItemTooLong = 1648;
    case DeprecatedSyntaxNoReplacement = 1681;
    case DataOutOfRange = 1690;
    case WrongVariableTypeInLimit = 1691;
    case AccessDeniedNoPassword = 1698;
    case SourceDelayOutOfRange = 1729;
    case BinlogEventRefused = 1730;
    case UnknownPartition = 1735;
    case PartitionClauseOnNonpartitioned = 1747;
    case InvalidConditionNumber = 1758;
    case InsecurePlainText = 1759;
    case SqlThreadWithCredentials = 1763;
    case UnknownExplainFormat = 1791;
    case ReplicaNotInitialized = 1794;
    case UnknownAlterAlgorithm = 1800;
    case UnknownAlterLock = 1801;
    case TablespaceExists = 1813;
    case PasswordFormat = 1827;
    case DuplicateIndex = 1831;
    case AlterOperationNotSupported = 1845;
    case AlterOperationNotSupportedReason = 1846;
    case ActiveLogNotPurged = 1868;
    case StackedWithoutHandler = 3004;
    case ReferencedTriggerMissing = 3011;
    case ExplainNotSupported = 3012;
    case InvalidLogarithmArgument = 3020;
    case SourcePasswordTooLong = 3056;
    case FieldInOrderNotSelect = 3065;
    case WildTableFilterPattern = 3067;
    case ReplicaChannelMissing = 3074;
    case ReplicaChannelRunning = 3081;
    case ReplicaThreadsStopped = 3084;
    case GroupReplicationNotConfigured = 3092;
    case GeneratedColumnValue = 3105;
    case WrongTablespaceName = 3119;
    case TablespaceNotEmpty = 3120;
    case WrongFileName = 3121;
    case InvalidJsonText = 3140;
    case InvalidJsonTextInParameter = 3141;
    case InvalidJsonPath = 3143;
    case InvalidJsonCharset = 3144;
    case InvalidJsonType = 3146;
    case JsonDocumentTooDeep = 3157;
    case UserDoesNotExist = 3162;
    case UserAlreadyExists = 3163;
    case InvalidEncryption = 3184;
    case KeyringMissing = 3185;
    case SchemaMissing = 3503;
    case TablespaceMissing = 3510;
    case BitwiseOperandsSize = 3513;
    case SrsParseError = 3517;
    case SrsNotFoundWarning = 3519;
    case PrimaryKeyInvisible = 3522;
    case UnknownAuthorizationId = 3523;
    case FailedDefaultRoles = 3526;
    case ComponentSchemeMissing = 3527;
    case ComponentSchemeUnserviced = 3528;
    case ComponentCantLoad = 3529;
    case RoleNotGranted = 3530;
    case RenameRole = 3532;
    case ComponentNotLoaded = 3537;
    case ComponentNotPersisted = 3542;
    case SrsNotFound = 3548;
    case NativeFunctionRejected = 3566;
    case BinlogIndexOutOfRange = 3567;
    case UnresolvedLockedTable = 3568;
    case DuplicateLockedTable = 3569;
    case RecursiveRequiresUnion = 3573;
    case RecursiveRequiresNonrecursiveFirst = 3574;
    case RecursiveWithoutUnion = 3577;
    case WindowNotDefined = 3579;
    case WindowDefinedTwice = 3591;
    case GroupingArgumentNotGrouped = 3602;
    case DuplicateTablespaceFile = 3606;
    case NoSdiFiles = 3608;
    case VariableNotPersisted = 3615;
    case IllegalPrivilegeLevel = 3619;
    case TablespaceFileMissing = 3629;
    case RecursionLimit = 3636;
    case ResourceGroupExists = 3650;
    case ResourceGroupMissing = 3651;
    case InvalidCpuId = 3652;
    case InvalidCpuRange = 3653;
    case InvalidThreadPriority = 3654;
    case OperationDisallowed = 3655;
    case ResourceGroupBusy = 3656;
    case ResourceGroupDisabled = 3657;
    case FeatureUnsupported = 3658;
    case InvalidThreadId = 3660;
    case ResourceGroupBindFailed = 3661;
    case ForceWithoutDisable = 3662;
    case TableFunctionWithoutAlias = 3667;
    case RegexpError = 3685;
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
    case BinlogEncryptionOff = 3805;
    case CheckConstraintViolated = 3819;
    case CheckConstraintNotFound = 3821;
    case InvalidGrantAs = 3836;
    case SecondaryEngineFailed = 3889;
    case IncorrectCurrentPassword = 3891;
    case CurrentPasswordNotRequired = 3893;
    case UnregisteredDynamicPrivilege = 3929;
    case ConstraintNotFound = 3940;
    case ValuesEmptyRow = 3942;
    case ValuesDefault = 3943;
    case RowFormatValue = 3945;
    case LocalInfileDisabled = 3948;
    case GroupPasswordTooLong = 3972;
    case InvalidUserAttributeJson = 3982;
    case CharacterSetMismatch = 3995;
    case TimeZoneCast = 3998;
    case AutoextendMultiple = 4023;
    case AutoextendSize = 4025;
    case RoleGrantedToItself = 4027;
    case NoVisibleColumn = 4028;
    case KeyringReloadFailed = 4035;
    case InvalidFactorPlugin = 4052;
    case FactorMissing = 4057;
    case RegistrationNotAllowed = 4060;
    case FactorOrder = 4062;
    case FactorIdentical = 4063;
    case TriggerExistsOnTable = 4099;
    case TriggerExistsElsewhere = 4100;
    case ExplainIntoImplicitFormat = 6006;
    case ExplainIntoFormat = 6007;
    case ExplainIntoForConnection = 6009;
    case FeatureNotSupported = 6033;
    case HypergraphRequired = 6037;

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
