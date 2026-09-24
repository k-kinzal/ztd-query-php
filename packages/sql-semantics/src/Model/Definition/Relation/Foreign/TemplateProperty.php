<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Foreign;

/**
 * The properties a LIKE clause copies from or leaves out of its template table.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Foreign\TemplateProperty::Defaults->value // => 'DEFAULTS'
 */
enum TemplateProperty: string
{
    case Comments = 'COMMENTS';
    case Compression = 'COMPRESSION';
    case Constraints = 'CONSTRAINTS';
    case Defaults = 'DEFAULTS';
    case Identity = 'IDENTITY';
    case Generated = 'GENERATED';
    case Indexes = 'INDEXES';
    case Statistics = 'STATISTICS';
    case Storage = 'STORAGE';
    case All = 'ALL';
}
