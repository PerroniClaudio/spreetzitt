<?php

use App\Jobs\Concerns\SendsUniqueEmails;

it('sends once for normalized duplicate email addresses', function () {
    $job = new class
    {
        use SendsUniqueEmails;

        public function send(array &$sentEmails, ?string $email, Closure $callback): void
        {
            $this->sendEmailOnce($sentEmails, $email, $callback);
        }
    };
    $sentEmails = [];
    $recipients = [];

    $send = function (string $email) use (&$recipients): void {
        $recipients[] = $email;
    };

    $job->send($sentEmails, ' Support@example.com ', $send);
    $job->send($sentEmails, 'support@example.com', $send);
    $job->send($sentEmails, 'invalid-email', $send);

    expect($recipients)->toBe(['support@example.com']);
});
