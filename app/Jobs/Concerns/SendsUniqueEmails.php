<?php

namespace App\Jobs\Concerns;

use Closure;

trait SendsUniqueEmails
{
    /**
     * Send a message only once per normalized email address during this job.
     *
     * @param  array<string, true>  $sentEmails
     */
    protected function sendEmailOnce(array &$sentEmails, ?string $email, Closure $send): void
    {
        $normalizedEmail = strtolower(trim((string) $email));

        if (! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) || isset($sentEmails[$normalizedEmail])) {
            return;
        }

        $sentEmails[$normalizedEmail] = true;
        $send($normalizedEmail);
    }
}
