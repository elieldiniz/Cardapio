<?php

it('renders the smoke-test livewire component on a test route', function () {
    $response = $this->get('/smoke-test/livewire');

    $response->assertOk();
    $response->assertSee('Livewire is wired up.');
});
