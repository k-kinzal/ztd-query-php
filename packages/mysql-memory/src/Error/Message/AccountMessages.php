<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Message;

/**
 * SQLSTATE and message definitions owned by the AccountError cases.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * @visibility MySqlMemory\Error
 */
final class AccountMessages
{
    /**
     * @var array<string, array{string, string}> SQLSTATE and format, indexed by the owning enum case
     */
    public const DEFINITIONS = [
        'DatabaseAccessDenied' => ['42000', 'Access denied for user \'%s\'@\'%s\' to database \'%s\''],
        'PasswordNoMatch' => ['42000', 'Can\'t find any matching row in the user table'],
        'NonexistingGrant' => ['42000', 'There is no such grant defined for user \'%s\' on host \'%s\''],
        'TableAccessDenied' => ['42000', '%s command denied to user \'%s\'@\'%s\' for table \'%s\''],
        'IllegalGrantForTable' => ['42000', 'Illegal GRANT/REVOKE command; please consult the manual to see which privileges can be used'],
        'NonexistingTableGrant' => ['42000', 'There is no such grant defined for user \'%s\' on host \'%s\' on table \'%s\''],
        'SpecificAccessDenied' => ['42000', 'Access denied; you need (at least one of) the %s privilege(s) for this operation'],
        'RevokeGrants' => ['HY000', 'Can\'t revoke all privileges for one or more of the requested users'],
        'HostnameWontWork' => ['HY000', 'MySQL is started in --skip-name-resolve mode; you must restart it without this switch for this grant to work'],
        'PasswordLength' => ['HY000', 'Password hash should be a %d-digit hexadecimal number'],
        'CannotUser' => ['HY000', 'Operation %s failed for %s'],
        'NonexistingRoutineGrant' => ['42000', 'There is no such grant defined for user \'%s\' on host \'%s\' on routine \'%s\''],
        'CantCreateUserWithGrant' => ['42000', 'You are not allowed to create a user with GRANT'],
        'NoSuchUser' => ['HY000', 'The user specified as a definer (\'%s\'@\'%s\') does not exist'],
        'AccessDeniedNoPassword' => ['28000', 'Access denied for user \'%s\'@\'%s\''],
        'InsecurePlainText' => ['HY000', 'Sending passwords in plain text without SSL/TLS is extremely insecure.'],
        'MustChangePassword' => ['HY000', 'You must reset your password using %s statement before executing this statement.'],
        'PasswordFormat' => ['HY000', 'The password hash doesn\'t have the expected format.'],
        'UserDoesNotExist' => ['HY000', 'Authorization ID %s does not exist.'],
        'UserAlreadyExists' => ['HY000', 'Authorization ID %s already exists.'],
        'UnknownAuthorizationId' => ['HY000', 'Unknown authorization ID %s'],
        'FailedRoleGrant' => ['HY000', 'Failed to grant %s` to %s'],
        'FailedDefaultRoles' => ['HY000', 'Failed to set default roles'],
        'RoleNotGranted' => ['HY000', '%s is not granted to %s'],
        'RenameRole' => ['HY000', 'Renaming of a role identifier is forbidden'],
        'IllegalPrivilegeLevel' => ['HY000', 'Illegal privilege level specified for %s'],
        'UnsupportedGrantAs' => ['HY000', 'GRANT ... AS is currently supported only for global privileges.'],
        'InvalidGrantAs' => ['HY000', 'Either some of the authorization IDs in the AS clause are invalid or the current user lacks privileges to execute the statement.'],
        'AnonymousRoleGrantee' => ['HY000', 'Cannot grant roles to an anonymous user.'],
        'RetainEmptyPassword' => ['HY000', 'Empty password can not be retained as second password for user \'%s\'@\'%s\'.'],
        'IncorrectCurrentPassword' => ['HY000', 'Incorrect current password. Specify the correct password which has to be replaced.'],
        'CurrentPasswordNotRequired' => ['HY000', 'Do not specify the current password while changing it for other users.'],
        'RetainChangesPlugin' => ['HY000', 'Current password can not be retained for user \'%s\'@\'%s\' because authentication plugin is being changed.'],
        'RetainWithEmptyPassword' => ['HY000', 'Current password can not be retained for user \'%s\'@\'%s\' because new password is empty.'],
        'UnregisteredDynamicPrivilege' => ['HY000', 'Dynamic privilege \'%s\' is not registered with the server.'],
        'InvalidUserAttributeJson' => ['HY000', 'The user attribute must be a valid JSON object'],
        'ReferencedAsDefiner' => ['HY000', 'User \'%s\'@\'%s\' is referenced as a definer account in %s.'],
        'RoleGrantedToItself' => ['HY000', 'User account %s is directly or indirectly granted to the role %s. The GRANT would create a loop'],
        'InvalidFactorPlugin' => ['HY000', 'Invalid plugin "%s" specified as %s factor during "%s".'],
        'PluginOperationUnsupported' => ['HY000', 'This operation is not supported for plugin "%s".'],
        'FactorMissing' => ['HY000', '%s factor authentication method doesn\'t exist. Please do ALTER USER... ADD %s factor... before doing this operation.'],
        'FactorPolicyMismatch' => ['HY000', '%s factor authentication method does not match against authentication policy. Please refer @@authentication_policy system variable.'],
        'RegistrationNotAllowed' => ['HY000', 'The registration operation is not allowed for user \'%s\'@\'%s\'. The operation can only be performed in user\'s own session.'],
        'FactorOrder' => ['HY000', '%s factor authentication method should be added before %s factor authentication method.'],
        'FactorIdentical' => ['HY000', 'Authentication factor should be different.'],
    ];
}
