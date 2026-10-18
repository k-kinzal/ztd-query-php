<?php

declare(strict_types=1);

namespace HarborOps;

use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;

final class Audit
{
    public function __construct(private readonly Database $database, private readonly ClockInterface $clock, private readonly string $operator)
    {
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.@-]{1,99}$/D', $operator)) {
            throw new InvalidArgumentException('Set HARBOR_OPERATOR to an attributable operating-system principal.');
        }
    }
    public function operator(): string
    {
        return $this->operator;
    }
    public function record(string $action, string $subject, array $details = []): void
    {
        $this->database->execute('INSERT INTO audit(operator,action,subject,details,occurred_at) VALUES (?,?,?,?,?)', [$this->operator,$action,$subject,json_encode($details, JSON_THROW_ON_ERROR),$this->clock->now()->getTimestamp()]);
    }
}
