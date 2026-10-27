<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;

/**
 * Constrains COLLATE operands of routine parameters, return types and local declarations.
 *
 * A named collation requires a character set supplied by the type itself.
 * Binary, spatial, JSON and VECTOR types supply binary; national strings supply
 * utf8mb3. An ordinary character type requires a character-set attribute beyond
 * BINARY alone. Known character sets and collations must agree. COLLATE DEFAULT
 * remains a legacy spelling of the absent override.
 * Verified through SQL on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DeclaredCollation
{
    /**
     * Rejects a declaration whose type and COLLATE operand contradict one another.
     */
    public function check(DeclaredName $type, ?CollationName $collation): void
    {
        $problem = $this->problem($type, $collation);
        Check::input($problem === null, $problem?->message() ?? 'The declared collation has a compatible character set.');
    }

    /**
     * Describes an invalid type/collation pair without retaining a rejected declaration.
     */
    public function problem(DeclaredName $type, ?CollationName $collation, GrammarRelease $release = GrammarRelease::MySql847): NotSupportedYet|CollationMismatch|null
    {
        if ($collation?->name === null) {
            return null;
        }
        $charset = $this->charset($type);
        if ($charset === null) {
            return new NotSupportedYet('COLLATE with no CHARACTER SET in SP parameters, RETURNS, DECLARE');
        }
        $declared = Charset::named($charset);
        $named = Collation::named($collation->name->value);

        return $declared !== null && $named !== null && $declared !== $named->charset ? new CollationMismatch($named->name, $declared->nameIn($release)) : null;
    }

    /**
     * Answers the character set carried by the declaration, independently of a session default.
     */
    public function charset(DeclaredName $type): ?string
    {
        if ($type instanceof Binary || $type instanceof Spatial || ($type instanceof Elementary && in_array($type->kind, [ElementaryKind::Json, ElementaryKind::Vector], true))) {
            return 'binary';
        }
        if ($type instanceof Character && $type->national) {
            return 'utf8mb3';
        }
        $attribute = $type instanceof Character || $type instanceof Enumeration ? $type->charset : null;

        return match ($attribute?->form) {
            CharsetForm::Ascii => 'latin1',
            CharsetForm::Unicode => 'ucs2',
            CharsetForm::Byte => 'binary',
            CharsetForm::Named, CharsetForm::CharacterSet, CharsetForm::CharSet => $attribute->charset?->value,
            CharsetForm::Binary, null => null,
        };
    }

    /**
     * Supplies the declared collation to parameter and local-variable reference typing.
     */
    public function type(DeclaredName $type, ?CollationName $collation, \SqlSemantics\Contract\AnalysisContext $context): \SqlSemantics\Statement\Type\TypeDescriptor
    {
        $named = $collation?->name === null ? null : Collation::named($collation->name->value);
        if ($named === null) {
            return $type;
        }

        return (new \SqlSemantics\Platform\MySql\Rules\Typing\Declared($named, $context->profile->grammar, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($context)->realAsFloat))->domain($type, $named);
    }

    /**
     * Records the notice of an explicit declaration collation at its semantic occurrence.
     */
    public function warn(?CollationName $collation, \SqlSemantics\Construction\Derivation $derivation): void
    {
        $notice = $collation?->deprecation($derivation->context->profile->grammar);
        if ($notice !== null) {
            $derivation->warn($notice, $collation);
        }
    }
}
