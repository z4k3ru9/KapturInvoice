<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Inquiry;
use App\Support\Homepage\PortfolioContent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The public homepage for one Company/entity, resolved by
 * App\Http\Middleware\ResolveCompanyFromDomain from the request's Host
 * header — a separate concern from Filament's tenancy, which resolves its
 * tenant from the URL path instead (see the middleware's docblock).
 */
#[Layout('layouts.public')]
class HomePage extends Component
{
    public Company $company;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public bool $submitted = false;

    public function mount(): void
    {
        // Bound by App\Http\Middleware\ResolveCompanyFromDomain, which this
        // component is only ever routed behind — abort rather than crash
        // on a typed-property null-assignment if that's somehow not true.
        abort_unless(app()->bound('currentCompany'), 404);

        $this->company = app('currentCompany');
    }

    public function submit(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        Inquiry::create([...$data, 'company_id' => $this->company->id]);

        $this->reset(['name', 'email', 'phone', 'message']);
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.home-page', [
            'portfolio' => PortfolioContent::for($this->company),
        ]);
    }
}
