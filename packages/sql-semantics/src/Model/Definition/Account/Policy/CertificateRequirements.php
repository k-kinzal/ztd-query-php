<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * REQUIRE SUBJECT, ISSUER, and CIPHER constraints; each attribute may appear once.
 * @visibility public
 * @example Counting certificate constraints
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER u REQUIRE SUBJECT 's' AND ISSUER 'i'");
 *     count($statement->requirement->requirements) // => 2
 * @example Rejecting a repeated attribute
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER u REQUIRE SUBJECT 's'");
 *     $constraint = $statement->requirement->requirements[0];
 *     new \SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements([$constraint, $constraint]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CertificateRequirements
{
    /**
     * @param non-empty-list<CertificateRequirement> $requirements Ordered constraints with distinct attributes
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $requirements)
    {
        Collections::objects(Collections::nonEmpty($requirements), CertificateRequirement::class);
        $attributes = array_map(static fn (CertificateRequirement $requirement): string => $requirement->attribute->value, $requirements);
        if (count(array_unique($attributes)) !== count($attributes)) {
            throw new InvalidStructure('REQUIRE cannot repeat SUBJECT, ISSUER, or CIPHER.');
        }
    }
}
