<?php

use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketAnswered;
use Livewire\Livewire;

function openTicketFor(User $dono, array $attributes = []): SupportTicket
{
    $ticket = SupportTicket::create(array_merge([
        'restaurant_id' => $dono->restaurant_id,
        'user_id' => $dono->id,
        'subject' => 'Vídeo não aparece',
        'category' => 'problema',
        'status' => SupportTicket::STATUS_OPEN,
        'last_message_at' => now(),
    ], $attributes));
    $ticket->messages()->create(['user_id' => $dono->id, 'from_staff' => false, 'body' => 'Não consigo ver o vídeo do prato.']);

    return $ticket;
}

it('lists the support screen in the navigation and shows the whatsapp shortcut when configured', function () {
    config()->set('landing.contact.whatsapp', '5569999999999');
    $dono = User::factory()->create();

    $this->actingAs($dono)->get(route('panel.support'))
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
        ->and($ticket->status)->toBe(SupportTicket::STATUS_OPEN)
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

it('only shows a dono their own restaurant tickets', function () {
    $dono = User::factory()->create();
    $other = User::factory()->create();
    openTicketFor($other, ['subject' => 'Chamado do vizinho']);

    $this->actingAs($dono)->get(route('panel.support'))->assertOk()->assertDontSee('Chamado do vizinho');
    $this->actingAs($dono)->get(route('panel.support.show', SupportTicket::first()))->assertNotFound();
});

it('puts a dono reply back in the support queue and can close the ticket', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono, ['status' => SupportTicket::STATUS_ANSWERED]);

    Livewire::actingAs($dono)->test('pages::panel.support-ticket', ['ticket' => $ticket])
        ->set('body', 'Ainda não funcionou.')
        ->call('reply')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_OPEN)
        ->and($ticket->messages()->count())->toBe(2);

    Livewire::actingAs($dono)->test('pages::panel.support-ticket', ['ticket' => $ticket])->call('close');

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_CLOSED);
});

it('lets the super admin see and answer tickets, notifying the dono', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(ListSupportTickets::class)->assertCanSeeTableRecords([$ticket]);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('reply', ['body' => 'Já corrigimos, pode testar de novo.'])
        ->assertHasNoActionErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(SupportTicket::STATUS_ANSWERED)
        ->and($ticket->messages()->latest('id')->first()->from_staff)->toBeTrue()
        ->and($dono->notifications)->toHaveCount(1)
        ->and($dono->notifications[0]->type)->toBe(SupportTicketAnswered::class);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])->callAction('close');

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_CLOSED);
});

it('marks the answer notice as read when the dono opens the ticket', function () {
    $dono = User::factory()->create();
    $ticket = openTicketFor($dono);
    $dono->notify(new SupportTicketAnswered($ticket));

    $this->actingAs($dono)->get(route('panel.support.show', $ticket))->assertOk()->assertSee('Vídeo não aparece');

    expect($dono->unreadNotifications()->count())->toBe(0);
});

it('does not let a dono into the admin ticket list', function () {
    $this->actingAs(User::factory()->create())->get('/admin/support-tickets')->assertForbidden();
});
