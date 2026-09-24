<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Routines;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Writes cast and transform definitions and removals.
 * @visibility SqlSemantics
 */
final class Casts
{
    /**
     * Returns null for statements outside these forms.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateCastStatement => new Tree('create-cast', [Build::keyword('CREATE CAST'), self::signature($statement->sourceType, $statement->targetType), Build::keyword($statement->mechanism->value), Build::keyword($statement->castContext->value)]),
            $statement instanceof Statement\CreateFunctionCastStatement => new Tree('create-cast', [Build::keyword('CREATE CAST'), self::signature($statement->sourceType, $statement->targetType), Build::keyword('WITH FUNCTION'), Routines::routine($statement->function), Build::keyword($statement->castContext->value)]),
            $statement instanceof Statement\DropCastStatement => new Tree('drop-cast', [Build::keyword('DROP CAST' . ($statement->ifExists ? ' IF EXISTS' : '')), self::signature($statement->cast->source, $statement->cast->target), Build::keyword($statement->behavior->value)]),
            $statement instanceof Statement\CreateTransformStatement => new Tree('create-transform', [Build::keyword($statement->orReplace ? 'CREATE OR REPLACE TRANSFORM FOR' : 'CREATE TRANSFORM FOR'), TypeDeclaration::write($statement->type), Build::keyword('LANGUAGE'), Build::identifier([$statement->language], Dialect::PostgreSql), Build::parentheses(Build::separated([...self::function('FROM', $statement->fromSql), ...self::function('TO', $statement->toSql)]))]),
            $statement instanceof Statement\DropTransformStatement => new Tree('drop-transform', [Build::keyword('DROP TRANSFORM' . ($statement->ifExists ? ' IF EXISTS' : '') . ' FOR'), TypeDeclaration::write($statement->transform->type), Build::keyword('LANGUAGE'), Build::identifier([$statement->transform->language], Dialect::PostgreSql), Build::keyword($statement->behavior->value)]),
            default => null,
        };
    }

    /**
     * The parenthesized source and target types.
     * @throws InvalidStructure
     */
    public static function signature(TypeDescriptor $source, TypeDescriptor $target): Tree
    {
        return Build::parentheses(new Tree('cast-signature', [TypeDeclaration::write($source), Build::keyword('AS'), TypeDeclaration::write($target)]));
    }

    /**
     * One FROM SQL or TO SQL element of a transform, when present.
     * @return list<Tree>
     */
    public static function function(string $direction, Routine\RoutineByName|Routine\RoutineBySignature|null $function): array
    {
        return $function === null ? [] : [new Tree('transform-function', [Build::keyword($direction . ' SQL WITH FUNCTION'), Routines::routine($function)])];
    }
}
