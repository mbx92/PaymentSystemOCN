<?php

namespace App\Http\Controllers;

use App\Models\CashIn;
use App\Models\ProjectPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectPaymentController extends Controller
{
    public function markPaid(Request $request, ProjectPayment $payment)
    {
        $validated = $request->validate([
            'paid_at' => 'required|date',
            'note'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($payment, $validated) {
            $payment->update($validated);

            $noteParts = [
                'Pembayaran termin '.$payment->term_number.' ('.$payment->percentage.'%)',
            ];
            if (! empty($validated['note'])) {
                $noteParts[] = $validated['note'];
            }
            $cashNote = implode(' — ', $noteParts);

            CashIn::updateOrCreate(
                ['project_payment_id' => $payment->id],
                [
                    'project_id'  => $payment->project_id,
                    'category'    => 'pendapatan_jasa',
                    'amount'      => $payment->amount,
                    'date'        => $validated['paid_at'],
                    'note'        => $cashNote,
                    'created_by'  => Auth::id(),
                ]
            );
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Termin berhasil ditandai lunas dan dicatat di kas masuk.']);
    }

    public function markUnpaid(ProjectPayment $payment)
    {
        DB::transaction(function () use ($payment) {
            CashIn::where('project_payment_id', $payment->id)->delete();
            $payment->update(['paid_at' => null, 'note' => null]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Status termin diubah ke belum lunas; entri kas masuk terkait dihapus.']);
    }
}
