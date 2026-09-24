<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes PostgreSQL type system definitions: domains, types, casts, transforms, operators, aggregates, collations, conversions, and text search objects.
 * @visibility SqlSemantics
 */
final class TypeSystemCommands
{
    /**
     * Returns null for other dialects and for statements outside the type system family.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        return match ($source->name) {
            'CreateDomainStmt' => Domains::create($origin, $source, $context),
            'AlterDomainStmt' => Domains::alter($origin, $source, $context),
            'DefineStmt' => Definitions::bind($origin, $source, $context),
            'AlterEnumStmt' => TypeDefinitions::alterEnum($origin, $source, $context),
            'AlterTypeStmt' => TypeOptions::alter($origin, $source, $context),
            'AlterCompositeTypeStmt' => TypeDefinitions::alterComposite($origin, $source, $context),
            'CreateCastStmt' => Casts::create($origin, $source, $context),
            'DropCastStmt' => Casts::drop($origin, $source),
            'CreateTransformStmt' => Casts::transform($origin, $source, $context),
            'DropTransformStmt' => Casts::dropTransform($origin, $source, $context),
            default => self::objects($origin, $source, $context),
        };
    }

    /**
     * Routes the operator, operator class and family, collation, conversion, and text search commands.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function objects(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        return match ($source->name) {
            'AlterOperatorStmt' => Operators::alter($origin, $source, $context),
            'RemoveOperStmt' => Operators::drop($origin, $source, $context),
            'CreateOpClassStmt' => OperatorSets::createClass($origin, $source, $context),
            'CreateOpFamilyStmt' => OperatorSets::createFamily($origin, $source, $context),
            'AlterOpFamilyStmt' => OperatorSets::alterFamily($origin, $source, $context),
            'DropOpClassStmt', 'DropOpFamilyStmt' => OperatorSets::drop($origin, $source, $context),
            'AlterCollationStmt' => Locales::refresh($origin, $source, $context),
            'CreateConversionStmt' => Locales::conversion($origin, $source, $context),
            'AlterTSDictionaryStmt' => TextSearch::alterDictionary($origin, $source, $context),
            'AlterTSConfigurationStmt' => TextSearch::alterConfiguration($origin, $source, $context),
            default => null,
        };
    }
}
