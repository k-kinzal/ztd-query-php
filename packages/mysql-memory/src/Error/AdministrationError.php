<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

/**
 * A server error about administration: system variables, replication and binary logs, plugins and components, keyrings, resource groups, threads, loadable functions and files.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-administration.html.
 *
 * @visibility public
 * @example Building the error of an unknown system variable
 *     \MySqlMemory\Error\AdministrationError::UnknownSystemVariable->error('nope')->getMessage() // => "Unknown system variable 'nope'"
 */
enum AdministrationError: int implements ErrorCode
{
    use CatalogedError;

    case FileStat = 13;
    case GetErrno = 1030;
    case NoSuchThread = 1094;
    case PathsForbidden = 1124;
    case FunctionExists = 1125;
    case CantOpenLibrary = 1126;
    case FunctionNotDefined = 1128;
    case UnknownSystemVariable = 1193;
    case ReplicaNotConfigured = 1200;
    case CommandFailed = 1220;
    case LocalVariable = 1228;
    case GlobalVariable = 1229;
    case NoDefault = 1230;
    case WrongValueForVariable = 1231;
    case WrongTypeForVariable = 1232;
    case VariableCantBeRead = 1233;
    case IncorrectGlobalLocalVariable = 1238;
    case BadReplicaUntil = 1277;
    case UnknownKeyCache = 1284;
    case UnknownTargetBinlog = 1373;
    case ForeignServerExists = 1476;
    case ForeignServerMissing = 1477;
    case PluginIsNotLoaded = 1524;
    case NoFormatDescriptionEvent = 1609;
    case PermanentPlugin = 1619;
    case VariableIsReadonly = 1621;
    case HeartbeatOutOfRange = 1624;
    case SourceDelayOutOfRange = 1729;
    case BinlogEventRefused = 1730;
    case SqlThreadWithCredentials = 1763;
    case ReplicaNotInitialized = 1794;
    case ActiveLogNotPurged = 1868;
    case SourcePasswordTooLong = 3056;
    case WildTableFilterPattern = 3067;
    case ReplicaChannelMissing = 3074;
    case ReplicaChannelRunning = 3081;
    case ReplicaThreadsStopped = 3084;
    case GroupReplicationNotConfigured = 3092;
    case KeyringMissing = 3185;
    case ComponentSchemeMissing = 3527;
    case ComponentSchemeUnserviced = 3528;
    case ComponentCantLoad = 3529;
    case ComponentNotLoaded = 3537;
    case ComponentNotPersisted = 3542;
    case BinlogIndexOutOfRange = 3567;
    case VariableNotPersisted = 3615;
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
    case BinlogEncryptionOff = 3805;
    case RowFormatValue = 3945;
    case GroupPasswordTooLong = 3972;
    case KeyringReloadFailed = 4035;
}
