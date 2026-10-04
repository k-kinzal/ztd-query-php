<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Spatial;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One attribute of CREATE SPATIAL REFERENCE SYSTEM: `NAME 'text'`, `DEFINITION 'text'`, `ORGANIZATION 'text' IDENTIFIED BY id`, `DESCRIPTION 'text'`.
 *
 * The text is a quoted string without a line feed. ORGANIZATION also names
 * the identifier the organization gives the system.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 *
 * @visibility public
 * @example Holding an organization attribute
 *     $attribute = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Organization, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('EPSG'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4326'));
 *     [$attribute->value->value, $attribute->identifier?->text] // => ['EPSG', '4326']
 */
final class SpatialAttribute implements Node
{
    use Snapshot;

    /**
     * @param SpatialAttributeKind $kind The attribute
     * @param Text $value The text of the attribute
     * @param Numeral|null $identifier The identifier of IDENTIFIED BY; written exactly for ORGANIZATION
     * @throws InvalidConstruction When the identifier is written for another attribute or missing for ORGANIZATION
     */
    public function __construct(public readonly SpatialAttributeKind $kind, public readonly Text $value, public readonly ?Numeral $identifier = null)
    {
        Check::input(($identifier !== null) === ($kind === SpatialAttributeKind::Organization), 'IDENTIFIED BY belongs to ORGANIZATION.');
    }

    /**
     * Writes the attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->value);
        if ($this->identifier !== null) {
            $out->keyword('IDENTIFIED', 'BY')->node($this->identifier);
        }
    }
}
