<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A spatial reference system statement that breaks a rule the server checks while it parses it.
 *
 * The server rejects the statement with the error of the rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 *
 * @visibility public
 * @example Describing a missing attribute
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::MissingAttribute, \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Definition))->message() // => 'Missing mandatory attribute DEFINITION (ER_SRS_MISSING_MANDATORY_ATTRIBUTE).'
 */
final class SpatialProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param SpatialRule $rule The broken rule
     * @param SpatialAttributeKind|null $attribute The attribute the rule concerns; null for the identifier of the system
     * @param string|null $value The decoded attribute containing a line feed, present only for that problem
     */
    public function __construct(public readonly SpatialRule $rule, public readonly ?SpatialAttributeKind $attribute = null, public readonly ?string $value = null)
    {
        Check::input(($rule === SpatialRule::LineFeed) === ($value !== null), 'Only a line-feed problem retains its offending value.');
        Check::input($rule !== SpatialRule::LineFeed || ($attribute !== null && str_contains((string) $value, "\n")), 'A line-feed problem names an attribute containing a line feed.');
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        $subject = $this->attribute === null ? 'SRID' : $this->attribute->value;
        $text = match ($this->rule) {
            SpatialRule::LineFeed => 'Attribute ' . $subject . ' contains a line feed',
            SpatialRule::IdentifierOutOfRange => $subject . ' is out of range: it is at most 4294967295',
            SpatialRule::IdentifierZero => 'SRID 0 cannot be modified',
            SpatialRule::RepeatedAttribute => 'Multiple definitions of attribute ' . $subject,
            SpatialRule::MissingAttribute => 'Missing mandatory attribute ' . $subject,
            SpatialRule::BlankName => 'The spatial reference system name cannot be an empty string or start or end with whitespace',
            SpatialRule::BlankOrganization => 'The organization name cannot be an empty string or start or end with whitespace',
            SpatialRule::ControlCharacter => 'Invalid character in attribute ' . $subject,
            SpatialRule::TooLong => 'Attribute ' . $subject . ' is too long',
        };

        return $text . ' (' . $this->rule->value . ').';
    }
}
