<?php

use App\Support\PixPayload;

/**
 * RF-06c — the Pix copia e cola carries the server total and a valid CRC16.
 */
it('computes the CRC16-CCITT-FALSE check value', function () {
    expect(PixPayload::crc16('123456789'))->toBe('29B1');
});

it('builds a payload with the amount, the key and a valid CRC', function () {
    $payload = PixPayload::build('teste@docin.com.br', 'Docin Teste', 'Porto Velho', '129.90', 'D-X6ECG');

    expect($payload)->toStartWith('000201')
        ->and($payload)->toContain('br.gov.bcb.pix')
        ->and($payload)->toContain('0118teste@docin.com.br')
        ->and($payload)->toContain('5406129.90')
        ->and($payload)->toContain('5303986')
        ->and($payload)->toContain('5802BR')
        ->and($payload)->toContain('5911DOCIN TESTE')
        ->and($payload)->toContain('6011PORTO VELHO')
        ->and($payload)->toContain('0506DX6ECG');

    $body = substr($payload, 0, -4);

    expect(substr($payload, -4))->toBe(PixPayload::crc16($body))->and($body)->toEndWith('6304');
});

it('omits the amount when it is zero and falls back for blank holder and city', function () {
    $payload = PixPayload::build('chave', '', '', '0.00', null);

    expect($payload)->not->toContain('54')->and($payload)->toContain('RECEBEDOR')->and($payload)->toContain('BRASIL')->and($payload)->toContain('0503***');
});

it('strips accents and symbols from the holder and truncates long names', function () {
    $payload = PixPayload::build('chave', 'José da Conceição & Filhos Ltda Comércio', 'São Luís', '10.00');

    expect($payload)->toContain('JOSE DA CONCEICAO  FILHOS')->and($payload)->toContain('SAO LUIS');
});
