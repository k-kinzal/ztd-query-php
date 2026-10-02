<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Spells decoded names as SQL of a fixed language profile, for SQL text a caller assembles and analyzes again.
 *
 * The codec encodes names for a position. It is not a general escaping
 * function: it does not spell values, expressions or statements, and SQL
 * assembled with its help is unchecked until it is analyzed.
 *
 * @visibility public
 * @example Spelling a relation name read from one query for another statement
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $input = $semantics->analyze('SELECT a FROM "order items"')->singleNamedInput();
 *     $table = \SqlSemantics\Facade\SqlNames::table($input->name(), $semantics->profile());
 *     $semantics->analyze("SELECT * FROM {$table}")->singleNamedInput()->name()->name->value // => 'order items'
 */
final class SqlNames
{
    /**
     * Spells a decoded, optionally qualified relation name for a relation name position.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the database package of the profile is not installed
     */
    public static function table(QualifiedName $name, LanguageProfile $profile): string
    {
        $codec = Platforms::of($profile->grammar->database())->codec($profile);
        $parts = [];
        if ($name->catalog !== null) {
            $parts[] = $codec->name($name->catalog, NameUse::Qualifier);
        }
        if ($name->schema !== null) {
            $parts[] = $codec->name($name->schema, NameUse::Qualifier);
        }
        $parts[] = $codec->name($name->name, NameUse::Relation);

        return implode('.', $parts);
    }
}
