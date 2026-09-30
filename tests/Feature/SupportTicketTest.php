<?php

use App\Actions\Support\OpenSupportTicket;
use App\Actions\Support\ReplyToSupportTicket;
use App\Enums\TicketStatus;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\AdminLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketAnswered;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    RateLimiter::clear('x');
});

function openTicketFor(User $dono, array $attributes = [], TicketStatus $status = TicketStatus::Open): SupportTicket
{
    $ticket = SupportTicket::create(array_merge([
        'restaurant_id' => $dono->restaurant_id,
        'user_id' => $dono->id,
        'subject' => 'Vídeo não aparece',
        'category' => 'problema',
    ], $attributes));
    $ticket->forceFill(['status' => $status, 'last_message_at' => now()])->save();
    $ticket->messages()->create(['user_id' => $dono->id, 'from_staff' => false, 'body' => 'Não consigo ver o vídeo do prato.']);

    return $ticket;
}

// ---- Owner panel -------------------------------------------------------

it('shows the support screen with the whatsapp shortcut when configured', function () {
    config()->set('landing.contact.whatsapp', '5569999999999');

    $this->actingAs(User::factory()->create())->get(route('panel.support'))
        ->assertOk()
        ->assertSee('Suporte')
        ->assertSee('data-support-whatsapp', false)
        ->assertSee('wa.me/5569999999999', false);
});

it('hides the whatsapp shortcut when no number is configured', function () {
    config()->set('landing.contact.whatsapp', null);

    $this->actingAs(User::factory()->create())->get(route('panel.support'))
        ->assertOk()
        ->assertDontSee('data-support-whatsapp', false);
});

it('ignores formatting characters in the configured whatsapp number', function () {
    config()->set('landing.contact.whatsapp', '+55 (69) 99999-9999');

    $this->actingAs(User::factory()->create())->get(route('panel.support'))
        ->assertSee('wa.me/5569999999999', false);
});

it('lets the dono open a ticket', function () {
    $dono = User::factory()->create();

    Livewire::actingAs($dono)->test('pages::panel.support')
        ->set('showForm', true)
        ->set('category', 'video')
        ->set('subject', 'Vídeo não gerou')
        ->set('body', 'Pedi três variações e nenhuma apareceu.')
        ->call('open')
        ->assertHasNoErrors()
        ->assertRedirect();

    $ticket = SupportTicket::sole();

    expect($ticket->restaurant_id)->toBe($dono->restaurant_id)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->last_message_at)->not->toBeNull()
        ->and($ticket->messages)->toHaveCount(1)
        ->and($ticket->messages[0]->from_staff)->toBeFalse();
});

it('validates the new ticket form', function () {
    Livewire::actingAs(User::factory()->create())->test('pages::panel.support')
        ->set('subject', '')
        ->set('body', 'curto')
        ->call('open')
        ->assertHasErrors(['subject', 'body']);

    expect(SupportTicket::count())->toBe(0);
});

it('rejects an unknown category', function () {
    $dono = User::factory()->create();

    expect(fn () => app(OpenSupportTicket::class)->handle($dono, 'Assunto', 'inventada', 'Mensagem com tamanho suficiente.'))
        ->toThrow(ValidationException::class);
});

it('only shows a dono their own restaurant tickets', function () {
    $dono = User::factory()->create();
    $other = User::factory()->create();
    $foreign = openTicketFor($other, ['subject' => 'Chamado do vizinho']);

    $this->actingAs($dono)->get(route('panel.support'))->assertOk()->assertDontSee('Chamado do vizinho');
    $this->actingAs($dono)->get(route('panel.support.show', $foreign))->assertNotFound();
});

it('never lets a dono reply to or close another restaurant ticket', function () {
    $dono = User::factory()->create();
    $foreign = openTicketFor(User::factory()->create());

    expect(fn () => app(ReplyToSupportTicket::class)->handle($foreign, $dono, 'Intrometido'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReplyToSupportTicket::class)->close($foreign, $dono))->toThrow(AuthorizationException::class);
});

it('puts a dono reply back in the support queue and can close the ticket', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono, status: TicketStatus::Answered);

    Livewire::actingAs($dono)->test('pages::panel.support-ticket', ['ticket' => $ticket])
        ->set('body', 'Ainda não funcionou.')
        ->call('reply')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->messages()->count())->toBe(2);

    Livewire::actingAs($dono)->test('pages::panel.support-ticket', ['ticket' => $ticket])->call('close');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('reopens a closed ticket when the dono writes again', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono, status: TicketStatus::Closed);

    app(ReplyToSupportTicket::class)->handle($ticket, $dono, 'Voltou a acontecer.');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->fresh()->closed_at)->toBeNull();
});

it('validates the reply body', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);

    Livewire::actingAs($dono)->test('pages::panel.support-ticket', ['ticket' => $ticket])
        ->set('body', '')
        ->call('reply')
        ->assertHasErrors('body');

    expect(fn () => app(ReplyToSupportTicket::class)->handle($ticket, $dono, str_repeat('a', 5001)))->toThrow(ValidationException::class);
});

it('marks the answer notice as read when the dono opens the ticket', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);
    $dono->notify(new SupportTicketAnswered($ticket));

    $this->actingAs($dono)->get(route('panel.support.show', $ticket))->assertOk()->assertSee('Vídeo não aparece');

    expect($dono->unreadNotifications()->count())->toBe(0);
});

