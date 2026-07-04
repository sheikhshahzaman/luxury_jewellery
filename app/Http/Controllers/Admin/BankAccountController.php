<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()->route('admin.bank-accounts.index')->with('success', 'Bank account added — it now appears at checkout.');
    }

    public function edit(BankAccount $bank_account): View
    {
        return view('admin.bank-accounts.form', ['account' => $bank_account]);
    }

    public function update(Request $request, BankAccount $bank_account): RedirectResponse
    {
        $bank_account->update($this->validated($request));

        return redirect()->route('admin.bank-accounts.index')->with('success', 'Bank account updated.');
    }

    public function destroy(BankAccount $bank_account): RedirectResponse
    {
        $bank_account->delete();

        return redirect()->route('admin.bank-accounts.index')->with('success', 'Bank account removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
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
