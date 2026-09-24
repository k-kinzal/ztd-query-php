<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Session;

/**
 * Which conditions of the diagnostics area a request reports: every condition, or errors only.
 * @visibility public
 * @example Reading the selected conditions
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW ERRORS');
 *     $statement->selection // => \SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection::Errors
 */
enum DiagnosticSelection: string
{
    case Warnings = 'WARNINGS';
    case Errors = 'ERRORS';
}
