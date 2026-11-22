<?php

declare(strict_types=1);

/**
 * Opens only the disposable fixture connection for the selected native server profile.
 */
function connectFixture(string $engine): array
{
    $prefix = $engine === 'mysql' ? 'ZTD_IR_MYSQL_' : 'ZTD_IR_POSTGRES_';
    $dsn = getenv($prefix . 'DSN');
    $user = getenv($prefix . 'USER');
    $password = getenv($prefix . 'PASSWORD');
    if ($dsn === false || $user === false || $password === false) {
        fwrite(STDERR, "Use a disposable database and set the server fixture connection settings.\n");
        exit(2);
    }
    $options = [PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => false];
    if ($engine === 'mysql') {
        $options[class_exists(Pdo\Mysql::class) ? Pdo\Mysql::ATTR_MULTI_STATEMENTS : PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
    }
    $pdo = new PDO($dsn, $user, $password, $options);

    if ($engine === 'mysql') {
        $pdo->exec("SET SESSION sql_mode = '', time_zone = '+00:00'");
        $pdo->exec('SET NAMES utf8mb4');
        $environment = $pdo->query('SELECT VERSION(), @@lower_case_table_names, @@sql_mode, @@character_set_connection, @@character_set_results')->fetch(PDO::FETCH_NUM);
    } else {
        $pdo->exec('SET standard_conforming_strings = on');
        $pdo->exec("SET client_encoding = 'UTF8'");
        $pdo->exec('SET search_path = pg_catalog, public');
        $pdo->exec("SET statement_timeout = '5s'");
        $environment = [];
        foreach (['server_version_num', 'server_encoding', 'client_encoding', 'standard_conforming_strings', 'search_path'] as $setting) {
            $environment[] = $pdo->query('SHOW ' . $setting)->fetchColumn();
        }
    }

    return [$pdo, $environment];
}
