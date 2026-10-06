<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A transform named by its type and language: `FOR type LANGUAGE language`.
 *
 * Source: https://www.postgresql.org/docs/17/sql-droptransform.html.
 *
 * @visibility public
 * @example Reading the language
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('hstore')])));
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TransformFor($type, new \SqlSemantics\Statement\Identifier\Name('plpython3u')))->language->value // => 'plpython3u'
 */
final class TransformFor implements ObjectReference
{
    use Snapshot;

    /**
     * @param TypeName $type The transformed type
     * @param Name $language The language
     */
    public function __construct(public readonly TypeName $type, public readonly Name $language)
    {
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes FOR, the type, LANGUAGE and the language.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOR')->node($this->type)->keyword('LANGUAGE')->name($this->language, NameUse::Column);
    }
}
