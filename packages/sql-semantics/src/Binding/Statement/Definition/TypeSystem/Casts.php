<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Targets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CastIdentity;
use SqlSemantics\Model\Definition\Catalog\TransformIdentity;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastMechanism;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds CREATE CAST, DROP CAST, CREATE TRANSFORM, and DROP TRANSFORM.
 * @visibility SqlSemantics
 */
final class Casts
{
    /**
     * A cast converts through a function, through text I/O, or by binary coercion.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        [$from, $to] = self::types($source);
        $cast = CastContext::from(implode(' ', DefinitionWords::of(Tree::child($source, ['cast_context']))));
        $function = Tree::child($source, ['function_with_argtypes']);
        if ($function !== null) {
            return new Statement\CreateFunctionCastStatement($origin, $from, $to, Targets::routine($function, $context), $cast);
        }
        $mechanism = in_array('INOUT', DefinitionWords::of($source), true) ? CastMechanism::InOut : CastMechanism::BinaryCoercible;
        try {
            return new Statement\CreateCastStatement($origin, $from, $to, $mechanism, $cast);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CastDefinition, $source, $error);
        }
    }

    /**
     * DROP CAST addresses the cast by its source and target types.
     * @throws UnclassifiedSql
     */
    public static function drop(Origin $origin, Node $source): Statement\DropCastStatement
    {
        [$from, $to] = self::types($source);
        return new Statement\DropCastStatement($origin, new CastIdentity($from, $to), Tree::child($source, ['opt_if_exists']) !== null, Domains::behavior($source));
    }

    /**
     * A transform names a FROM SQL function, a TO SQL function, or both, in either order.
     * @throws UnclassifiedSql
     */
    public static function transform(Origin $origin, Node $source, QueryContext $context): Statement\CreateTransformStatement
    {
        $elements = Tree::child($source, ['transform_element_list']) ?? throw new UnclassifiedSql('A transform requires its functions.');
        $fromSql = null;
        $toSql = null;
        $direction = '';
        foreach ($elements->children as $child) {
            if ($child instanceof Node && $child->name === 'function_with_argtypes') {
                $fromSql = $direction === 'FROM' ? Targets::routine($child, $context) : $fromSql;
                $toSql = $direction === 'TO' ? Targets::routine($child, $context) : $toSql;
                continue;
            }
            $word = $child instanceof Node ? '' : strtoupper($child->text);
            $direction = in_array($word, ['FROM', 'TO'], true) ? $word : $direction;
        }
        return new Statement\CreateTransformStatement($origin, self::types($source)[0], self::language($source, $context), $fromSql, $toSql, Tree::child($source, ['opt_or_replace']) !== null);
    }

    /**
     * DROP TRANSFORM addresses the transform by its type and language.
     * @throws UnclassifiedSql
     */
    public static function dropTransform(Origin $origin, Node $source, QueryContext $context): Statement\DropTransformStatement
    {
        return new Statement\DropTransformStatement($origin, new TransformIdentity(self::types($source)[0], self::language($source, $context)), Tree::child($source, ['opt_if_exists']) !== null, Domains::behavior($source));
    }

    /**
     * The type declarations written directly in the statement, outside any function signature.
     * @return non-empty-list<TypeDescriptor>
     * @throws UnclassifiedSql
     */
    public static function types(Node $source): array
    {
        $types = [];
        foreach ($source->children as $child) {
            if ($child instanceof Node && $child->name === 'Typename') {
                $types[] = (new TypeReader(Dialect::PostgreSql))->read($child);
            }
        }
        return $types === [] ? throw new UnclassifiedSql('The statement requires a type.') : $types;
    }

    /**
     * The procedural language of a transform.
     * @throws UnclassifiedSql
     */
    public static function language(Node $source, QueryContext $context): string
    {
        return $context->tables->identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A transform requires its language.'))->tokens()[0]);
    }
}
