<?php

declare(strict_types=1);

namespace HarborOps\Import;

use DateTimeImmutable;
use DomainException;
use HarborOps\Database;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final class Importer
{
    public function __construct(private readonly Database $database, private readonly ClockInterface $clock, private readonly ValidatorInterface $validator, private readonly SerializerInterface $serializer, private readonly EventDispatcherInterface $events)
    {
    }

    public function import(int $sourceId, mixed $stream): array
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Input must be a readable JSON Lines stream.');
        }
        return $this->database->transaction(function () use ($sourceId, $stream): array {
            $source = $this->database->one('SELECT * FROM sources WHERE id = ? AND enabled = 1', [$sourceId]);
            if ($source === null) {
                throw new DomainException('The source does not exist or is disabled.');
            }
            $counts = ['inserted' => 0,'unchanged' => 0];
            $line = 0;
            while (($json = fgets($stream, 262146)) !== false) {
                ++$line;
                if (strlen($json) > 262144) {
                    throw new DomainException('Line ' . $line . ' exceeds 256 KiB.');
                }
                if (trim($json) === '') {
                    continue;
                }
                try {
                    $post = $this->serializer->deserialize($json, IncomingPost::class, 'json', ['allow_extra_attributes' => false]);
                    $violations = $this->validator->validate($post);
                    if (count($violations) > 0) {
                        throw new DomainException((string) $violations);
                    }
                    $fingerprint = hash('sha256', json_encode([$post->memberId,$post->title,$post->body,$post->publishedAt], JSON_THROW_ON_ERROR));
                    $existing = $this->database->one('SELECT * FROM posts WHERE source_id = ? AND external_id = ?', [$sourceId,$post->id]);
                    if ($existing !== null) {
                        if (!hash_equals($existing['fingerprint'], $fingerprint)) {
                            throw new DomainException('Existing external ID contains different content; use the correction workflow.');
                        }
                        ++$counts['unchanged'];
                        continue;
                    }
                    $member = $this->database->one('SELECT * FROM members WHERE community_id = ? AND external_id = ?', [$source['community_id'],$post->memberId]);
                    if ($member !== null && $member['erased_at'] !== null) {
                        throw new DomainException('An erased member cannot be reimported.');
                    }
                    $now = $this->clock->now()->getTimestamp();
                    $published = new DateTimeImmutable($post->publishedAt);
                    if ($published->getTimestamp() > $now + 300) {
                        throw new DomainException('Publication time is in the future.');
                    }
                    if ($member === null) {
                        $this->database->execute('INSERT INTO members(community_id,external_id,email,display_name,created_at) VALUES (?,?,?,?,?)', [$source['community_id'],$post->memberId,$post->email,$post->displayName,$now]);
                        $member = ['id' => (int)$this->database->pdo->lastInsertId()];
                    }
                    $this->database->execute('INSERT INTO posts(source_id,external_id,member_id,title,body,fingerprint,published_at,created_at) VALUES (?,?,?,?,?,?,?,?)', [$sourceId,$post->id,$member['id'],$post->title,$post->body,$fingerprint,$published->getTimestamp(),$now]);
                    $this->events->dispatch(new PostImported((int)$this->database->pdo->lastInsertId(), (int)$source['community_id'], $post->id));
                    ++$counts['inserted'];
                } catch (Throwable $error) {
                    throw new DomainException('Import line ' . $line . ': ' . $error->getMessage(), 0, $error);
                }
            }
            if (!feof($stream)) {
                throw new RuntimeException('Input stream could not be read to completion.');
            }
            return $counts;
        });
    }
}
