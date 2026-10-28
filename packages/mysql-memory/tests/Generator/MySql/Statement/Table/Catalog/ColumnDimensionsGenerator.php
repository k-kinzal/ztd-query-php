<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Catalog;

use Eris\Generator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\ColumnDimensions;
use Tests\Generator\Composition;
use Tests\Generator\Operands;

/**
 * Composes a catalog type's dimensions without reading its display text.
 *
 * @implements Generator<ColumnDimensions>
 */
final class ColumnDimensionsGenerator implements Generator
{
    /** @use Composition<ColumnDimensions> */
    use Composition;

    /**
     * @param Generator<string> $name The canonical type name
     * @param Generator<int|null> $characters The maximum character length
     * @param Generator<int|null> $octets The related maximum byte length
     * @param Generator<int|null> $precision The numeric precision
     * @param Generator<int|null> $scale The related numeric scale
     * @param Generator<int|null> $fraction The fractional seconds precision
     */
    public function __construct(Generator $name, Generator $characters, Generator $octets, Generator $precision, Generator $scale, Generator $fraction)
    {
        $this->delegate = Generators::map(static fn (array $parts): ColumnDimensions => new ColumnDimensions(...$parts), Operands::tuple($name, $characters, $octets, $precision, $scale, $fraction));
    }

    /**
     * Preserves the related dimensions of a generated catalog column.
     *
     * @param Generator<ColumnDimensions> $types The enclosing column's declared types
     * @return Generator<ColumnDimensions>
     */
    public static function catalog(Generator $types): Generator
    {
        return Operands::bind($types, static fn (ColumnDimensions $type): self => new self(
            Generators::constant($type->name),
            Generators::constant($type->characters),
            Generators::constant($type->octets),
            Generators::constant($type->precision),
            Generators::constant($type->scale),
            Generators::constant($type->fraction),
        ));
    }
}
