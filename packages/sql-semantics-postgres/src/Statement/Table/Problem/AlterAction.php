<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Problem;

/**
 * An ALTER TABLE action as the server names it when the relation kind does not accept it.
 *
 * Mirrors `alter_table_type_to_string` in `tablecmds.c`, which names SET ( attribute options ) and the SET
 * options of an identity column alike.
 *
 * @visibility public
 * @example Naming the action of an ADD COLUMN
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\AlterAction::AddColumn->value // => 'ADD COLUMN'
 */
enum AlterAction: string
{
    case AddColumn = 'ADD COLUMN';
    case SetDefault = 'ALTER COLUMN ... SET DEFAULT';
    case DropNotNull = 'ALTER COLUMN ... DROP NOT NULL';
    case SetNotNull = 'ALTER COLUMN ... SET NOT NULL';
    case SetExpression = 'ALTER COLUMN ... SET EXPRESSION';
    case DropExpression = 'ALTER COLUMN ... DROP EXPRESSION';
    case SetStatistics = 'ALTER COLUMN ... SET STATISTICS';
    case SetColumnAttributes = 'ALTER COLUMN ... SET';
    case ResetOptions = 'ALTER COLUMN ... RESET';
    case SetStorage = 'ALTER COLUMN ... SET STORAGE';
    case SetCompression = 'ALTER COLUMN ... SET COMPRESSION';
    case DropColumn = 'DROP COLUMN';
    case AddConstraint = 'ADD CONSTRAINT';
    case AlterConstraint = 'ALTER CONSTRAINT';
    case ValidateConstraint = 'VALIDATE CONSTRAINT';
    case DropConstraint = 'DROP CONSTRAINT';
    case SetDataType = 'ALTER COLUMN ... SET DATA TYPE';
    case ColumnOptions = 'ALTER COLUMN ... OPTIONS';
    case ClusterOn = 'CLUSTER ON';
    case SetWithoutCluster = 'SET WITHOUT CLUSTER';
    case SetAccessMethod = 'SET ACCESS METHOD';
    case SetLogged = 'SET LOGGED';
    case SetUnlogged = 'SET UNLOGGED';
    case SetWithoutOids = 'SET WITHOUT OIDS';
    case SetTablespace = 'SET TABLESPACE';
    case SetRelationOptions = 'SET';
    case ResetRelationOptions = 'RESET';
    case EnableTrigger = 'ENABLE TRIGGER';
    case EnableAlwaysTrigger = 'ENABLE ALWAYS TRIGGER';
    case EnableReplicaTrigger = 'ENABLE REPLICA TRIGGER';
    case DisableTrigger = 'DISABLE TRIGGER';
    case EnableAllTriggers = 'ENABLE TRIGGER ALL';
    case DisableAllTriggers = 'DISABLE TRIGGER ALL';
    case EnableUserTriggers = 'ENABLE TRIGGER USER';
    case DisableUserTriggers = 'DISABLE TRIGGER USER';
    case EnableRule = 'ENABLE RULE';
    case EnableAlwaysRule = 'ENABLE ALWAYS RULE';
    case EnableReplicaRule = 'ENABLE REPLICA RULE';
    case DisableRule = 'DISABLE RULE';
    case Inherit = 'INHERIT';
    case NoInherit = 'NO INHERIT';
    case Of = 'OF';
    case NotOf = 'NOT OF';
    case ReplicaIdentity = 'REPLICA IDENTITY';
    case EnableRowSecurity = 'ENABLE ROW SECURITY';
    case DisableRowSecurity = 'DISABLE ROW SECURITY';
    case ForceRowSecurity = 'FORCE ROW SECURITY';
    case NoForceRowSecurity = 'NO FORCE ROW SECURITY';
    case Options = 'OPTIONS';
    case AttachPartition = 'ATTACH PARTITION';
    case DetachPartition = 'DETACH PARTITION';
    case DetachPartitionFinalize = 'DETACH PARTITION ... FINALIZE';
    case AddIdentity = 'ALTER COLUMN ... ADD IDENTITY';
    case DropIdentity = 'ALTER COLUMN ... DROP IDENTITY';
}
