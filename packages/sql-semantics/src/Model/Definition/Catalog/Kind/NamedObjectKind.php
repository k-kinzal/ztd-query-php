<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog\Kind;

/**
 * PostgreSQL object classes that are addressed by one unqualified name.
 * @visibility public
 * @example Reading the SQL spelling of a global object class
 *     \SqlSemantics\Model\Definition\Catalog\Kind\NamedObjectKind::ForeignDataWrapper->value // => 'FOREIGN DATA WRAPPER'
 */
enum NamedObjectKind: string
{
    case AccessMethod = 'ACCESS METHOD';
    case Database = 'DATABASE';
    case EventTrigger = 'EVENT TRIGGER';
    case Extension = 'EXTENSION';
    case ForeignDataWrapper = 'FOREIGN DATA WRAPPER';
    case Language = 'LANGUAGE';
    case Publication = 'PUBLICATION';
    case Role = 'ROLE';
    case Schema = 'SCHEMA';
    case Server = 'SERVER';
    case Subscription = 'SUBSCRIPTION';
    case Tablespace = 'TABLESPACE';
}
