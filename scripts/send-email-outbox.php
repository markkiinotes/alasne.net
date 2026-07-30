<?php

declare(strict_types=1);

use App\Services\Mail\EmailOutboxSender;

require __DIR__ . '/../bootstrap/app.php';

$sender = app()->container->make(EmailOutboxSender::class);

$results = $sender->sendPending();

foreach ($results['messages'] as $message) {
    echo $message . PHP_EOL;
}

echo 'Done. Sent: ' . $results['sent'] . '. Failed: ' . $results['failed'] . '.' . PHP_EOL;