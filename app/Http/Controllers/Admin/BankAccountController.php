<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(): View
    {
        return view('admin.bank-accounts.index', [
            'accounts' => BankAccount::ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.bank-accounts.form', ['account' => new BankAccount]);
    }

    public function store(Request $request): RedirectResponse
    {
        BankAccount::create($this->validated($request));

        return redirect()->route('admin.bank-accounts.index')->with('success', 'Payment account added — it now appears at checkout.');
    }

    public function edit(BankAccount $bank_account): View
    {
        return view('admin.bank-accounts.form', ['account' => $bank_account]);
    }

    public function update(Request $request, BankAccount $bank_account): RedirectResponse
    {
        $bank_account->update($this->validated($request));

        return redirect()->route('admin.bank-accounts.index')->with('success', 'Payment account updated.');
    }

    public function destroy(BankAccount $bank_account): RedirectResponse
    {
        // Past orders show which account was paid; keep it and just hide it.
        if ($bank_account->orders()->exists()) {
            $bank_account->update(['is_active' => false]);

            return redirect()->route('admin.bank-accounts.index')
                ->with('success', 'This account is used by past orders, so it was hidden from checkout instead of deleted.');
        }

        $bank_account->delete();

        return redirect()->route('admin.bank-accounts.index')->with('success', 'Payment account removed.');
    }

    protected function validated(Request $request): array
    {
        $request->mergeIfMissing(['type' => 'bank']);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(BankAccount::TYPES))],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:60'],
            'iban' => ['nullable', 'string', 'max:60'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'branch' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
