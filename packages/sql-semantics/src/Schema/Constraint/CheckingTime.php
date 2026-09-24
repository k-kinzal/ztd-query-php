<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

/**
 * CheckingTime alternatives.
 *
 * @visibility public
 * @example Classifying deferrable constraints
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER PRIMARY KEY); CREATE TABLE c(pid INTEGER REFERENCES p(id) DEFERRABLE INITIALLY DEFERRED)');
 *     $schema->tables[1]->constraints[0]->checking // => \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableDeferred
 */
enum CheckingTime: string
{
    case Immediate = 'not-deferrable';
    case DeferrableImmediate = 'deferrable-immediate';
    case DeferrableDeferred = 'deferrable-deferred';
}
