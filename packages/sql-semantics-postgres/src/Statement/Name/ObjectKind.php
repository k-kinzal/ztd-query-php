<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

/**
 * The kind of catalog object a generic object command addresses.
 *
 * Mirrors PostgreSQL's `ObjectType`: DROP, COMMENT, SECURITY LABEL, ALTER ...
 * RENAME, OWNER TO, SET SCHEMA, DEPENDS ON EXTENSION, GRANT and ALTER
 * EXTENSION are one node each with an object kind, not one node per kind.
 * Source: https://www.postgresql.org/docs/17/sql-commands.html.
 *
 * @visibility public
 * @example Spelling an object kind
 *     \SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::MaterializedView->keywords() // => ['MATERIALIZED', 'VIEW']
 */
enum ObjectKind: string
{
    case AccessMethod = 'ACCESS METHOD';
    case Aggregate = 'AGGREGATE';
    case Cast = 'CAST';
    case Collation = 'COLLATION';
    case Column = 'COLUMN';
    case Constraint = 'CONSTRAINT';
    case Conversion = 'CONVERSION';
    case Database = 'DATABASE';
    case Domain = 'DOMAIN';
    case DomainConstraint = 'CONSTRAINT ON DOMAIN';
    case EventTrigger = 'EVENT TRIGGER';
    case Extension = 'EXTENSION';
    case ForeignDataWrapper = 'FOREIGN DATA WRAPPER';
    case ForeignServer = 'SERVER';
    case ForeignTable = 'FOREIGN TABLE';
    case Function = 'FUNCTION';
    case Index = 'INDEX';
    case Language = 'LANGUAGE';
    case LargeObject = 'LARGE OBJECT';
    case MaterializedView = 'MATERIALIZED VIEW';
    case OperatorClass = 'OPERATOR CLASS';
    case Operator = 'OPERATOR';
    case OperatorFamily = 'OPERATOR FAMILY';
    case Parameter = 'PARAMETER';
    case Policy = 'POLICY';
    case Procedure = 'PROCEDURE';
    case Publication = 'PUBLICATION';
    case Role = 'ROLE';
    case Routine = 'ROUTINE';
    case Rule = 'RULE';
    case Schema = 'SCHEMA';
    case Sequence = 'SEQUENCE';
    case Statistics = 'STATISTICS';
    case Subscription = 'SUBSCRIPTION';
    case Table = 'TABLE';
    case Tablespace = 'TABLESPACE';
    case TextSearchConfiguration = 'TEXT SEARCH CONFIGURATION';
    case TextSearchDictionary = 'TEXT SEARCH DICTIONARY';
    case TextSearchParser = 'TEXT SEARCH PARSER';
    case TextSearchTemplate = 'TEXT SEARCH TEMPLATE';
    case Transform = 'TRANSFORM';
    case Trigger = 'TRIGGER';
    case Type = 'TYPE';
    case UserMapping = 'USER MAPPING';
    case View = 'VIEW';

    /**
     * Answers the keywords that spell the kind in an object command.
     *
     * @return non-empty-list<string>
     */
    public function keywords(): array
    {
        return explode(' ', $this->value);
    }
}
