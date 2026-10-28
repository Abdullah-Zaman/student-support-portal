<?php

use App\Providers\AppServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

test('MAIL_ALWAYS_TO redirects every outgoing email outside production', function () {
    config(['mail.always_to' => 'catch-all@superior.edu.pk']);
    (new AppServiceProvider(app()))->boot();

    $capturedRecipients = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$capturedRecipients) {
        $capturedRecipients = array_map(fn ($address) => $address->getAddress(), $event->message->getTo());
    });

    Mail::raw('Test body', function ($message) {
        $message->to('real.student@superior.edu.pk')->subject('Test subject');
    });

    expect($capturedRecipients)->toBe(['catch-all@superior.edu.pk']);
});

test('mail is not redirected when MAIL_ALWAYS_TO is blank', function () {
    config(['mail.always_to' => null]);
    (new AppServiceProvider(app()))->boot();

    $capturedRecipients = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$capturedRecipients) {
        $capturedRecipients = array_map(fn ($address) => $address->getAddress(), $event->message->getTo());
    });

    Mail::raw('Test body', function ($message) {
        $message->to('real.student@superior.edu.pk')->subject('Test subject');
    });

    expect($capturedRecipients)->toBe(['real.student@superior.edu.pk']);
});

test('mail is never redirected in production, even when MAIL_ALWAYS_TO is set', function () {
    config(['mail.always_to' => 'catch-all@superior.edu.pk']);
    app()->instance('env', 'production');
    (new AppServiceProvider(app()))->boot();

    $capturedRecipients = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$capturedRecipients) {
        $capturedRecipients = array_map(fn ($address) => $address->getAddress(), $event->message->getTo());
    });

    Mail::raw('Test body', function ($message) {
        $message->to('real.student@superior.edu.pk')->subject('Test subject');
    });

    expect($capturedRecipients)->toBe(['real.student@superior.edu.pk']);
});

test('MAIL_STUDENTS_TO redirects the portal\'s own mail to the test inbox when MAIL_ALWAYS_TO is blank', function () {
    config(['mail.always_to' => null, 'mail.students_to' => 'test-inbox@superior.edu.pk']);
    (new AppServiceProvider(app()))->boot();

    $capturedRecipients = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$capturedRecipients) {
        $capturedRecipients = array_map(fn ($address) => $address->getAddress(), $event->message->getTo());
    });

    Mail::raw('Test body', function ($message) {
        $message->to('su92-bssem-f22-172@superior.edu.pk')->subject('Test subject');
    });

    expect($capturedRecipients)->toBe(['test-inbox@superior.edu.pk']);
});

test('MAIL_STUDENTS_TO is ignored in production', function () {
    config(['mail.always_to' => null, 'mail.students_to' => 'test-inbox@superior.edu.pk']);
    app()->instance('env', 'production');
    (new AppServiceProvider(app()))->boot();

    $capturedRecipients = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$capturedRecipients) {
        $capturedRecipients = array_map(fn ($address) => $address->getAddress(), $event->message->getTo());
    });

    Mail::raw('Test body', function ($message) {
        $message->to('su92-bssem-f22-172@superior.edu.pk')->subject('Test subject');
    });

    expect($capturedRecipients)->toBe(['su92-bssem-f22-172@superior.edu.pk']);
});
