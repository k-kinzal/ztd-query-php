<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

/**
 * The certificate property a REQUIRE clause constrains.
 * @visibility public
 * @example Inspecting a certificate attribute
 *     \SqlSemantics\Model\Definition\Account\Policy\CertificateAttribute::Issuer->value // => 'ISSUER'
 */
enum CertificateAttribute: string
{
    case Subject = 'SUBJECT';
    case Issuer = 'ISSUER';
    case Cipher = 'CIPHER';
}
