<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ZoneOption;

/**
 * Lowers the keyword spellings of the character and date/time types.
 *
 * Rule: PG-TYPE-SPELLING-LOWER-001. Scope: `Character`, `ConstCharacter`,
 * `CharacterWithLength`, `CharacterWithoutLength`, `character`,
 * `opt_varying`, `ConstDatetime`, `opt_timezone`. Constructors:
 * `CharacterDesignation`, `DatetimeDesignation`. Termination: no recursion.
 * Source: https://www.postgresql.org/docs/17/datatype-character.html,
 * https://www.postgresql.org/docs/17/datatype-datetime.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Spellings
{
    /**
     * The spelling of each `character` production, with the position of its `opt_varying`; -1 when it has none.
     */
    private const CHARACTERS = [
        'character: CHARACTER opt_varying' => [CharacterKeyword::Character, 1], 'character: CHAR_P opt_varying' => [CharacterKeyword::Char, 1],
        'character: VARCHAR' => [CharacterKeyword::Varchar, -1], 'character: NATIONAL CHARACTER opt_varying' => [CharacterKeyword::NationalCharacter, 2],
        'character: NATIONAL CHAR_P opt_varying' => [CharacterKeyword::NationalChar, 2], 'character: NCHAR opt_varying' => [CharacterKeyword::Nchar, 1],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `Character`, `ConstCharacter`, `CharacterWithLength` or `CharacterWithoutLength`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function character(Node $type): CharacterDesignation
    {
        $form = $this->lowering->productions->form($type);
        if (in_array($form->signature, ['Character: CharacterWithLength', 'Character: CharacterWithoutLength', 'ConstCharacter: CharacterWithLength', 'ConstCharacter: CharacterWithoutLength'], true)) {
            return $this->character($form->node(0));
        }
        if ($form->signature !== 'CharacterWithLength: character ( Iconst )' && $form->signature !== 'CharacterWithoutLength: character') {
            throw ImplementationGap::production($form);
        }
        $spelling = $this->lowering->productions->form($form->node(0));
        [$keyword, $position] = self::CHARACTERS[$spelling->signature] ?? throw ImplementationGap::production($spelling);

        return new CharacterDesignation(
            $keyword,
            $position >= 0 && $this->varying($spelling->node($position)),
            $form->signature === 'CharacterWithoutLength: character' ? null : $this->lowering->literals->integer($form->node(2)),
        );
    }

    /**
     * Lowers `opt_varying`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function varying(Node $varying): bool
    {
        $form = $this->lowering->productions->form($varying);

        return match ($form->signature) {
            'opt_varying: VARYING' => true,
            'opt_varying:' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ConstDatetime`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function datetime(Node $type): DatetimeDesignation
    {
        $form = $this->lowering->productions->form($type);
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'ConstDatetime: TIMESTAMP ( Iconst ) opt_timezone' => new DatetimeDesignation(DatetimeKeyword::Timestamp, $literals->integer($form->node(2)), $this->zone($form->node(4))),
            'ConstDatetime: TIMESTAMP opt_timezone' => new DatetimeDesignation(DatetimeKeyword::Timestamp, null, $this->zone($form->node(1))),
            'ConstDatetime: TIME ( Iconst ) opt_timezone' => new DatetimeDesignation(DatetimeKeyword::Time, $literals->integer($form->node(2)), $this->zone($form->node(4))),
            'ConstDatetime: TIME opt_timezone' => new DatetimeDesignation(DatetimeKeyword::Time, null, $this->zone($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_timezone`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function zone(Node $zone): ?ZoneOption
    {
        $form = $this->lowering->productions->form($zone);

        return match ($form->signature) {
            'opt_timezone: WITH_LA TIME ZONE' => ZoneOption::WithTimeZone,
            'opt_timezone: WITHOUT_LA TIME ZONE' => ZoneOption::WithoutTimeZone,
            'opt_timezone:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
