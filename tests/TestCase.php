<?php

namespace Darvis\Signer\Tests;

use Darvis\Signer\SignerServiceProvider;
use Darvis\Signer\Tests\Fixtures\User;
use FPDF;
use Illuminate\Support\Facades\Hash;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SignerServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    /**
     * Create a portal admin user with the given password.
     */
    protected function createAdminUser(string $password = 'secret-password'): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
        ]);
    }

    /**
     * Generate a simple two page PDF and return its temporary file path.
     */
    protected function createSamplePdf(): string
    {
        $pdf = new FPDF();

        foreach ([1, 2] as $page) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 14);
            $pdf->Cell(0, 10, "Sample contract page {$page}");
        }

        $path = tempnam(sys_get_temp_dir(), 'signer-test').'.pdf';
        $pdf->Output('F', $path);

        return $path;
    }

    /**
     * A tiny valid PNG as a base64 data URL, like the signature pad submits.
     */
    protected function signatureDataUrl(): string
    {
        $image = imagecreatetruecolor(120, 40);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 10, 30, 110, 10, imagecolorallocate($image, 0, 0, 0));

        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
