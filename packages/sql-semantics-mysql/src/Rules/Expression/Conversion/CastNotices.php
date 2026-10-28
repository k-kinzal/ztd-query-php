<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Derives target notices shared by ordinary and deferred casts.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CastNotices
{
    /**
     * Reports target spelling notices and an unknown character set.
     */
    public function derive(CastTarget $target, Derivation $derivation): void
    {
        if ($target->kind === CastKind::NationalChar) {
            Deprecation::raise(Deprecated::National, $derivation);
        }
        if ($target->charset?->charset !== null) {
            $this->charset($target->charset->charset->value, $derivation);
        }
    }

    /**
     * Publishes a complete cast's target condition after its operand and target notices.
     */
    public function cast(CastTarget $target, \SqlSemantics\Statement\Node $operand, Derivation $derivation, bool $array = false, \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm $form = \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm::Cast): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        $this->derive($target, $derivation);
        $local = $form === \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm::AtLocal;
        $problem = $local ? new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('AT LOCAL')
            : ($array && $derivation->inProgram() ? new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::ArrayCast) : (new CastProblems())->target($target, $array, $derivation->context->profile->grammar));
        return $this->failure($this->label($problem, $target, $operand, $derivation), $derivation, !$local && $this->aborts($derivation));
    }

    /**
     * Retains an excessive AT TIME ZONE precision at its completed cast boundary.
     */
    public function precision(?string $precision, Derivation $derivation): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        $problem = (int) ($precision ?? '0') > 6 ? new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision((int) $precision, 'CAST') : null;
        return $this->failure($problem, $derivation, $this->aborts($derivation));
    }

    /**
     * Derives JSON_VALUE return-target conditions, which continue reading in every release.
     */
    public function returning(?CastTarget $target, \SqlSemantics\Statement\Node $document, Derivation $derivation): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        if ($target === null) {
            return null;
        }
        $this->derive($target, $derivation);
        return $this->failure($this->label((new CastProblems())->returning($target), $target, $document, $derivation), $derivation, false);
    }

    /**
     * Keeps optional source boundaries for diagnostic spelling, independent of SQL meaning.
     */
    public function label(?\SqlSemantics\Statement\Fact\Diagnostic $problem, CastTarget $target, \SqlSemantics\Statement\Node $operand, Derivation $derivation): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        $source = $operand->source() ?? $derivation->sources->of($operand);
        return $source !== null && $target->kind === CastKind::Decimal && $problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize && $problem->rule !== \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::ScaleExceedsPrecision
            ? new \SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput($problem, $source->offset, $source->offset + $source->length) : $problem;
    }

    /**
     * Reports a SQL condition without preventing publication of the containing statement.
     */
    public function failure(?\SqlSemantics\Statement\Fact\Diagnostic $problem, Derivation $derivation, bool $aborts): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, $aborts));
        }
        return $problem;
    }

    /**
     * Identifies releases whose cast factory stops parsing after an invalid target.
     */
    public function aborts(Derivation $derivation): bool
    {
        return !in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5744, \SqlSemantics\Contract\GrammarRelease::MySql8044, \SqlSemantics\Contract\GrammarRelease::MySql810], true);
    }

    /**
     * Emits BINARY's notice after its operand, including an unavailable AT LOCAL result.
     */
    public function binary(\SqlSemantics\Statement\Node $operand, Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        if (!Deprecated::BinaryOperator->warnedIn($release)) {
            return;
        }
        while ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped && $operand->escape === null)) {
            $operand = $operand->operand;
        }
        $local = ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast) && $operand->form === \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm::AtLocal;
        $derivation->warn(new Deprecation(Deprecated::BinaryOperator, aborts: $local && $release !== \SqlSemantics\Contract\GrammarRelease::MySql8044));
    }

    /**
     * Reports a character bound that overflows its resolved character set.
     * Packet limits remain execution state; this is the target's byte capacity.
     */
    public function capacity(CastTarget $target, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain $domain, Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        $limit = \SqlSemantics\Platform\MySql\Rules\Typing\Casts::stringLimit($target, $release);
        if (!in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) && in_array($target->kind, [CastKind::Char, CastKind::NationalChar], true) && $limit !== null && $limit > intdiv(4294967295, $domain->collation->charset->maxLength)) {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange());
        }
    }


    /**
     * Derives a character-set spelling and its lookup condition.
     */
    public function charset(string $name, Derivation $derivation): void
    {
        Deprecation::charset($name, $derivation);
        if (Charset::named($name) === null) {
            $problem = new UnknownCharset($name);
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));
        }
    }
}
