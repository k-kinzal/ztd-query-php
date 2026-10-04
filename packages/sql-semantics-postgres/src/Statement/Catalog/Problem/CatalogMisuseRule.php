<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem;

/**
 * The rules of PostgreSQL a grammatical catalog command can break, in the words of the server.
 *
 * A `%s` in a message stands for a name or text the problem is about.
 * Source: https://www.postgresql.org/docs/17/sql-createschema.html, https://www.postgresql.org/docs/17/sql-createdatabase.html,
 * https://www.postgresql.org/docs/17/sql-alterdatabase.html, https://www.postgresql.org/docs/17/sql-createextension.html,
 * https://www.postgresql.org/docs/17/sql-altertablespace.html, https://www.postgresql.org/docs/17/sql-createpublication.html,
 * https://www.postgresql.org/docs/17/sql-alterpublication.html, https://www.postgresql.org/docs/17/sql-altertype.html,
 * https://www.postgresql.org/docs/17/sql-createtype.html.
 *
 * @visibility public
 * @example Reading which rule a command breaks
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER TYPE t DROP VALUE 'a'");
 *     $operation->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule::EnumValueDrop
 */
enum CatalogMisuseRule: string
{
    case SchemaElementsIfNotExists = 'CREATE SCHEMA IF NOT EXISTS cannot include schema elements';
    case ReservedSchemaName = 'unacceptable schema name "%s"';
    case ElementSchemaMismatch = 'CREATE specifies a schema (%s) different from the one being created (%s)';
    case RedundantOptions = 'conflicting or redundant options';
    case UnknownOption = 'option "%s" not recognized';
    case ExclusiveOption = 'option "%s" cannot be specified with other options';
    case ExtensionFrom = 'CREATE EXTENSION ... FROM is no longer supported';
    case ResetWithValues = 'RESET must not include values for parameters';
    case PublicationListStart = 'invalid publication object list';
    case PublicationTableName = 'invalid table name';
    case PublicationSchemaName = 'invalid schema name';
    case PublicationSchemaWhere = 'WHERE clause not allowed for schema';
    case PublicationSchemaColumns = 'column specification not allowed for schema';
    case PublicationDropWhere = 'cannot use a WHERE clause when removing a table from a publication';
    case PublicationDropColumns = 'column list must not be specified in ALTER PUBLICATION ... DROP';
    case PublicationMissingColumn = 'column "%s" of relation "%s" does not exist';
    case PublicationSystemColumn = 'cannot use system column "%s" in publication column list';
    case PublicationDuplicateColumn = 'duplicate column "%s" in publication column list';
    case EnumValueDrop = 'dropping an enum value is not implemented';
    case EnumLabelLength = 'invalid enum label "%s"';
    case RangeAttribute = 'type attribute "%s" not recognized';
    case RangeSubtype = 'type attribute "subtype" is required';
    case AttributeTwice = 'column "%s" specified more than once';
    case DomainUnique = 'unique constraints not possible for domains';
    case DomainPrimaryKey = 'primary key constraints not possible for domains';
    case DomainExclusion = 'exclusion constraints not possible for domains';
    case DomainForeignKey = 'foreign key constraints not possible for domains';
    case DomainDeferrability = 'specifying constraint deferrability not supported for domains';
    case DomainGenerated = 'specifying GENERATED not supported for domains';
    case DomainNullConflict = 'conflicting NULL/NOT NULL constraints';
    case DomainDefaults = 'multiple default expressions';
    case DomainCheckNoInherit = 'check constraints for domains cannot be marked NO INHERIT';
    case MarkedDeferrable = '%s constraints cannot be marked DEFERRABLE';
    case MarkedNotValid = '%s constraints cannot be marked NOT VALID';
    case ImproperName = 'improper qualified name (too many dotted names): %s';
}
