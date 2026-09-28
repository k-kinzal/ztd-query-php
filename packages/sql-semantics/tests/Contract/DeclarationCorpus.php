<?php

declare(strict_types=1);

namespace Tests\Contract;

/**
 * Declaration facts independently checked against database catalogs.
 *
 * @phpstan-type Case array{string, string, bool, bool, bool, int|null, int|null, bool}
 */
final class DeclarationCorpus
{
    /**
     * @return array<string, Case> Dialect, SQL, acceptance, nullable, automatic, effective precision, effective scale, unique
     */
    public static function cases(): array
    {
        return [
            'mysql-last-null' => ['mysql', 'CREATE TABLE probe(a INT NOT NULL NULL)', true, true, false, null, null, false],
            'mysql-last-not-null' => ['mysql', 'CREATE TABLE probe(a INT NULL NOT NULL)', true, false, false, null, null, false],
            'mysql-serial-attribute' => ['mysql', 'CREATE TABLE probe(a INT SERIAL DEFAULT VALUE)', true, false, true, null, null, true],
            'mysql-serial-type' => ['mysql', 'CREATE TABLE probe(a SERIAL)', true, false, true, null, null, true],
            'mysql-unindexed-auto' => ['mysql', 'CREATE TABLE probe(a INT AUTO_INCREMENT)', false, false, false, null, null, false],
            'mysql-other-index' => ['mysql', 'CREATE TABLE probe(a INT AUTO_INCREMENT, b INT, KEY(b))', false, false, false, null, null, false],
            'mysql-indexed-auto' => ['mysql', 'CREATE TABLE probe(a INT AUTO_INCREMENT, KEY(a))', true, false, true, null, null, false],
            'mysql-two-auto' => ['mysql', 'CREATE TABLE probe(a SERIAL, b SERIAL)', false, false, false, null, null, false],
            'mysql-decimal-default' => ['mysql', 'CREATE TABLE probe(a DECIMAL)', true, true, false, 10, 0, false],
            'mysql-decimal-zero' => ['mysql', 'CREATE TABLE probe(a DECIMAL(0))', true, true, false, 10, 0, false],
            'mysql-decimal-precision' => ['mysql', 'CREATE TABLE probe(a DECIMAL(5))', true, true, false, 5, 0, false],
            'mysql-decimal-bounds' => ['mysql', 'CREATE TABLE probe(a DECIMAL(65,30))', true, true, false, 65, 30, false],
            'mysql-decimal-invalid' => ['mysql', 'CREATE TABLE probe(a DECIMAL(5,6))', false, false, false, null, null, false],
            'mysql-default' => ['mysql', 'CREATE TABLE probe(a INT DEFAULT -2)', true, true, false, null, null, false],
            'mysql-enum' => ['mysql', "CREATE TABLE probe(a ENUM('a''b', 'a\\nb', X'41'))", true, true, false, null, null, false],
            'mysql-foreign' => ['mysql', 'CREATE TABLE probe(a INT, FOREIGN KEY(a) REFERENCES parent(id))', true, true, false, null, null, false],
            'pg-conflicting-null' => ['pg', 'CREATE TABLE probe(a INT NOT NULL NULL)', false, false, false, null, null, false],
            'pg-conflicting-not-null' => ['pg', 'CREATE TABLE probe(a INT NULL NOT NULL)', false, false, false, null, null, false],
            'pg-serial-null' => ['pg', 'CREATE TABLE probe(a SERIAL NULL)', false, false, false, null, null, false],
            'pg-numeric-default' => ['pg', 'CREATE TABLE probe(a NUMERIC)', true, true, false, null, null, false],
            'pg-numeric-precision' => ['pg', 'CREATE TABLE probe(a NUMERIC(8))', true, true, false, 8, 0, false],
            'pg-numeric-negative-scale' => ['pg', 'CREATE TABLE probe(a NUMERIC(2,-3))', true, true, false, 2, -3, false],
            'pg-numeric-large-scale' => ['pg', 'CREATE TABLE probe(a NUMERIC(2,5))', true, true, false, 2, 5, false],
            'pg-numeric-invalid' => ['pg', 'CREATE TABLE probe(a NUMERIC(0))', false, false, false, null, null, false],
            'pg-catalog-type' => ['pg', 'CREATE TABLE probe(a pg_catalog.numeric(7,2))', true, true, false, 7, 2, false],
            'pg-default' => ['pg', "CREATE TABLE probe(a TEXT DEFAULT E'\\x41')", true, true, false, null, null, false],
            'pg-foreign' => ['pg', 'CREATE TABLE probe(a INT REFERENCES parent(id))', true, true, false, null, null, false],
            'sqlite-temp' => ['sqlite', 'CREATE TEMP TABLE probe(a INT)', true, true, false, null, null, false],
            'sqlite-temporary' => ['sqlite', 'CREATE TEMPORARY TABLE probe(a INT)', true, true, false, null, null, false],
            'sqlite-expression-key' => ['sqlite', 'CREATE TABLE probe(a INT, b INT, PRIMARY KEY(a+b,b))', false, false, false, null, null, false],
            'sqlite-expression-unique' => ['sqlite', 'CREATE TABLE probe(a INT, b INT, UNIQUE(a+b,b))', false, false, false, null, null, false],
            'sqlite-parenthesized-key' => ['sqlite', 'CREATE TABLE probe(a INT, PRIMARY KEY((a)))', true, true, false, null, null, false],
            'sqlite-collated-key' => ['sqlite', 'CREATE TABLE probe(a INT, PRIMARY KEY(a COLLATE NOCASE))', true, true, false, null, null, false],
            'sqlite-quoted-key' => ['sqlite', "CREATE TABLE probe(a INT, PRIMARY KEY('a'))", true, true, false, null, null, false],
            'sqlite-null-does-not-remove' => ['sqlite', 'CREATE TABLE probe(a INT NOT NULL NULL)', true, false, false, null, null, false],
            'sqlite-affinity-only' => ['sqlite', 'CREATE TABLE probe(a DECIMAL(5))', true, true, false, null, null, false],
            'sqlite-default' => ['sqlite', 'CREATE TABLE probe(a TEXT DEFAULT "x")', true, true, false, null, null, false],
            'sqlite-untyped' => ['sqlite', 'CREATE TABLE probe(a)', true, true, false, null, null, false],
            'sqlite-foreign' => ['sqlite', 'CREATE TABLE probe(a INT REFERENCES parent(id))', true, true, false, null, null, false],
        ];
    }

    /**
     * @return non-empty-list<string>
     */
    public static function versions(string $dialect): array
    {
        return match ($dialect) {
            'mysql' => ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
            'pg' => ['pg-16.6', 'pg-17.2'],
            default => ['sqlite-3.47.2'],
        };
    }
}
