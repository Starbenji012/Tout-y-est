<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

final class LoginThrottleService
{
    private const SESSION_KEY = '_login_throttle';
    private const WINDOW_SECONDS = 600;
    private const HELP_THRESHOLD = 3;
    private const THROTTLE_THRESHOLD = 5;
    private const MAX_DELAY_SECONDS = 120;

    public function status(): array
    {
        $now = time();
        $state = Session::get(self::SESSION_KEY, []);
        $attempts = array_values(array_filter(
            is_array($state['attempts'] ?? null) ? $state['attempts'] : [],
            static fn (mixed $attempt): bool => is_int($attempt) && $attempt >= $now - self::WINDOW_SECONDS,
        ));
        $blockedUntil = max(0, (int) ($state['blocked_until'] ?? 0));

        if ($blockedUntil <= $now) {
            $blockedUntil = 0;
        }

        $this->store($attempts, $blockedUntil);

        return $this->result($attempts, $blockedUntil, $now);
    }

    public function recordFailure(): array
    {
        $status = $this->status();
        $attempts = $status['attempts'];
        $attempts[] = time();
        $blockedUntil = 0;

        if (count($attempts) >= self::THROTTLE_THRESHOLD) {
            $delay = min(
                self::MAX_DELAY_SECONDS,
                30 * (count($attempts) - self::THROTTLE_THRESHOLD + 1),
            );
            $blockedUntil = time() + $delay;
        }

        $this->store($attempts, $blockedUntil);

        return $this->result($attempts, $blockedUntil, time());
    }

    public function clear(): void
    {
        Session::remove(self::SESSION_KEY);
    }

    private function store(array $attempts, int $blockedUntil): void
    {
        Session::set(self::SESSION_KEY, [
            'attempts' => $attempts,
            'blocked_until' => $blockedUntil,
        ]);
    }

    private function result(array $attempts, int $blockedUntil, int $now): array
    {
        $count = count($attempts);

        return [
            'attempts' => $attempts,
            'count' => $count,
            'showHelp' => $count >= self::HELP_THRESHOLD,
            'blocked' => $blockedUntil > $now,
            'retryAfter' => max(0, $blockedUntil - $now),
        ];
    }
}
