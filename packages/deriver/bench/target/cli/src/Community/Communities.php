<?php

declare(strict_types=1);

namespace HarborOps\Community;

use DomainException;
use HarborOps\{Audit, Database};
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;

final class Communities
{
    public function __construct(private readonly Database $database, private readonly ClockInterface $clock, private readonly Audit $audit)
    {
    }
    public function create(string $slug, string $name, int $retention): int
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 80 || mb_strlen($name) < 3 || mb_strlen($name) > 120 || $retention < 30 || $retention > 3650) {
            throw new InvalidArgumentException('Provide a valid slug, name and retention between 30 and 3650 days.');
        }
        return $this->database->transaction(function () use ($slug, $name, $retention): int {
            $this->database->execute('INSERT INTO communities(slug,name,retention_days,created_at) VALUES (?,?,?,?)', [$slug,$name,$retention,$this->clock->now()->getTimestamp()]);
            $id = (int)$this->database->pdo->lastInsertId();
            $this->audit->record('community.created', (string)$id);
            return $id;
        });
    }
    public function source(int $community, string $name): int
    {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Source name must contain 2 to 100 characters.');
        }
        return $this->database->transaction(function () use ($community, $name): int {
            $this->database->execute('INSERT INTO sources(community_id,name) VALUES (?,?)', [$community,$name]);
            $id = (int)$this->database->pdo->lastInsertId();
            $this->audit->record('source.created', (string)$id);
            return $id;
        });
    }
    public function subscribe(int $member, bool $enabled): void
    {
        if ($this->database->execute('UPDATE members SET digest_enabled = ? WHERE id = ? AND erased_at IS NULL', [$enabled ? 1 : 0,$member]) !== 1) {
            throw new DomainException('Active member not found.');
        }
        $this->audit->record($enabled ? 'digest.opted_in' : 'digest.opted_out', (string)$member);
    }
}
