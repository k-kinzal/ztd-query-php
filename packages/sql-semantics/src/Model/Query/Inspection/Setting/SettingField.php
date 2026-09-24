<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Setting;

use SqlSemantics\Type\Nullability;

/**
 * Result fields of PostgreSQL SHOW: the value of one parameter, or the name, value and description of every parameter.
 * @visibility public
 * @example Reading the declared facts of a field
 *     $field = \SqlSemantics\Model\Query\Inspection\Setting\SettingField::Description;
 *     [$field->value, $field->nullability()->value] // => ['description', 'maybe-null']
 */
enum SettingField: string
{
    case Name = 'name';
    case Setting = 'setting';
    case Description = 'description';

    /**
     * Parameter names and displayed values are always present; a custom placeholder parameter has no description.
     */
    public function nullability(): Nullability
    {
        return $this === self::Description ? Nullability::MaybeNull : Nullability::NotNull;
    }
}
