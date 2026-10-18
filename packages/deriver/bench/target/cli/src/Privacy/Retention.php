<?php

declare(strict_types=1);

namespace HarborOps\Privacy;

use DomainException;
use HarborOps\{Audit,Database};
use Symfony\Component\Clock\ClockInterface;

final class Retention
{
    public function __construct(private readonly Database $database, private readonly ClockInterface $clock, private readonly Audit $audit)
    {
    }
    public function request(int $member, string $reason): int
    {
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000 || !$this->database->one('SELECT id FROM members WHERE id=? AND erased_at IS NULL', [$member])) {
            throw new DomainException('An active member and a detailed request reason are required.');
        }
        $this->database->execute('INSERT INTO erasure_requests(member_id,requested_by,reason,created_at) VALUES (?,?,?,?)', [$member,$this->audit->operator(),$reason,$this->clock->now()->getTimestamp()]);
        $id = (int)$this->database->pdo->lastInsertId();
        $this->audit->record('erasure.requested', (string)$id);
        return $id;
    }
    public function approve(int $request): void
    {
        if ($this->database->execute("UPDATE erasure_requests SET state='approved',approved_by=? WHERE id=? AND state='pending' AND requested_by<>?", [$this->audit->operator(),$request,$this->audit->operator()]) !== 1) {
            throw new DomainException('A second operator must approve a pending erasure.');
        }
        $this->audit->record('erasure.approved', (string)$request);
    }
    public function erase(int $request): void
    {
        $this->database->transaction(function () use ($request): void {
            $row = $this->database->one("SELECT * FROM erasure_requests WHERE id=? AND state='approved'", [$request]);
            if ($row === null) {
                throw new DomainException('An approved erasure request is required.');
            }
            $this->database->execute("UPDATE members SET email='',display_name='Deleted member',digest_enabled=0,erased_at=? WHERE id=?", [$this->clock->now()->getTimestamp(),$row['member_id']]);
            $this->database->execute("UPDATE posts SET title='[removed]',body='[removed by author]',state='removed' WHERE member_id=?", [$row['member_id']]);
            $this->database->execute("UPDATE erasure_requests SET state='done',completed_at=? WHERE id=?", [$this->clock->now()->getTimestamp(),$request]);
            $this->audit->record('erasure.completed', (string)$request);
        });
    }
    public function purge(bool $dryRun): int
    {
        return $this->database->transaction(function () use ($dryRun): int {
            $count = 0;
            foreach ($this->database->rows('SELECT * FROM communities') as $community) {
                $parameters = [$community['id'],$this->clock->now()->getTimestamp() - $community['retention_days'] * 86400];
                $condition = "source_id IN (SELECT id FROM sources WHERE community_id=?) AND published_at<? AND body<>'[expired]' AND NOT EXISTS (SELECT 1 FROM cases c WHERE c.post_id=posts.id AND c.state='open') AND NOT EXISTS (SELECT 1 FROM appeals a JOIN cases c ON c.id=a.case_id WHERE c.post_id=posts.id AND a.state='open')";
                $count += $dryRun ? (int)$this->database->one('SELECT COUNT(*) AS total FROM posts WHERE '.$condition, $parameters)['total'] : $this->database->execute("UPDATE posts SET title='[expired]',body='[expired]',state='removed' WHERE ".$condition, $parameters);
            }
            if (!$dryRun) {
                $this->audit->record('retention.purged', 'posts', ['count' => $count]);
            }
            return $count;
        });
    }
}
