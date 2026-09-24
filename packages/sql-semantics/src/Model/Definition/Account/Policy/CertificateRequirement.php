<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

use SqlSemantics\Model\Definition\Account\Identification\IdentificationOperands;
use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * One certificate constraint such as SUBJECT 'text', recorded without matching any certificate.
 * @visibility public
 * @example Reading a certificate constraint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER u REQUIRE CIPHER 'EDH-RSA-DES-CBC3-SHA'");
 *     $statement->requirement->requirements[0]->attribute->value // => 'CIPHER'
 */
final class CertificateRequirement
{
    /**
     * Requires the attribute and its text literal.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly CertificateAttribute $attribute, public readonly Literal $value)
    {
        IdentificationOperands::secret($value);
    }
}
