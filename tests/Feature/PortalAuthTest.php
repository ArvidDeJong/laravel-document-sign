<?php

it('redirects guests from the portal to the login page', function () {
    $this->get(route('signer.portal.dashboard'))
        ->assertRedirect(route('signer.portal.login'));
});

it('lets an admin log in and reach the dashboard', function () {
    $this->createAdminUser('super-secret');

    $this->post(route('signer.portal.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'super-secret',
    ])->assertRedirect(route('signer.portal.dashboard'));

    $this->assertAuthenticated();

    $this->get(route('signer.portal.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');
});

it('rejects invalid credentials', function () {
    $this->createAdminUser('super-secret');

    $this->from(route('signer.portal.login'))
        ->post(route('signer.portal.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs an admin out', function () {
    $this->actingAs($this->createAdminUser());

    $this->post(route('signer.portal.logout'))
        ->assertRedirect(route('signer.portal.login'));

    $this->assertGuest();
});

it('redirects a logged in admin away from the login page', function () {
    $this->actingAs($this->createAdminUser());

    $this->get(route('signer.portal.login'))
        ->assertRedirect(route('signer.portal.dashboard'));
});
