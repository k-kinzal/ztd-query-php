<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * An ENUM or SET type with its permitted values in declaration order and its character set attribute.
 *
 * The order of the values is their index order and is part of the type.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/enum.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set.html.
 *
 * @visibility public
 * @example Reading the members of an enumeration
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('s'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('m')]);
 *     [$type->name(), $type->members[1]->value] // => ['ENUM', 'm']
 */
final class Enumeration implements TypeName
{
    use Snapshot;

    /**
     * @var non-empty-list<Text> The permitted values in declaration order
     */
    public readonly array $members;

    /**
     * @param EnumerationKind $kind ENUM or SET
     * @param list<Text> $members The permitted values in declaration order; at least one
     * @param CharsetAttribute|null $charset The character set attribute
     */
    public function __construct(public readonly EnumerationKind $kind, array $members, public readonly ?CharsetAttribute $charset = null)
    {
        $this->members = Check::listOf($members, Text::class, 'An enumeration declares at least one member.', 1);
    }

    /**
     * Names the type by its keyword.
     */
    public function name(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the keyword, the members and the character set attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->glue()->symbol('(')->list($this->members)->symbol(')')->node($this->charset);
    }
}
