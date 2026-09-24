<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

/**
 * Whether account metadata is a free-form comment or a JSON attribute document.
 * @visibility public
 * @example Inspecting the metadata form
 *     \SqlSemantics\Model\Definition\Account\Policy\AnnotationForm::Attribute->value // => 'ATTRIBUTE'
 */
enum AnnotationForm: string
{
    case Comment = 'COMMENT';
    case Attribute = 'ATTRIBUTE';
}
