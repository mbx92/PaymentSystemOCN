<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\DocumentSequence;
use App\ERP\Core\Services\RuleBasedErpChatParser;
use App\Models\ErpChatParserRule;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ERPAdministrationMasterDataController extends Controller
{
    public function parserRules(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $query = ErpChatParserRule::query()->orderBy('priority')->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('intent_key', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        return Inertia::render('ERP/Admin/ParserRules', [
            'rules' => $query->get(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function storeParserRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'intent_key' => 'required|string|max:60',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'required|string|max:60',
            'priority' => 'required|integer|min:1|max:9999',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'response_text' => 'nullable|string|max:2000',
        ]);

        $validated['intent_key'] = strtolower(trim((string) $validated['intent_key']));
        $validated['keywords'] = collect($validated['keywords'])
            ->map(fn ($keyword) => strtolower(trim((string) $keyword)))
            ->filter()
            ->values()
            ->all();

        ErpChatParserRule::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Parser rule berhasil ditambahkan.']);
    }

    public function updateParserRule(Request $request, ErpChatParserRule $parserRule): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'intent_key' => 'required|string|max:60',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'required|string|max:60',
            'priority' => 'required|integer|min:1|max:9999',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'response_text' => 'nullable|string|max:2000',
        ]);

        $validated['intent_key'] = strtolower(trim((string) $validated['intent_key']));
        $validated['keywords'] = collect($validated['keywords'])
            ->map(fn ($keyword) => strtolower(trim((string) $keyword)))
            ->filter()
            ->values()
            ->all();

        $parserRule->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Parser rule berhasil diperbarui.']);
    }

    public function testParserRule(Request $request, RuleBasedErpChatParser $parser): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $result = $parser->parse($validated['message']);

        return back()->with('flash', [
            'type' => 'success',
            'message' => $result['matched']
                ? 'Parser match: '.$result['rule']['name'].' (intent: '.$result['rule']['intent_key'].')'
                : 'Parser belum menemukan rule yang cocok.',
        ]);
    }

    public function documentSequences(): Response
    {
        return Inertia::render('ERP/Admin/DocumentSequences', [
            'sequences' => DocumentSequence::query()->orderBy('module')->orderBy('document_type')->get(),
        ]);
    }

    public function storeDocumentSequence(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => 'required|string|max:50',
            'document_type' => 'required|string|max:100',
            'prefix' => 'required|string|max:20',
            'padding_length' => 'required|integer|min:3|max:10',
            'running_number' => 'required|integer|min:0',
        ]);

        DocumentSequence::query()->updateOrCreate(
            ['module' => $validated['module'], 'document_type' => $validated['document_type']],
            [
                'prefix' => strtoupper($validated['prefix']),
                'padding_length' => (int) $validated['padding_length'],
                'running_number' => (int) $validated['running_number'],
            ],
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Setting nomor dokumen berhasil disimpan.']);
    }

    public function updateDocumentSequence(Request $request, DocumentSequence $documentSequence): RedirectResponse
    {
        $validated = $request->validate([
            'prefix' => 'required|string|max:20',
            'padding_length' => 'required|integer|min:3|max:10',
            'running_number' => 'required|integer|min:0',
        ]);

        $documentSequence->update([
            'prefix' => strtoupper($validated['prefix']),
            'padding_length' => (int) $validated['padding_length'],
            'running_number' => (int) $validated['running_number'],
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Sequence nomor dokumen berhasil diperbarui.']);
    }

    public function paymentMethods(): Response
    {
        return Inertia::render('ERP/Admin/PaymentMethods', [
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
        ]);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|alpha_dash|unique:payment_methods,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['code'] = strtolower($validated['code']);
        PaymentMethod::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Metode pembayaran berhasil ditambahkan.']);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|alpha_dash|unique:payment_methods,code,'.$paymentMethod->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['code'] = strtolower($validated['code']);
        $paymentMethod->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Metode pembayaran berhasil diperbarui.']);
    }
}

