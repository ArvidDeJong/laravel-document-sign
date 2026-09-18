# Laravel Document Sign

Onderteken PDF documenten in Laravel met een flow zoals DocuSign. Je uploadt een document, nodigt ondertekenaars uit per mail, zij tekenen via een beveiligde link op een kant en klare ondertekenpagina en de package stempelt alle handtekeningen op de PDF, inclusief volledige audit trail.

De package bevat daarnaast een compleet portaal met login, dashboard, klantenbeheer en documentenbeheer. Daarmee werkt een verse Laravel installatie na `composer require` en `php artisan migrate` direct als zelfstandig ondertekensysteem.

## Vereisten

* PHP 8.3 of hoger
* Laravel 12
* GD extensie (voor het verwerken van handtekeningafbeeldingen)

## Installatie

```bash
composer require darvis/laravel-document-sign
php artisan migrate
```

Publiceer optioneel de config en views:

```bash
php artisan vendor:publish --tag=signer-config
php artisan vendor:publish --tag=signer-views
```

## Het portaal

Na installatie is het portaal direct beschikbaar op `/portal`. Inloggen gebeurt met een gebruiker uit de `users` tabel van je applicatie, dus maak er een aan als die er nog niet is:

```bash
php artisan tinker
> App\Models\User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('geheim')]);
```

Het portaal biedt:

* Dashboard met tellers en recente documenten
* Klantenbeheer: bedrijven of personen met contactpersonen
* Documenten versturen: PDF uploaden, klant kiezen, ondertekenaars voorvullen vanuit de contactpersonen van de klant
* Documentdetail met status per ondertekenaar, audit trail en downloads van het origineel en de ondertekende PDF

De UI gebruikt Tailwind via CDN, dus er is geen build stap nodig. De teksten zijn Engels met een meegeleverde Nederlandse vertaling; zet `'locale' => 'nl'` in `config/app.php` voor een Nederlands portaal. Wil je alleen de ondertekenflow zonder portaal, zet dan `SIGNER_PORTAL_ENABLED=false` in je `.env`. De prefix, middleware en guard stel je in via `config/signer.php`.

## Gebruik zonder portaal

```php
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('contracten/contract.pdf'))
    ->title('Freelance overeenkomst')
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 1, x: 55, y: 80)
    ->send();
```

Elke ondertekenaar ontvangt een mail met een tijdelijk geldige, ondertekende link naar de ondertekenpagina. Daar ziet de ondertekenaar de PDF, tekent op een canvas en bevestigt. Zodra iedereen heeft getekend genereert de package de definitieve PDF met alle handtekeningen erop gestempeld.

### Posities

De positie van een handtekening geef je op in procenten van de pagina, gemeten vanaf linksboven. Zo werkt dezelfde positie op elk papierformaat. De parameter `width` bepaalt de breedte van de handtekening in procenten van de paginabreedte en valt terug op `default_signature_width` uit de config.

### Status opvragen

```php
$document->refresh();

$document->status;          // DocumentStatus enum: Draft, Pending, Completed, Cancelled
$document->isFullySigned(); // true zodra iedereen heeft getekend
$document->signed_path;     // pad van de definitieve PDF op de geconfigureerde disk
$document->auditEvents;     // volledige audit trail
```

### Events

De package dispatcht twee events waar je in je applicatie op kunt reageren:

* `Darvis\Signer\Events\SignerSigned` zodra een ondertekenaar heeft getekend
* `Darvis\Signer\Events\DocumentCompleted` zodra het document compleet is

```php
use Darvis\Signer\Events\DocumentCompleted;

Event::listen(function (DocumentCompleted $event) {
    // bijvoorbeeld: mail de definitieve PDF naar alle partijen
});
```

### Audit trail

Elke stap wordt vastgelegd in `signer_audit_events`, met IP adres en user agent waar beschikbaar: `document_created`, `invitation_sent`, `document_signed` en `document_completed`.

## Configuratie

`config/signer.php`:

* `disk`: de filesystem disk voor originelen, handtekeningen en definitieve PDF bestanden
* `storage_path`: basispad binnen de disk
* `link_expires_after_hours`: geldigheidsduur van de ondertekenlink in uren
* `route_prefix` en `route_middleware`: routes van de ondertekenpagina
* `portal`: portaal aan of uit, prefix, middleware en guard
* `default_signature_width`: standaard breedte van een handtekening in procenten

## Testen

```bash
composer install
composer test
```

De tests draaien met Pest en Orchestra Testbench en dekken de volledige flow: aanmaken, uitnodigen, ondertekenen, stempelen en de audit trail, plus randgevallen zoals dubbel ondertekenen, ongeldige links en ongeldige handtekeningdata.

## Ontwikkeling

Ontwikkel je mee aan deze package binnen een Laravel applicatie, installeer dan ook [Laravel Boost](https://github.com/laravel/boost) in die applicatie voor betere AI ondersteuning tijdens het bouwen.

## Licentie

MIT
