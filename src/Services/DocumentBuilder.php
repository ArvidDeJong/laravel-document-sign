<?php

namespace Darvis\Signer\Services;

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Models\Customer;
use Darvis\Signer\Models\Document;
use Darvis\Signer\Notifications\SignatureRequested;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DocumentBuilder
{
    protected string $title = 'Document';

    protected ?Customer $customer = null;

    /** @var array<int, array{name: string, email: string, page: int, x: float, y: float, width: float|null}> */
    protected array $signers = [];

    public function __construct(
        protected string $pdfPath,
        protected AuditLogger $auditLogger,
        protected SignerConfig $config,
    ) {
        if (! is_file($pdfPath)) {
            throw new InvalidArgumentException("PDF file not found at [{$pdfPath}].");
        }
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function forCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Add a signer. Position values are percentages of the page size.
     */
    public function addSigner(
        string $name,
        string $email,
        int $page = 1,
        float $x = 10.0,
        float $y = 80.0,
        ?float $width = null,
    ): static {
        $this->signers[] = compact('name', 'email', 'page', 'x', 'y', 'width');

        return $this;
    }

    /**
     * Store the document, create the signers and mail every signing invitation.
     */
    public function send(): Document
    {
        if ($this->signers === []) {
            throw new InvalidArgumentException('Add at least one signer before sending.');
        }

        $document = $this->createDocument();

        foreach ($document->signers as $signer) {
            $signer->notify(new SignatureRequested($signer));
            $this->auditLogger->log($document, 'invitation_sent', $signer);
        }

        return $document;
    }

    protected function createDocument(): Document
    {
        $document = Document::create([
            'customer_id' => $this->customer?->id,
            'title' => $this->title,
            'original_path' => $this->storeOriginal(),
            'status' => DocumentStatus::Pending,
        ]);

        foreach ($this->signers as $signer) {
            $document->signers()->create($signer);
        }

        $this->auditLogger->log($document, 'document_created');

        return $document->load('signers');
    }

    protected function storeOriginal(): string
    {
        $path = $this->config->storagePath().'/originals/'.uniqid().'.pdf';

        $contents = file_get_contents($this->pdfPath);

        if ($contents === false) {
            throw new InvalidArgumentException("PDF file at [{$this->pdfPath}] could not be read.");
        }

        Storage::disk($this->config->disk())->put($path, $contents);

        return $path;
    }
}
