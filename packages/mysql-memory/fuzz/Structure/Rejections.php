<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Node;

/**
 * Identifies SQL data and runtime conditions outside the direct-construction property.
 *
 * This test policy uses explicit error identities, not SQLSTATE classes or
 * message matching. Unknown errors, outer syntax errors, unsupported implementation
 * paths and operand-shape failures remain counterexamples. It neither modifies
 * the generated structure nor decides which constructors the generator visits.
 */
final class Rejections
{
    /**
     * Names the data interpretation or state dependency, or leaves the rejection unresolved.
     */
    public function reason(SqlError $error, Node $node): ?string
    {
        if ($error->signalled !== null) {
            return 'explicit SQL condition';
        }
        if ($node instanceof ShowParseTree && $error->error === StatementError::ParseError) {
            return 'debug-build capability';
        }
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare && $error->error === StatementError::ParseError) {
            return 'SQL parsed when PREPARE executes';
        }

        return match ($error->error) {
            DataError::InvalidJsonType => 'JSON document value domain',
            DataError::Base64DecodeFailed => 'binary-log payload decoding',
            SchemaError::SrsParseError => 'spatial reference definition interpretation',
            SchemaError::WrongTablespaceName, SchemaError::WrongFileName => 'storage-engine name and file restrictions',
            AdministrationError::ComponentSchemeMissing => 'component URN interpretation',
            QueryError::NoDatabase => 'current database',
            QueryError::BadDatabase, QueryError::UnknownTable, QueryError::NoSuchTable, QueryError::BadField,
            SchemaError::SchemaMissing, SchemaError::DatabaseMissing, SchemaError::TablespaceMissing, SchemaError::SrsNotFound,
            ProgramError::RoutineMissing, ProgramError::UndeclaredVariable, ProgramError::EventMissing, ProgramError::UndefinedCondition,
            AdministrationError::ForeignServerMissing, AdministrationError::ResourceGroupMissing,
            AdministrationError::UnknownSystemVariable, AdministrationError::UnknownKeyCache,
            SchemaError::UnknownCharacterSet, SchemaError::UnknownCollation => 'name resolution in the current catalog',
            AccountError::PasswordNoMatch, AccountError::CannotUser,
            AccountError::AccessDeniedNoPassword, AccountError::UnknownAuthorizationId,
            AccountError::FactorMissing, AccountError::RevokeGrants, AccountError::CantCreateUserWithGrant => 'accounts and privileges',
            StatementError::UnknownStatementHandler => 'prepared statement registry',
            StatementError::XaUnknownXid, StatementError::XaWrongState => 'transaction state',
            ProgramError::ResignalWithoutHandler => 'active condition handler',
            AdministrationError::NoSuchThread, StatementError::ExplainNotSupported => 'connection registry and running statement',
            AdministrationError::ReplicaNotConfigured, AdministrationError::ReplicaNotInitialized,
            AdministrationError::GroupReplicationNotConfigured, AdministrationError::NoBinaryLogging,
            AdministrationError::BinlogClosed, AdministrationError::ReplicaChannelMissing => 'replication configuration',
            AdministrationError::CantOpenLibrary, AdministrationError::PluginIsNotLoaded,
            AdministrationError::ComponentNotLoaded, AdministrationError::KeyringReloadFailed => 'installed libraries and components',
            AdministrationError::VariableNotPersisted => 'persisted configuration',
            StatementError::HypergraphRequired => 'optimizer configuration',
            AdministrationError::InvalidCpuId => 'available processors',
            AdministrationError::InvalidThreadPriority => 'thread-priority capability',
            SchemaError::IllegalCreateOption, AdministrationError::FeatureUnsupported => 'selected storage engine capabilities',
            default => null,
        };
    }
}
