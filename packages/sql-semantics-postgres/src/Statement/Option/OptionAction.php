<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

/**
 * What an ALTER command does with one foreign-data option.
 *
 * ADD is assumed when no action is written.
 * Source: https://www.postgresql.org/docs/17/sql-alterforeigndatawrapper.html.
 *
 * @visibility public
 * @example Spelling the action that removes an option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Option\OptionAction::Drop->value // => 'DROP'
 */
enum OptionAction: string
{
    case Add = 'ADD';
    case Set = 'SET';
    case Drop = 'DROP';
}
