<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetalRate;
use App\Models\MetalRateChange;
use App\Models\Setting;
use App\Services\LiveRates;
use App\Services\SpotRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class RateController extends Controller
{
    public function index(SpotRates $spot, LiveRates $live): View
    {
        // Rupees per gram at spot today, for the auto-price preview beside each margin.
        // (Asked for a tonne so the rounding to whole rupees doesn't show in the per-gram figure.)
        $perGram = collect(MetalRate::INSTRUMENT)->map(fn ($instrument, $metal) => $live->autoPrice($metal, 1e6, 0, 0))
            ->map(fn ($p) => $p ? ['buy' => $p['buy'] / 1e6, 'sell' => $p['sell'] / 1e6] : null);

        return view('admin.rates', [
            'perGram' => $perGram,
            'rates' => MetalRate::ordered()->with('editor')->get()->groupBy('metal'),
            'changes' => MetalRateChange::with('user')->latest('id')->limit(15)->get(),
            'spot' => $spot->current(),
            'mode' => $spot->mode(),
            'status' => $spot->status(),
        ]);
    }

    /**
     * Save the price board. A manual item takes the typed buy/sell; an auto
     * item takes its margins and is priced from spot from then on. Each change
     * is written together with its audit record; the spot fetch never writes here.
     */
    public function update(Request $request, LiveRates $live): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['array'],
            'rows.*.metal' => ['required', Rule::in(array_keys(MetalRate::METALS))],
            'rows.*.key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'rows.*.label' => ['required', 'string', 'max:60'],
            'rows.*.note' => ['nullable', 'string', 'max:60'],
            'rows.*.unit' => ['required', Rule::in(array_keys(MetalRate::UNITS))],
            'rows.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'rows.*.pricing' => ['nullable', Rule::in(array_keys(MetalRate::PRICING))],
            'rows.*.buy_margin' => ['nullable', 'numeric', 'min:-50', 'max:200'],
            'rows.*.sell_margin' => ['nullable', 'numeric', 'min:-50', 'max:200', 'lte:rows.*.buy_margin'],
            // An auto item doesn't need typed prices; a manual one does.
            'rows.*.buy' => ['exclude_if:rows.*.pricing,auto', 'required', 'numeric', 'min:0', 'max:100000000'],
            'rows.*.sell' => ['exclude_if:rows.*.pricing,auto', 'required', 'numeric', 'min:0', 'max:100000000'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'rows.*.is_active' => ['nullable', 'boolean'],
            'rates_note' => ['nullable', 'string', 'max:300'],
            'rates_shuffle' => ['nullable', 'boolean'],
            'rates_shuffle_pkr_min' => ['required', 'integer', 'min:0', 'max:1000'],
            'rates_shuffle_pkr_max' => ['required', 'integer', 'gte:rates_shuffle_pkr_min', 'max:1000'],
            'rates_shuffle_seconds' => ['required', 'integer', 'min:2', 'max:60'],
        ], [
            'rates_shuffle_pkr_max.gte' => 'The largest move must be at least the smallest move.',
            'rows.*.label.required' => 'Every row needs a size, e.g. "1 Tola".',
            'rows.*.sell_margin.lte' => 'The sell margin can\'t be above the buy margin, or you would pay more than you charge.',
            'rows.*.key.regex' => 'Keys use lowercase letters, numbers and underscores only, e.g. silver_10_tola_qr.',
        ]);

        $rows = $data['rows'] ?? [];
        $existing = MetalRate::whereIn('id', array_filter(array_keys($rows), 'is_numeric'))->get()->keyBy('id');

        // Every existing row must be a configured item; new rows need a key no other item has.
        $taken = MetalRate::pluck('key')->all();
        $errors = [];
        foreach ($rows as $id => $row) {
            if (is_numeric($id)) {
                if (! $existing->has($id)) {
                    $errors["rows.$id.label"] = 'That rate no longer exists. Reload the page and try again.';
                }

                continue;
            }
            $key = $row['key'] ?? null;
            if ($key && in_array($key, $taken, true)) {
                $errors["rows.$id.key"] = "The key \"$key\" is already used by another item.";

                continue;
            }
            $rows[$id]['key'] = $key ?: MetalRate::makeKey($row['metal'], $row['quantity'], $row['unit'], $row['note'] ?? null, $taken);
            $taken[] = $rows[$id]['key'];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $userId = $request->user()->id;
        $changed = 0;

        DB::transaction(function () use ($rows, $existing, $userId, $live, &$changed) {
            foreach ($rows as $id => $row) {
                $rate = is_numeric($id) ? $existing[$id] : new MetalRate(['key' => $row['key']]);
                $before = $rate->exists ? ['buy' => (float) $rate->buy, 'sell' => (float) $rate->sell] : null;

                $auto = ($row['pricing'] ?? $rate->pricing) === 'auto';
                $buyMargin = $auto ? (float) ($row['buy_margin'] ?? $rate->buy_margin) : (float) $rate->buy_margin;
                $sellMargin = $auto ? (float) ($row['sell_margin'] ?? $rate->sell_margin) : (float) $rate->sell_margin;

                if ($auto) {
                    // Recorded only when the item is switched to auto or its margins change;
                    // the saved buy/sell is the price that gave, kept as the fallback.
                    $priceChanged = ! $rate->isAuto() || $buyMargin != (float) $rate->buy_margin || $sellMargin != (float) $rate->sell_margin
                        || $row['unit'] !== $rate->unit || (float) $row['quantity'] != (float) $rate->quantity;
                    $price = $priceChanged ? $live->autoPrice($row['metal'], MetalRate::gramsFor($row['unit'], (float) $row['quantity']), $buyMargin, $sellMargin) : null;
                    $row['buy'] = $price['buy'] ?? $before['buy'] ?? 0;
                    $row['sell'] = $price['sell'] ?? $before['sell'] ?? 0;
                } else {
                    $priceChanged = ! $before || $rate->isAuto() || $before['buy'] != (float) $row['buy'] || $before['sell'] != (float) $row['sell'];
                }

                $attributes = [
                    'metal' => $row['metal'],
                    'label' => $row['label'],
                    'note' => $row['note'] ?? null,
                    'unit' => $row['unit'],
                    'quantity' => $row['quantity'],
                    'pricing' => $auto ? 'auto' : 'manual',
                    'buy_margin' => $buyMargin,
                    'sell_margin' => $sellMargin,
                    'buy' => $row['buy'],
                    'sell' => $row['sell'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? false),
                ];

                $rate->fill($attributes);
                if ($priceChanged) {
                    $rate->updated_by = $userId;
                }
                $rate->save();

                if ($priceChanged) {
                    MetalRateChange::create([
                        'metal_rate_id' => $rate->id,
                        'key' => $rate->key,
                        'buy_before' => $before['buy'] ?? null,
                        'sell_before' => $before['sell'] ?? null,
                        'buy' => $row['buy'],
                        'sell' => $row['sell'],
                        'user_id' => $userId,
                    ]);
                    $changed++;
                }
            }
        });

        Setting::set('rates_note', (string) ($data['rates_note'] ?? ''));
        Setting::set('rates_shuffle', $request->boolean('rates_shuffle') ? '1' : '0');
        foreach (['rates_shuffle_pkr_min', 'rates_shuffle_pkr_max', 'rates_shuffle_seconds'] as $key) {
            Setting::set($key, (string) $data[$key]);
        }
        LiveRates::forget();

        return back()->with('success', $changed
            ? "Prices saved — $changed ".str('price')->plural($changed).' updated on the website.'
            : 'Saved. No buy or sell prices changed.');
    }

    /** Choose automatic or manual spot rates; in manual mode, save the typed values. */
    public function spot(Request $request, SpotRates $spot): RedirectResponse
    {
        $manual = $request->input('spot_mode') === 'manual';
        $data = $request->validate([
            'spot_mode' => ['required', Rule::in(['auto', 'manual'])],
            'spot' => [Rule::requiredIf($manual), 'array'],
            'spot.XAU.bid' => [Rule::requiredIf($manual), 'nullable', 'numeric', 'gt:0'],
            'spot.XAU.ask' => [Rule::requiredIf($manual), 'nullable', 'numeric', 'gte:spot.XAU.bid'],
            'spot.XAG.bid' => [Rule::requiredIf($manual), 'nullable', 'numeric', 'gt:0'],
            'spot.XAG.ask' => [Rule::requiredIf($manual), 'nullable', 'numeric', 'gte:spot.XAG.bid'],
            'spot.USDPKR.bid' => ['nullable', 'numeric', 'gt:0'],
            'spot.USDPKR.ask' => ['nullable', 'numeric', 'gte:spot.USDPKR.bid'],
        ], [
            'spot.*.ask.gte' => 'The ask must be at least the bid.',
        ]);

        Setting::set('spot_mode', $data['spot_mode']);

        if ($manual) {
            $values = collect($data['spot'])->filter(fn ($v) => ($v['bid'] ?? null) !== null && ($v['ask'] ?? null) !== null)->all();
            try {
                $spot->saveManual($values);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['spot' => $e->getMessage()]);
            }
        }
        SpotRates::forget();

        return back()->with('success', $manual
            ? 'Manual spot rates saved and shown on the website. Automatic fetching is paused.'
            : 'Spot rates are automatic again — they update '.rates_every().'.');
    }

    /** Fetch spot rates right now (server-side). Automatic mode only, so typed values aren't overtaken. */
    public function fetch(SpotRates $spot): RedirectResponse
    {
        if ($spot->mode() === 'manual') {
            return back()->with('error', 'Spot rates are on manual. Switch to automatic to fetch from the provider.');
        }

        ['saved' => $saved, 'failed' => $failed] = $spot->fetch(freshFx: true);

        if (! $saved) {
            return back()->with('error', 'Could not fetch spot rates; the last good values are still shown. '.collect($failed)->map(fn ($m, $i) => "$i: $m")->implode('; '));
        }

        return back()->with('success', 'Fetched '.implode(', ', array_keys($saved)).'.'
            .($failed ? ' Kept the last value for '.implode(', ', array_keys($failed)).'.' : ''));
    }

    public function destroy(MetalRate $rate): RedirectResponse
    {
        $rate->delete();
        LiveRates::forget();

        return back()->with('success', $rate->displayLabel().' removed from the rates board.');
    }
}
