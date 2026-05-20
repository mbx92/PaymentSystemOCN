<?php

namespace App\Http\Controllers;

use App\Support\ModuleWorkspaceRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UiPreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_menu_order' => ['nullable', 'array'],
            'module_menu_order.module' => ['required_with:module_menu_order', 'string', Rule::in(ModuleWorkspaceRegistry::moduleKeys())],
            'module_menu_order.order' => ['required_with:module_menu_order', 'array'],
            'module_menu_order.order.*' => ['string'],
        ]);

        $user = $request->user();
        $preferences = $user->resolvedUiPreferences();

        if (isset($validated['module_menu_order'])) {
            $moduleKey = $validated['module_menu_order']['module'];
            $preferences['module_menu_orders'][$moduleKey] = ModuleWorkspaceRegistry::normalizeMenuOrder(
                $moduleKey,
                $validated['module_menu_order']['order'],
            );
        }

        $user->update(['ui_preferences' => $preferences]);

        return response()->json([
            'message' => 'Preferensi UI berhasil diperbarui.',
            'ui_preferences' => $user->fresh()->resolvedUiPreferences(),
        ]);
    }
}
