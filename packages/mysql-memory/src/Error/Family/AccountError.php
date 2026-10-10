<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about accounts: users, roles, privileges, passwords and authentication factors.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/access-control.html.
 *
 * @visibility public
 * @example Building the error of a missing user
 *     \MySqlMemory\Error\Family\AccountError::CannotUser->error('DROP USER', "'u'@'%'")->getMessage() // => "Operation DROP USER failed for 'u'@'%'"
 */
enum AccountError: int implements ErrorCode
{
    use CatalogedError;

    case DatabaseAccessDenied = 1044;
    case PasswordNoMatch = 1133;
    case NonexistingGrant = 1141;
    case TableAccessDenied = 1142;
    case IllegalGrantForTable = 1144;
    case NonexistingTableGrant = 1147;
    case SpecificAccessDenied = 1227;
    case RevokeGrants = 1269;
    case HostnameWontWork = 1285;
    case PasswordLength = 1372;
    case CannotUser = 1396;
    case NonexistingRoutineGrant = 1403;
    case CantCreateUserWithGrant = 1410;
    case NoSuchUser = 1449;
    case AccessDeniedNoPassword = 1698;
    case InsecurePlainText = 1759;
    case MustChangePassword = 1820;
    case PasswordFormat = 1827;
    case UserDoesNotExist = 3162;
    case UserAlreadyExists = 3163;
    case UnknownAuthorizationId = 3523;
    case FailedDefaultRoles = 3526;
    case RoleNotGranted = 3530;
    case RenameRole = 3532;
    case IllegalPrivilegeLevel = 3619;
    case UnsupportedGrantAs = 3835;
    case InvalidGrantAs = 3836;
    case RetainEmptyPassword = 3878;
    case IncorrectCurrentPassword = 3891;
    case CurrentPasswordNotRequired = 3893;
    case RetainChangesPlugin = 3894;
    case RetainWithEmptyPassword = 3895;
    case UnregisteredDynamicPrivilege = 3929;
    case InvalidUserAttributeJson = 3982;
    case RoleGrantedToItself = 4027;
    case InvalidFactorPlugin = 4052;
    case PluginOperationUnsupported = 4054;
    case FactorMissing = 4057;
    case FactorPolicyMismatch = 4058;
    case RegistrationNotAllowed = 4060;
    case FactorOrder = 4062;
    case FactorIdentical = 4063;
}
