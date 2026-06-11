<?php

use function Pest\Laravel\get;

it('renders the public rules page', function (): void {
    get(route('rules'))
        ->assertOk()
        ->assertSee('Spielregeln')
        ->assertSee('Death Cup')
        ->assertSee('Balls Back')
        ->assertSee('Platzierungsspiele');
});

it('links to the rules page from the home header', function (): void {
    get(route('home'))
        ->assertOk()
        ->assertSee(route('rules'));
});
