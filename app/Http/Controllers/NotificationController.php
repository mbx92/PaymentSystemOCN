<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationRead;
use App\Support\AppNotificationCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, AppNotificationCenter $notificationCenter): Response
    {
        return Inertia::render('Notifications/Index', [
            'notificationCenter' => $notificationCenter->buildFor($request->user()),
        ]);
    }

    public function poll(Request $request, AppNotificationCenter $notificationCenter): JsonResponse
    {
        return response()->json([
            'notificationCenter' => $notificationCenter->buildFor($request->user()),
        ]);
    }

    public function markRead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notification_id' => 'required|string|max:160',
        ]);

        UserNotificationRead::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'notification_id' => $data['notification_id'],
            ],
            [
                'read_at' => now(),
            ],
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    public function markUnread(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notification_id' => 'required|string|max:160',
        ]);

        UserNotificationRead::query()
            ->where('user_id', $request->user()->id)
            ->where('notification_id', $data['notification_id'])
            ->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Notifikasi ditandai belum dibaca.']);
    }

    public function markAllRead(Request $request, AppNotificationCenter $notificationCenter): RedirectResponse
    {
        $items = $notificationCenter->buildFor($request->user())['items'] ?? [];

        foreach ($items as $item) {
            UserNotificationRead::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'notification_id' => $item['notification_id'],
                ],
                [
                    'read_at' => now(),
                ],
            );
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }
}
