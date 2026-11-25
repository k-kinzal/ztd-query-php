<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The generating field and other backing keys of a resolved physical index.
 * @visibility public
 * @example Identifying an alternate native backing key
 *     $field = new \SqlSemantics\Platform\MySql\Ir\Identifier('id');
 *     $alternate = new \SqlSemantics\Platform\MySql\Ir\Identifier('by_id');
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement($field, [$alternate]))->alternates[0]->value // => 'by_id'
 */
final class IndexAutoIncrement
{
    use Snapshot;

    /**
     * @var list<Identifier> Other native keys beginning with this field
     */
    public readonly array $alternates;

    /**
     * Fixes the field and every distinct alternate key name.
     * @param list<Identifier> $alternates Other known native backing keys
     */
    public function __construct(public readonly Identifier $field, array $alternates)
    {
        Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $field->value) === 1, 'An AUTO_INCREMENT backing field needs a valid native name.');
        $checked = Check::listOf($alternates, Identifier::class, 'AUTO_INCREMENT alternate keys form an ordered list of native names.');
        Check::input(count($checked) < 64, 'An AUTO_INCREMENT backing index can have at most 63 alternate keys.');
        $case = new NativeIdentifierCase();
        $seen = [];
        foreach ($checked as $alternate) {
            $key = $case->fold($alternate->value);
            Check::input(
                preg_match('/\A.{1,64}(?<! )\z/us', $alternate->value) === 1 && !isset($seen[$key]),
                'The AUTO_INCREMENT alternate key names must be distinct native identifiers.'
            );
            $seen[$key] = true;
        }
        $this->alternates = $checked;
    }
}
