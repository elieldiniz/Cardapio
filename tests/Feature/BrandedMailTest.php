<?php

use App\Models\User;
use App\Notifications\PaymentFailed;

beforeEach(fn () => seedReferenceData());

it('embeds the wordmark in the e-mail instead of relying on web fonts', function () {
    config()->set('mail.default', 'array');
    $dono = User::factory()->create(['name' => 'Ana Souza']);

    $dono->notifyNow(new PaymentFailed, ['mail']);

    $email = app('mailer')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    $inline = collect($email->getAttachments())->map(fn ($part) => $part->getFilename())->all();

    expect($inline)->toContain('symbol', 'wordmark-light', 'wordmark-dark')
        ->and($email->getHtmlBody())->toContain('Olá, <strong')
        ->and($email->getHtmlBody())->toContain('Não conseguimos cobrar sua assinatura.');
});
