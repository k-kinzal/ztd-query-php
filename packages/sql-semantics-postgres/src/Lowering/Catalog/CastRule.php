<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastContext;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastConversion;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CreateCast;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CreateTransform;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TransformFor;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the cast and transform commands.
 *
 * Rule: PG-CAST-LOWER-001. Scope: `CreateCastStmt`, `cast_context`,
 * `DropCastStmt`, `CreateTransformStmt`, `transform_element_list`,
 * `DropTransformStmt`. Constructors: `CreateCast`, `CreateTransform`,
 * `TransformFunction`, and the routine family's `Drop` with a
 * `CastObject` or `TransformObject`, because the server represents DROP CAST
 * and DROP TRANSFORM as `DropStmt` of kind OBJECT_CAST and OBJECT_TRANSFORM.
 * Source: https://www.postgresql.org/docs/17/sql-createcast.html, https://www.postgresql.org/docs/17/sql-dropcast.html,
 * https://www.postgresql.org/docs/17/sql-createtransform.html, https://www.postgresql.org/docs/17/sql-droptransform.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class CastRule
{
    /**
     * The direction of each function position of each `transform_element_list` production.
     */
    private const TRANSFORMS = [
        'transform_element_list: FROM SQL_P WITH FUNCTION function_with_argtypes , TO SQL_P WITH FUNCTION function_with_argtypes' => [4 => TransformDirection::FromSql, 10 => TransformDirection::ToSql],
        'transform_element_list: TO SQL_P WITH FUNCTION function_with_argtypes , FROM SQL_P WITH FUNCTION function_with_argtypes' => [4 => TransformDirection::ToSql, 10 => TransformDirection::FromSql],
        'transform_element_list: FROM SQL_P WITH FUNCTION function_with_argtypes' => [4 => TransformDirection::FromSql],
        'transform_element_list: TO SQL_P WITH FUNCTION function_with_argtypes' => [4 => TransformDirection::ToSql],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a cast or transform command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $types = $this->lowering->types;
        $flags = new CatalogFlags($this->lowering);

        return match ($form->signature) {
            'CreateCastStmt: CREATE CAST ( Typename AS Typename ) WITH FUNCTION function_with_argtypes cast_context' => new CreateCast($types->typeName($form->node(3)), $types->typeName($form->node(5)), $this->lowering->routines->functionSignature($form->node(9)), $this->context($form->node(10))),
            'CreateCastStmt: CREATE CAST ( Typename AS Typename ) WITHOUT FUNCTION cast_context' => new CreateCast($types->typeName($form->node(3)), $types->typeName($form->node(5)), CastConversion::Binary, $this->context($form->node(9))),
            'CreateCastStmt: CREATE CAST ( Typename AS Typename ) WITH INOUT cast_context' => new CreateCast($types->typeName($form->node(3)), $types->typeName($form->node(5)), CastConversion::InOut, $this->context($form->node(9))),
            'DropCastStmt: DROP CAST opt_if_exists ( Typename AS Typename ) opt_drop_behavior' => new Drop(ObjectKind::Cast, [new CastPair($types->typeName($form->node(4)), $types->typeName($form->node(6)))], $flags->present($form->node(2)), $this->lowering->flags->dropBehavior($form->node(8))),
            'CreateTransformStmt: CREATE opt_or_replace TRANSFORM FOR Typename LANGUAGE name ( transform_element_list )' => new CreateTransform($this->lowering->flags->present($form->node(1)), $types->typeName($form->node(4)), $this->lowering->names->name($form->node(6)), $this->functions($this->lowering->productions->form($form->node(8)))),
            'DropTransformStmt: DROP TRANSFORM opt_if_exists FOR Typename LANGUAGE name opt_drop_behavior' => new Drop(ObjectKind::Transform, [new TransformFor($types->typeName($form->node(4)), $this->lowering->names->name($form->node(6)))], $flags->present($form->node(2)), $this->lowering->flags->dropBehavior($form->node(7))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `cast_context`; no context is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function context(Node $context): ?CastContext
    {
        $form = $this->lowering->productions->form($context);

        return match ($form->signature) {
            'cast_context: AS IMPLICIT_P' => CastContext::Implicit,
            'cast_context: AS ASSIGNMENT' => CastContext::Assignment,
            'cast_context:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `transform_element_list` into its functions in the order written.
     *
     * @return list<TransformFunction>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function functions(Form $form): array
    {
        $functions = [];
        foreach (self::TRANSFORMS[$form->signature] ?? throw ImplementationGap::production($form) as $position => $direction) {
            $functions[] = new TransformFunction($direction, $this->lowering->routines->functionSignature($form->node($position)));
        }

        return $functions;
    }
}
