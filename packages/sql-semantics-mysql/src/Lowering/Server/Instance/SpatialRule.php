<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Instance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;
use SqlSemantics\Platform\MySql\Statement\Statement;

/**
 * Lowers CREATE and DROP SPATIAL REFERENCE SYSTEM.
 *
 * Rule: MYSQL-SRS-001. Scope: create_srs_stmt, srs_attributes,
 * drop_srs_stmt. The attribute list is left recursive from an empty list
 * and is walked along its spine; the attributes are kept in written order.
 * Constructs: CreateSpatialReference, SpatialAttribute,
 * DropSpatialReference. Terminates: the spine is walked in a loop.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-spatial-reference-system.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering
 */
final class SpatialRule
{
    /**
     * The attribute productions, by the attribute they write.
     */
    private const ATTRIBUTES = [
        'srs_attributes: srs_attributes NAME_SYM TEXT_STRING_sys_nonewline' => SpatialAttributeKind::Name,
        'srs_attributes: srs_attributes DEFINITION_SYM TEXT_STRING_sys_nonewline' => SpatialAttributeKind::Definition,
        'srs_attributes: srs_attributes ORGANIZATION_SYM TEXT_STRING_sys_nonewline IDENTIFIED_SYM BY real_ulonglong_num' => SpatialAttributeKind::Organization,
        'srs_attributes: srs_attributes DESCRIPTION_SYM TEXT_STRING_sys_nonewline' => SpatialAttributeKind::Description,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a spatial reference system statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $numbers = $this->lowering->numbers;
        $options = $this->lowering->options;

        return match ($form->signature) {
            'create_srs_stmt: CREATE OR_SYM REPLACE_SYM SPATIAL_SYM REFERENCE_SYM SYSTEM_SYM real_ulonglong_num srs_attributes' => $this->create(
                $form,
                true,
                false,
                $numbers->integral($form->node(6)),
                $this->attributes($form->node(7)),
            ),
            'create_srs_stmt: CREATE SPATIAL_SYM REFERENCE_SYM SYSTEM_SYM opt_if_not_exists real_ulonglong_num srs_attributes' => $this->create(
                $form,
                false,
                $options->present($form->node(4)),
                $numbers->integral($form->node(5)),
                $this->attributes($form->node(6)),
            ),
            'drop_srs_stmt: DROP SPATIAL_SYM REFERENCE_SYM SYSTEM_SYM if_exists real_ulonglong_num' => $this->drop($form, $options->present($form->node(4)), $numbers->integral($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Retains the complete CREATE request, including its intrinsic declaration problem.
     *
     * @param list<SpatialAttribute> $attributes The complete written attributes
     */
    public function create(Form $form, bool $replace, bool $ifNotExists, \SqlSemantics\Platform\MySql\Statement\Literal\Numeral $srid, array $attributes): CreateSpatialReference
    {
        return new CreateSpatialReference($replace, $ifNotExists, $srid, $attributes);
    }

    /**
     * Retains a DROP request and its intrinsic identifier problem.
     *
     */
    public function drop(Form $form, bool $ifExists, \SqlSemantics\Platform\MySql\Statement\Literal\Numeral $srid): DropSpatialReference
    {
        return new DropSpatialReference($ifExists, $srid);
    }

    /**
     * Lowers the attribute list: a node of `srs_attributes`.
     *
     * @return list<SpatialAttribute>
     * @throws \SqlSemantics\Construction\RejectedInput When an attribute is intrinsically invalid or repeated
     * @throws ImplementationGap When a production has no rule
     */
    public function attributes(Node $list): array
    {
        $forms = [];
        $form = $this->lowering->form($list);
        while ($form->signature !== 'srs_attributes:') {
            $forms[] = $form;
            $form = $this->lowering->form($form->node(0));
        }
        $attributes = [];
        $seen = [];
        foreach (array_reverse($forms) as $attributeForm) {
            $kind = self::ATTRIBUTES[$attributeForm->signature] ?? throw ImplementationGap::production($attributeForm);
            $value = $this->lowering->literals->text($attributeForm->node(2));
            $identifier = $kind === SpatialAttributeKind::Organization ? $this->lowering->numbers->integral($attributeForm->node(5)) : null;
            if (isset($seen[$kind->value])) {
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::RepeatedAttribute, $kind), (new \SqlSemantics\Construction\SourceRanges())->span($attributeForm->node)[1] ?? 0);
            }
            $seen[$kind->value] = true;
            $attributes[] = new SpatialAttribute($kind, $value, $identifier);
        }

        return $attributes;
    }

    /**
     * Preserves a completed attribute refusal before a later invalid operand.
     */
    public function completed(Node $node): ?\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure
    {
        if ($node->name !== 'srs_attributes') {
            return null;
        }
        try {
            $this->attributes($node);
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($failure->problem, true);
        }

        return null;
    }
}
