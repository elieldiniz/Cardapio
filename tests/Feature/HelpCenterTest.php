<?php

use App\Models\User;
use Livewire\Livewire;

/**
 * RF-24 — the help center search.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
});

it('lists all the videos without a search', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.help')->assertSee('Abrir e fechar o caixa')->assertSee('Criar um cupom de desconto');
});

it('finds videos by a term in the title, description or tags, ignoring case and accents', function (string $term, string $expected) {
    Livewire::actingAs($this->dono)->test('pages::panel.help')->set('search', $term)->assertSee($expected);
})->with([
    'tag' => ['SANGRIA', 'Abrir e fechar o caixa'],
    'sem acento' => ['promocao', 'Cadastrar produtos, complementos e pizzas'],
    'descrição' => ['selos', 'Cartão fidelidade'],
]);

it('shows an empty state when nothing matches', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.help')->set('search', 'xyzabc')->assertSee('Nada encontrado')->assertDontSee('Abrir e fechar o caixa');
});

it('shows the support WhatsApp only when it is configured', function () {
    config(['help_videos.support_whatsapp' => '(69) 99604-1447']);
    Livewire::actingAs($this->dono)->test('pages::panel.help')->assertSeeHtml('data-support-whatsapp')->assertSeeHtml('phone=69996041447');

    config(['help_videos.support_whatsapp' => null]);
    Livewire::actingAs($this->dono)->test('pages::panel.help')->assertDontSeeHtml('data-support-whatsapp');
});

it('is reachable from the menu', function () {
    $this->actingAs($this->dono)->get(route('panel.help'))->assertOk()->assertSee('Ajuda');
});

it('plays a recorded video inside the card when the video is an mp4 of the app', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.help')->set('search', 'cupom')->assertSeeHtml('data-help-player')->assertSeeHtml('/videos/ajuda/cupons.mp4');

    expect(file_exists(public_path('videos/ajuda/cupons.mp4')))->toBeTrue();
});

it('shows "Vídeo em breve" for a topic that has no video yet', function () {
    config()->set('help_videos.videos', [['title' => 'Tema novo', 'description' => 'Ainda sem vídeo.', 'tags' => ['novo'], 'url' => null]]);

    Livewire::actingAs($this->dono)->test('pages::panel.help')->assertSee('Tema novo')->assertSee('Vídeo em breve')->assertDontSeeHtml('data-help-player');
});

it('has a recorded video file for every topic of the help center', function () {
    $videos = config('help_videos.videos');

    expect($videos)->toHaveCount(11);

    foreach ($videos as $video) {
        expect($video['url'])->toStartWith('/videos/ajuda/')->and(file_exists(public_path(ltrim($video['url'], '/'))))->toBeTrue("Falta o arquivo de {$video['title']}");
    }
});
