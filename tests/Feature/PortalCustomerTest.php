<?php

use Darvis\Signer\Models\Contact;
use Darvis\Signer\Models\Customer;
use Darvis\Signer\Models\Document;

beforeEach(function () {
    $this->actingAs($this->createAdminUser());
});

it('creates a customer', function () {
    $this->post(route('signer.portal.customers.store'), [
        'name' => 'Alice Jansen',
        'company' => 'Acme BV',
        'email' => 'alice@acme.test',
        'city' => 'Amsterdam',
    ])->assertRedirect();

    $this->assertDatabaseHas('signer_customers', [
        'name' => 'Alice Jansen',
        'company' => 'Acme BV',
    ]);
});

it('requires a name for a customer', function () {
    $this->post(route('signer.portal.customers.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('updates a customer', function () {
    $customer = Customer::create(['name' => 'Old name']);

    $this->put(route('signer.portal.customers.update', $customer), [
        'name' => 'New name',
    ])->assertRedirect();

    expect($customer->refresh()->name)->toBe('New name');
});

it('deletes a customer with its contacts', function () {
    $customer = Customer::create(['name' => 'Alice']);
    $customer->contacts()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

    $this->delete(route('signer.portal.customers.destroy', $customer))
        ->assertRedirect(route('signer.portal.customers.index'));

    $this->assertDatabaseCount('signer_customers', 0);
    $this->assertDatabaseCount('signer_contacts', 0);
});

it('adds and removes a contact', function () {
    $customer = Customer::create(['name' => 'Alice']);

    $this->post(route('signer.portal.customers.contacts.store', $customer), [
        'name' => 'Bob',
        'email' => 'bob@example.com',
    ])->assertRedirect();

    $contact = Contact::first();
    expect($contact->customer_id)->toBe($customer->id);

    $this->delete(route('signer.portal.contacts.destroy', $contact))->assertRedirect();

    $this->assertDatabaseCount('signer_contacts', 0);
});

it('keeps documents when their customer is deleted', function () {
    $customer = Customer::create(['name' => 'Alice']);

    $document = Document::create([
        'customer_id' => $customer->id,
        'title' => 'Contract',
        'original_path' => 'signer/originals/test.pdf',
    ]);

    $customer->delete();

    expect($document->refresh()->customer_id)->toBeNull();
});
