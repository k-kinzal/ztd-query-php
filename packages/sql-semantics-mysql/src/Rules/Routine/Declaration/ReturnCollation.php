<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Distinguishes implicit return collations from complete character-set declarations.
 *
 * A non-national CHAR or VARCHAR return with BINARY alone has no resolved return
 * collation on any supported release. National types become defined on MySQL 8.
 * Legacy character returns also refuse BINARY with a named character set;
 * ASCII uses its fixed collation. TEXT, ENUM and SET retain their
 * own binary attribute semantics and do not share the character-type refusal.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReturnCollation
{
    private const CHARACTER_KINDS = [CharacterKind::Char, CharacterKind::VarChar, CharacterKind::CharVarying];

    /**
     * Identifies a return declaration unsupported independently of catalog or release.
     */
    public function intrinsic(TypeName $type): bool
    {
        return $type instanceof Character && in_array($type->kind, self::CHARACTER_KINDS, true) && !$type->national && $type->charset?->form === CharsetForm::Binary;
    }

    /**
     * Answers the return declaration's typed, release-specific condition.
     */
    public function problem(TypeName $type, GrammarRelease $release): ?NotSupportedYet
    {
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $attribute = $type instanceof Character && in_array($type->kind, self::CHARACTER_KINDS, true) ? $type->charset : null;
        $refused = $legacy && $attribute !== null && $attribute->binary() && ($attribute->form === CharsetForm::Binary || $attribute->form->named());
        return $this->intrinsic($type) || $refused ? new NotSupportedYet('return value collation') : null;
    }

    /**
     * Resolves the return collation from its declaration and the definition's database default.
     * TEXT, ENUM and SET ignore BINARY alone in a return declaration; a named set with
     * the binary attribute retains its ordering from MySQL 8. Legacy named
     * character sets ignore that mark. Explicit COLLATE always retains its ordering.
     */
    public function resolve(\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName $type, ?\SqlSemantics\Platform\MySql\Statement\Name\CollationName $collation, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation $default, GrammarRelease $release): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation
    {
        $override = $collation?->name === null ? null : \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::forName($collation->name->value, $release);
        $text = $type instanceof Character && !in_array($type->kind, self::CHARACTER_KINDS, true);
        if ($override === null && ($type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Enumeration || $text) && $type->charset?->form === CharsetForm::Binary) {
            return $default;
        }

        if ($override === null && $type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Enumeration && $type->charset?->form->named() === true && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $attribute = new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute($type->charset->form, $type->charset->charset);
            return (new \SqlSemantics\Platform\MySql\Rules\Typing\Declared($default, $release))->charset($attribute, $default);
        }

        return (new \SqlSemantics\Platform\MySql\Rules\Typing\Declared($default, $release))->domain($type, $override)->collation;
    }
}
