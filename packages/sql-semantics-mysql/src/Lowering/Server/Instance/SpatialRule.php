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
use SqlSemantics\Statement\Statement;

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
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
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
            'create_srs_stmt: CREATE OR_SYM REPLACE_SYM SPATIAL_SYM REFERENCE_SYM SYSTEM_SYM real_ulonglong_num srs_attributes' => new CreateSpatialReference(
                true,
                false,
                $numbers->numeral($form->node(6)),
                $this->attributes($form->node(7)),
            ),
            'create_srs_stmt: CREATE SPATIAL_SYM REFERENCE_SYM SYSTEM_SYM opt_if_not_exists real_ulonglong_num srs_attributes' => new CreateSpatialReference(
                false,
                $options->present($form->node(4)),
                $numbers->numeral($form->node(5)),
                $this->attributes($form->node(6)),
            ),
            'drop_srs_stmt: DROP SPATIAL_SYM REFERENCE_SYM SYSTEM_SYM if_exists real_ulonglong_num' => new DropSpatialReference($options->present($form->node(4)), $numbers->numeral($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the attribute list: a node of `srs_attributes`.
     *
     * @return list<SpatialAttribute>
     * @throws ImplementationGap When a production has no rule
     */
    public function attributes(Node $list): array
    {
        $attributes = [];
        $form = $this->lowering->form($list);
        while ($form->signature !== 'srs_attributes:') {
            $kind = self::ATTRIBUTES[$form->signature] ?? throw ImplementationGap::production($form);
            $identifier = $kind === SpatialAttributeKind::Organization ? $this->lowering->numbers->numeral($form->node(5)) : null;
            array_unshift($attributes, new SpatialAttribute($kind, $this->lowering->literals->text($form->node(2)), $identifier));
            $form = $this->lowering->form($form->node(0));
        }

        return $attributes;
    }
}
