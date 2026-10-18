<?php

declare(strict_types=1);

namespace HarborOps\Queue;

use DomainException;
use HarborOps\Database;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

final class Queue
{
    public function __construct(private readonly Database $database, private readonly ClockInterface $clock, private readonly RetryPolicy $policy)
    {
    }
    public function enqueue(string $kind, array $payload, string $key): int
    {
        $now = $this->clock->now()->getTimestamp();
        $this->database->execute('INSERT INTO jobs(kind,payload,deduplication_key,available_at,created_at) VALUES (?,?,?,?,?) ON CONFLICT DO NOTHING', [$kind,json_encode($payload, JSON_THROW_ON_ERROR),$key,$now,$now]);
        return (int)$this->database->one('SELECT id FROM jobs WHERE deduplication_key = ?', [$key])['id'];
    }
    public function claim(): ?array
    {
        return $this->database->transaction(function (): ?array {
            $now = $this->clock->now()->getTimestamp();
            $this->database->execute("UPDATE jobs SET state = CASE WHEN attempts >= 5 THEN 'dead' ELSE 'ready' END,lease_token=NULL,lease_until=NULL,last_error='Worker lease expired' WHERE state='leased' AND lease_until<=?", [$now]);
            $job = $this->database->one("SELECT * FROM jobs WHERE state='ready' AND available_at<=? ORDER BY available_at,id LIMIT 1", [$now]);
            if ($job === null) {
                return null;
            }
            $job['lease_token'] = bin2hex(random_bytes(24));
            $job['attempts']++;
            $this->database->execute("UPDATE jobs SET state='leased',attempts=attempts+1,lease_token=?,lease_until=? WHERE id=?", [$job['lease_token'],$now + $this->policy->leaseSeconds(),$job['id']]);
            $job['payload'] = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
            return $job;
        });
    }
    public function complete(array $job): void
    {
        if ($this->database->execute("UPDATE jobs SET state='done',completed_at=?,lease_token=NULL,lease_until=NULL WHERE id=? AND state='leased' AND lease_token=?", [$this->clock->now()->getTimestamp(),$job['id'],$job['lease_token']]) !== 1) {
            throw new DomainException('The job lease no longer belongs to this worker.');
        }
    }
    public function fail(array $job, Throwable $error): void
    {
        $state = $this->policy->exhausted($job['attempts']) ? 'dead' : 'ready';
        $this->database->execute('UPDATE jobs SET state=?,available_at=?,lease_token=NULL,lease_until=NULL,last_error=? WHERE id=? AND state=\'leased\' AND lease_token=?', [$state,$this->clock->now()->getTimestamp() + $this->policy->delay($job['attempts']),mb_substr($error->getMessage(), 0, 1000),$job['id'],$job['lease_token']]);
    }
    public function retry(int $id): void
    {
        if ($this->database->execute("UPDATE jobs SET state='ready',attempts=0,available_at=?,last_error=NULL WHERE id=? AND state='dead'", [$this->clock->now()->getTimestamp(),$id]) !== 1) {
            throw new DomainException('Only a dead job can be retried manually.');
        }
    }
}
