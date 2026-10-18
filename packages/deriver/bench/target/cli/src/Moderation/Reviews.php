<?php

declare(strict_types=1);

namespace HarborOps\Moderation;

use DomainException;
use HarborOps\{Audit,Database};
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;

final class Reviews
{
    public function __construct(private readonly Database $database, private readonly ClockInterface $clock, private readonly Audit $audit, private readonly ReviewPolicy $policy)
    {
    }
    public function addRule(int $community, string $name, string $phrase, string $severity): int
    {
        $this->policy->initialState($severity);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100 || mb_strlen($phrase) < 3 || mb_strlen($phrase) > 500) {
            throw new InvalidArgumentException('Rule name and phrase must contain meaningful text.');
        }
        return $this->database->transaction(function () use ($community, $name, $phrase, $severity): int {
            $this->database->execute('INSERT INTO rules(community_id,name,phrase,severity) VALUES (?,?,?,?)', [$community,$name,$phrase,$severity]);
            $id = (int)$this->database->pdo->lastInsertId();
            $this->database->execute('UPDATE posts SET scanned_at = NULL WHERE source_id IN (SELECT id FROM sources WHERE community_id = ?) AND state <> ?', [$community,'removed']);
            $this->audit->record('rule.created', (string)$id);
            return $id;
        });
    }
    public function scan(int $limit): int
    {
        if ($limit < 1 || $limit > 10000) {
            throw new InvalidArgumentException('Limit must be between 1 and 10000.');
        }
        return $this->database->transaction(function () use ($limit): int {
            $posts = $this->database->rows("SELECT p.*,s.community_id FROM posts p JOIN sources s ON s.id=p.source_id WHERE p.scanned_at IS NULL AND p.state <> 'removed' ORDER BY p.id LIMIT " . $limit);
            foreach ($posts as $post) {
                foreach ($this->database->rows('SELECT * FROM rules WHERE community_id = ? AND enabled = 1', [$post['community_id']]) as $rule) {
                    if (mb_stripos($post['title'] . "\n" . $post['body'], $rule['phrase']) === false) {
                        continue;
                    }
                    $inserted = $this->database->execute('INSERT INTO cases(post_id,rule_id,created_at) VALUES (?,?,?) ON CONFLICT DO NOTHING', [$post['id'],$rule['id'],$this->clock->now()->getTimestamp()]);
                    if ($inserted && $this->policy->initialState($rule['severity']) === 'quarantined') {
                        $this->database->execute("UPDATE posts SET state='quarantined' WHERE id = ?", [$post['id']]);
                    }
                }
                $this->database->execute('UPDATE posts SET scanned_at = ? WHERE id = ?', [$this->clock->now()->getTimestamp(),$post['id']]);
            }
            $this->audit->record('moderation.scanned', 'posts', ['count' => count($posts)]);
            return count($posts);
        });
    }
    public function assign(int $case): void
    {
        if ($this->database->execute("UPDATE cases SET assignee = ? WHERE id = ? AND state = 'open' AND (assignee IS NULL OR assignee = ?)", [$this->audit->operator(),$case,$this->audit->operator()]) !== 1) {
            throw new DomainException('Case is closed or assigned to another operator.');
        }
        $this->audit->record('case.assigned', (string)$case);
    }
    public function resolve(int $id, string $decision, string $reason): void
    {
        $this->policy->resolutionState($decision);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('A reason of 10 to 2000 characters is required.');
        }
        $this->database->transaction(function () use ($id, $decision, $reason): void {
            $case = $this->database->one("SELECT * FROM cases WHERE id = ? AND state = 'open'", [$id]);
            if ($case === null || $case['assignee'] !== $this->audit->operator()) {
                throw new DomainException('Claim this open case before resolving it.');
            }
            $this->database->execute('UPDATE cases SET state = ?,decision_by = ?,reason = ?,resolved_at = ? WHERE id = ?', [$decision === 'uphold' ? 'upheld' : 'dismissed',$this->audit->operator(),$reason,$this->clock->now()->getTimestamp(),$id]);
            $this->refreshPost((int)$case['post_id']);
            $this->audit->record('case.resolved', (string)$id, ['decision' => $decision,'reason' => $reason]);
        });
    }
    public function appeal(int $case, string $reason): int
    {
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000 || !$this->database->one("SELECT id FROM cases WHERE id = ? AND state = 'upheld'", [$case])) {
            throw new DomainException('An upheld case and a detailed appeal reason are required.');
        }
        $this->database->execute('INSERT INTO appeals(case_id,reason,created_at) VALUES (?,?,?)', [$case,$reason,$this->clock->now()->getTimestamp()]);
        return (int)$this->database->pdo->lastInsertId();
    }
    public function decideAppeal(int $id, bool $restore, string $reason): void
    {
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('A detailed appeal resolution is required.');
        }
        $this->database->transaction(function () use ($id, $restore, $reason): void {
            $row = $this->database->one("SELECT a.*,c.post_id,c.decision_by AS original_reviewer FROM appeals a JOIN cases c ON c.id=a.case_id WHERE a.id = ? AND a.state='open'", [$id]);
            if ($row === null || $row['original_reviewer'] === $this->audit->operator()) {
                throw new DomainException('An independent reviewer must decide an open appeal.');
            }
            $this->database->execute('UPDATE appeals SET state = ?,decision_by = ?,resolution = ?,resolved_at = ? WHERE id = ?', [$restore ? 'accepted' : 'rejected',$this->audit->operator(),$reason,$this->clock->now()->getTimestamp(),$id]);
            if ($restore) {
                $this->database->execute("UPDATE cases SET state = 'dismissed' WHERE id = ?", [$row['case_id']]);
                $this->refreshPost((int)$row['post_id']);
            }
            $this->audit->record('appeal.decided', (string)$id, ['restored' => $restore,'reason' => $reason]);
        });
    }
    private function refreshPost(int $post): void
    {
        $upheld = $this->database->one("SELECT id FROM cases WHERE post_id = ? AND state = 'upheld'", [$post]);
        $urgent = $this->database->one("SELECT c.id FROM cases c JOIN rules r ON r.id=c.rule_id WHERE c.post_id = ? AND c.state='open' AND r.severity='urgent'", [$post]);
        $this->database->execute('UPDATE posts SET state = ? WHERE id = ?', [$upheld ? $this->policy->resolutionState('uphold') : ($urgent ? 'quarantined' : $this->policy->resolutionState('dismiss')),$post]);
    }
}
