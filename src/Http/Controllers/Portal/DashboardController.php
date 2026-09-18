<?php

namespace Darvis\Signer\Http\Controllers\Portal;

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Models\Customer;
use Darvis\Signer\Models\Document;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('signer::portal.dashboard', [
            'customerCount' => Customer::count(),
            'pendingCount' => Document::where('status', DocumentStatus::Pending)->count(),
            'completedCount' => Document::where('status', DocumentStatus::Completed)->count(),
            'recentDocuments' => Document::with('customer')->latest()->limit(10)->get(),
        ]);
    }
}
