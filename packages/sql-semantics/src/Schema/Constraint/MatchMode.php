<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

/**
 * MatchMode alternatives.
 *
 * @visibility public
 * @example Classifying the MATCH clause of a foreign key
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER PRIMARY KEY); CREATE TABLE c(pid INTEGER REFERENCES p(id) MATCH FULL)');
 *     $schema->tables[1]->constraints[0]->match // => \SqlSemantics\Schema\Constraint\MatchMode::Full
 */
enum MatchMode: string
{
    case Simple = 'simple';
    case Full = 'full';
    case Partial = 'partial';
}
