<?php

declare(strict_types=1);

namespace Harbor\Database;

use RuntimeException;

final class Migrator
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function migrate(string $directory): void
    {
        $this->database->execute('CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY)');
        $files = glob($directory . '/*.sql');
        sort($files);
        foreach ($files as $file) {
            $this->database->transaction(function () use ($file): void {
                $name = basename($file);
                if ($this->database->one('SELECT name FROM migrations WHERE name = ?', [$name]) !== null) {
                    return;
                }
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException('Cannot read migration ' . $name);
                }
                $this->database->pdo->exec($sql);
                $this->database->execute('INSERT INTO migrations(name) VALUES (?)', [$name]);
            });
        }
    }
}
