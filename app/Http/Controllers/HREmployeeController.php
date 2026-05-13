<?php

namespace App\Http\Controllers;

use App\ERP\HR\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HREmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = Employee::query()->orderBy('name')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn (Employee $e) => [
                'id' => $e->id,
                'employee_no' => $e->employee_no,
                'name' => $e->name,
                'email' => $e->email,
                'phone' => $e->phone,
                'position' => $e->position,
                'base_salary' => (float) $e->base_salary,
                'is_active' => $e->is_active,
            ]);

        return Inertia::render('ERP/HR/Employees', [
            'employees' => $employees,
            'filters' => $this->filtersWithPerPage($request, []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_no' => 'required|string|max:32|unique:employees,employee_no',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:120',
            'base_salary' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['base_salary'] = $validated['base_salary'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        Employee::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Karyawan berhasil ditambahkan.']);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'employee_no' => 'required|string|max:32|unique:employees,employee_no,'.$employee->id,
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:120',
            'base_salary' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['base_salary'] = $validated['base_salary'] ?? 0;
        if (array_key_exists('is_active', $validated)) {
            $validated['is_active'] = (bool) $validated['is_active'];
        }

        $employee->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Data karyawan diperbarui.']);
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->payrolls()->exists()) {
            return back()->with('flash', ['type' => 'warning', 'message' => 'Tidak dapat menghapus karyawan yang sudah punya data payroll.']);
        }

        $employee->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Karyawan dihapus.']);
    }
}
