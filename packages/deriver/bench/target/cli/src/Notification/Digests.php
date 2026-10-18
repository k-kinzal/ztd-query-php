<?php

declare(strict_types=1);

namespace HarborOps\Notification;

use DateTimeImmutable;
use DateTimeZone;
use HarborOps\{Database};
use HarborOps\Queue\Queue;
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class Digests
{
    public function __construct(private readonly Database $database, private readonly Queue $queue, private readonly ClockInterface $clock, private readonly MailerInterface $mailer, private readonly string $sender)
    {
    }
    public function schedule(string $period): int
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $period, new DateTimeZone('UTC'));
        if (!$day || $day->format('Y-m-d') !== $period || $day >= $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0)) {
            throw new InvalidArgumentException('Choose a completed UTC date in YYYY-MM-DD format.');
        }
        return $this->database->transaction(function () use ($period): int {
            $count = 0;
            foreach ($this->database->rows('SELECT id FROM members WHERE digest_enabled=1 AND erased_at IS NULL') as $member) {
                $job = $this->queue->enqueue('digest', ['member_id' => $member['id'],'period' => $period], 'digest:'.$member['id'].':'.$period);
                $count += $this->database->execute('INSERT INTO deliveries(member_id,period,job_id) VALUES (?,?,?) ON CONFLICT DO NOTHING', [$member['id'],$period,$job]);
            }
            return $count;
        });
    }
    public function send(array $payload): void
    {
        $member = $this->database->one('SELECT * FROM members WHERE id=? AND digest_enabled=1 AND erased_at IS NULL', [$payload['member_id']]);
        if ($member === null) {
            return;
        }
        $delivery = $this->database->one('SELECT * FROM deliveries WHERE member_id=? AND period=?', [$member['id'],$payload['period']]);
        if ($delivery === null || $delivery['sent_at'] !== null) {
            return;
        }
        $start = (new DateTimeImmutable($payload['period'].'T00:00:00+00:00'))->getTimestamp();
        $posts = $this->database->rows("SELECT p.title,p.body FROM posts p JOIN sources s ON s.id=p.source_id WHERE s.community_id=? AND p.state='published' AND p.published_at>=? AND p.published_at<? ORDER BY p.id LIMIT 100", [$member['community_id'],$start,$start + 86400]);
        if ($posts !== []) {
            $sections = array_map(static fn (array $post): string => $post['title']."\n".mb_substr($post['body'], 0, 1000), $posts);
            $message = (new Email())->from($this->sender)->to($member['email'])->subject('Harbor community digest · '.$payload['period'])->text(implode("\n\n", $sections));
            $message->getHeaders()->addIdHeader('Message-ID', 'harbor-digest-'.$delivery['id'].'@'.substr(strrchr($this->sender, '@'), 1));
            $this->mailer->send($message);
        }
        $this->database->execute('UPDATE deliveries SET sent_at=? WHERE id=?', [$this->clock->now()->getTimestamp(),$delivery['id']]);
    }
}
