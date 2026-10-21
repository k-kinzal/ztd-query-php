<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Uses existing type rules to identify results that cannot supply a TIMESTAMP field.
 *
 * Catalog declarations, routine signatures and parameter bindings are absent.
 * Unresolved facts remain unresolved. A rejection requires a non-TIMESTAMP
 * result in every supported release with AT TIME ZONE; diagnostics and execution
 * eligibility are not consulted. Coarse DATETIME families remain undecided
 * because they can include a TIMESTAMP field.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TimeZoneFacts
{
    /**
     * Answers false only when the existing type facts exclude the input role in every release.
     */
    public function input(Scalar $operand): ?bool
    {
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            $context = new AnalysisContext(new LanguageProfile($release), [new Name('intrinsic')], [], false);
            $derivation = new Derivation($context);
            if (!$this->excludes($derivation->scalar($operand, $derivation->environment())->type)) {
                return null;
            }
        }
        return false;
    }

    /**
     * Reads known descriptors and every alternative without treating unresolved facts as refusals.
     */
    public function excludes(TypeFact $type): bool
    {
        if ($type instanceof Known) {
            return $this->descriptor($type->descriptor);
        }
        return $type instanceof Choice && array_filter($type->alternatives, fn (TypeDescriptor $descriptor): bool => !$this->descriptor($descriptor)) === [];
    }

    /**
     * Distinguishes a concrete field from a coarse type family that might preserve TIMESTAMP.
     */
    public function descriptor(TypeDescriptor $descriptor): bool
    {
        if ($descriptor instanceof Domain) {
            return $descriptor->field !== Field::Timestamp && $descriptor->kind !== Kind::Null;
        }
        return $descriptor instanceof TypeName && TypeClass::of($descriptor) !== TypeClass::DateTime;
    }
}
