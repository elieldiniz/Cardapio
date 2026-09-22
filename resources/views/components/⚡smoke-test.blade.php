<?php

use Livewire\Component;

new class extends Component
{
    public string $message = 'Livewire is wired up.';
};
?>

<div data-testid="smoke-test">
    {{ $message }}
</div>