it('renders the reply box on the ticket screen and escapes message html', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);
    $ticket->messages()->create(['user_id' => $dono->id, 'from_staff' => false, 'body' => '<script>alert(1)</script>']);

    $this->actingAs($dono)->get(route('panel.support.show', $ticket))
        ->assertOk()
        ->assertSee('<textarea', false)
        ->assertDontSee('sr-only')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('paginates the ticket list', function () {
    $dono = User::factory()->create();
    foreach (range(1, 16) as $i) {
        openTicketFor($dono, ['subject' => "Chamado {$i}"], TicketStatus::Closed);
    }

    $component = Livewire::actingAs($dono)->test('pages::panel.support');

    expect($component->instance()->tickets)->toHaveCount(15)
        ->and($component->instance()->tickets->hasMorePages())->toBeTrue();
});

// ---- Abuse protection --------------------------------------------------

it('limits how many tickets a restaurant can open per hour', function () {
    $dono = User::factory()->create();
    $open = fn (int $i) => app(OpenSupportTicket::class)->handle($dono, "Assunto {$i}", 'duvida', 'Mensagem com tamanho suficiente.');

    foreach (range(1, OpenSupportTicket::HOURLY_LIMIT) as $i) {
        $open($i)->close();
    }

    expect(fn () => $open(99))->toThrow(ValidationException::class);
});

it('caps tickets waiting to be resolved', function () {
    $dono = User::factory()->create();

    foreach (range(1, OpenSupportTicket::MAX_UNRESOLVED) as $i) {
        openTicketFor($dono, ['subject' => "Aberto {$i}"]);
    }

    expect(fn () => app(OpenSupportTicket::class)->handle($dono, 'Mais um', 'duvida', 'Mensagem com tamanho suficiente.'))
        ->toThrow(ValidationException::class);
});

it('treats a double submit as the same ticket and the same reply', function () {
    $dono = User::factory()->create();
    $open = fn () => app(OpenSupportTicket::class)->handle($dono, 'Duplo clique', 'duvida', 'Mensagem com tamanho suficiente.');

    expect($open()->id)->toBe($open()->id)
        ->and(SupportTicket::count())->toBe(1);

    $ticket = SupportTicket::first();
    app(ReplyToSupportTicket::class)->handle($ticket, $dono, 'Mesma resposta');
    app(ReplyToSupportTicket::class)->handle($ticket, $dono, 'Mesma resposta');

    expect($ticket->messages()->count())->toBe(2);
});

it('throttles dono replies', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);

    foreach (range(1, ReplyToSupportTicket::OWNER_LIMIT_PER_10_MIN) as $i) {
        app(ReplyToSupportTicket::class)->handle($ticket, $dono, "Mensagem {$i}");
    }

    expect(fn () => app(ReplyToSupportTicket::class)->handle($ticket, $dono, 'Uma a mais'))->toThrow(ValidationException::class);
});

it('does not let anyone write as a dono while impersonating', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);
    $this->actingAs($dono)->withSession(['impersonator_id' => 1]);

    expect(fn () => app(ReplyToSupportTicket::class)->handle($ticket, $dono, 'Falando pelo dono'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(OpenSupportTicket::class)->handle($dono, 'Assunto', 'duvida', 'Mensagem com tamanho suficiente.'))->toThrow(AuthorizationException::class);

    $this->get(route('panel.support.show', $ticket))->assertOk()->assertSee('Modo suporte');
});

// ---- Ticket state ------------------------------------------------------

it('does not allow status changes through mass assignment', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);

    $ticket->update(['status' => 'fechado']);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

// ---- Super admin -------------------------------------------------------

it('lets the super admin see and answer tickets, notifying the dono and auditing', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(ListSupportTickets::class)->assertCanSeeTableRecords([$ticket]);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('reply', ['body' => 'Já corrigimos, pode testar de novo.'])
        ->assertHasNoActionErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Answered)
        ->and($ticket->messages()->reorder('id', 'desc')->first()->from_staff)->toBeTrue()
        ->and($dono->notifications)->toHaveCount(1)
        ->and($dono->notifications[0]->type)->toBe(SupportTicketAnswered::class);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])->callAction('close');
    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])->callAction('reopen');
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);

    expect(AdminLog::with('action')->orderBy('id')->get()->pluck('action.slug')->all())
        ->toBe(['chamado_respondido', 'chamado_fechado', 'chamado_reaberto']);
});

it('counts tickets waiting on support in the admin navigation badge', function () {
    $dono = User::factory()->create();
    openTicketFor($dono);
    openTicketFor($dono, ['subject' => 'Já respondido'], TicketStatus::Answered);
    openTicketFor($dono, ['subject' => 'Fechado'], TicketStatus::Closed);

    expect(SupportTicketResource::getNavigationBadge())->toBe('1');
});

it('keeps the admin ticket area away from donos', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);

    $this->actingAs($dono)->get('/admin/support-tickets')->assertForbidden();
    expect($dono->can('viewAny', SupportTicket::class))->toBeFalse()
        ->and($dono->can('reopen', $ticket))->toBeFalse()
        ->and($dono->can('delete', $ticket))->toBeFalse();
});
