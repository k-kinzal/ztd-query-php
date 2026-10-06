<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem;

/**
 * The rules of PostgreSQL a grammatical role or privilege command can break, in the words of the server.
 *
 * A `%s` in a message stands for a name or text the problem is about.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html, https://www.postgresql.org/docs/17/sql-alterrole.html,
 * https://www.postgresql.org/docs/17/sql-droprole.html, https://www.postgresql.org/docs/17/sql-grant.html,
 * https://www.postgresql.org/docs/17/sql-revoke.html, https://www.postgresql.org/docs/17/ddl-priv.html,
 * https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html, https://www.postgresql.org/docs/17/datatype-oid.html.
 *
 * @visibility public
 * @example Reading which rule a command breaks
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP ROLE CURRENT_USER');
 *     $operation->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule::SpecialRoleDrop
 */
enum AccessProblemRule: string
{
    case RedundantOptions = 'conflicting or redundant options';
    case UnrecognizedRoleOption = 'unrecognized role option "%s"';
    case UnencryptedPassword = 'UNENCRYPTED PASSWORD is no longer supported';
    case ReservedRoleName = 'role name "%s" is reserved';
    case InvalidConnectionLimit = 'invalid connection limit: %s';
    case PublicRole = 'role "public" does not exist';
    case SpecialRoleDrop = 'cannot use special role specifier in DROP ROLE';
    case UnrecognizedPrivilege = 'unrecognized privilege type "%s"';
    case InvalidPrivilege = 'invalid privilege type %s for %s';
    case ColumnPrivilegesTarget = 'column privileges are only valid for relations';
    case GrantOptionToPublic = 'grant options can only be granted to roles';
    case RoleColumns = 'column names cannot be included in GRANT/REVOKE ROLE';
    case DefaultColumns = 'default privileges cannot be set for columns';
    case DefaultSchemasInSchema = 'cannot use IN SCHEMA clause when using GRANT/REVOKE ON SCHEMAS';
    case InvalidObjectIdentifier = 'invalid input syntax for type oid: "%s"';
    case ObjectIdentifierRange = 'value "%s" is out of range for type oid';
}
