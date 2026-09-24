<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Session;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result field of SHOW COUNT(*) ERRORS and SHOW COUNT(*) WARNINGS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Session\DiagnosticCountField::Errors->label() // => '@@session.error_count'
 */
enum DiagnosticCountField: string implements MetadataField
{
    use TextField;

    case Errors = '@@session.error_count';
    case Warnings = '@@session.warning_count';

    /**
     * Returns the counter of the selected conditions.
     */
    public static function of(DiagnosticSelection $selection): self
    {
        return match ($selection) {
            DiagnosticSelection::Warnings => self::Warnings,
            DiagnosticSelection::Errors => self::Errors,
        };
    }

    /**
     * Condition counts are integers.
     */
    public function type(): string
    {
        return 'bigint';
    }
}
