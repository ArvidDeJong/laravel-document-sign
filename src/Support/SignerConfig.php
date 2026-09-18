<?php

namespace Darvis\Signer\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * The only place in the package that reads the `signer` config.
 *
 * Every consumer asks this class instead of calling config() itself, so a
 * renamed or defaulted key is changed in one spot and the analyser sees the
 * types.
 */
class SignerConfig
{
    public function __construct(
        protected Repository $config,
    ) {}

    /**
     * Default width of a placed signature in percent of the page width.
     */
    public function defaultSignatureWidth(): float
    {
        return (float) $this->config->get('signer.default_signature_width', 20.0);
    }

    /**
     * The filesystem disk that holds originals, signature images and signed PDFs.
     */
    public function disk(): string
    {
        return (string) $this->config->get('signer.disk', 'local');
    }

    /**
     * Hours a signing link stays valid after it has been sent.
     */
    public function linkExpiresAfterHours(): int
    {
        return (int) $this->config->get('signer.link_expires_after_hours', 72);
    }

    public function portalEnabled(): bool
    {
        return (bool) $this->config->get('signer.portal.enabled', true);
    }

    public function portalGuard(): string
    {
        return (string) $this->config->get('signer.portal.guard', 'web');
    }

    /**
     * @return array<int, string>
     */
    public function portalMiddleware(): array
    {
        return (array) $this->config->get('signer.portal.middleware', ['web']);
    }

    public function portalPrefix(): string
    {
        return (string) $this->config->get('signer.portal.prefix', 'portal');
    }

    /**
     * @return array<int, string>
     */
    public function routeMiddleware(): array
    {
        return (array) $this->config->get('signer.route_middleware', ['web']);
    }

    public function routePrefix(): string
    {
        return (string) $this->config->get('signer.route_prefix', 'sign');
    }

    /**
     * Base path within the disk for everything the package stores.
     */
    public function storagePath(): string
    {
        return (string) $this->config->get('signer.storage_path', 'signer');
    }
}
