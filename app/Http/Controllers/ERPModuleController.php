<?php

namespace App\Http\Controllers;

use App\Support\ModuleWorkspaceRegistry;
use Inertia\Inertia;
use Inertia\Response;

class ERPModuleController extends Controller
{
    public function accounting(): Response
    {
        return $this->renderRegistryModule('accounting');
    }

    public function payments(): Response
    {
        return Inertia::render('ERP/Accounting/Payments');
    }

    public function sales(): Response
    {
        return $this->renderRegistryModule('sales');
    }

    public function purchasing(): Response
    {
        return $this->renderRegistryModule('purchasing');
    }

    public function inventory(): Response
    {
        return $this->renderRegistryModule('inventory');
    }

    public function projects(): Response
    {
        return $this->renderRegistryModule('projects');
    }

    public function hr(): Response
    {
        return $this->renderRegistryModule('hr');
    }

    public function crm(): Response
    {
        return $this->renderRegistryModule('crm');
    }

    public function reporting(): Response
    {
        return $this->renderRegistryModule('reporting');
    }

    public function administration(): Response
    {
        return $this->renderRegistryModule('administration');
    }

    private function renderRegistryModule(string $moduleKey): Response
    {
        return Inertia::render('ERP/Modules/Index', [
            'moduleKey' => $moduleKey,
            'module' => ModuleWorkspaceRegistry::labelFor($moduleKey),
            'menus' => ModuleWorkspaceRegistry::menusFor($moduleKey),
        ]);
    }
}
