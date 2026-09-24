<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

use SqlSemantics\Model\Definition\Account\Identification\IdentificationOperands;
use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * MySQL 8 account metadata: COMMENT 'text' or ATTRIBUTE 'json', recorded as supplied.
 * @visibility public
 * @example Reading account metadata
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER u COMMENT 'analyst'");
 *     [$statement->annotation->form->value, $statement->annotation->text->text] // => ['COMMENT', "'analyst'"]
 */
final class AccountAnnotation
{
    /**
     * Keeps the metadata literal without parsing JSON.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly AnnotationForm $form, public readonly Literal $text)
    {
        IdentificationOperands::secret($text);
    }
}
