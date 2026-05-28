<?php

namespace App\Models;

use App\ERP\Core\Models\Company;
use App\Support\ModuleWorkspaceRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * Spatie role names shown in Add/Edit user and Roles & permission (guard web).
     *
     * @var list<string>
     */
    public const ASSIGNABLE_ROLE_NAMES = ['admin', 'manajer', 'anggota', 'finance'];

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'ui_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ui_preferences' => 'array',
        ];
    }

    public static function defaultUiPreferences(): array
    {
        return [
            'module_menu_orders' => [],
        ];
    }

    public function resolvedUiPreferences(): array
    {
        $stored = $this->ui_preferences;
        $defaults = self::defaultUiPreferences();
        $preferences = is_array($stored) ? $stored : [];

        $moduleOrders = [];
        foreach (ModuleWorkspaceRegistry::moduleKeys() as $moduleKey) {
            if (! isset($preferences['module_menu_orders'][$moduleKey]) || ! is_array($preferences['module_menu_orders'][$moduleKey])) {
                continue;
            }

            $moduleOrders[$moduleKey] = ModuleWorkspaceRegistry::normalizeMenuOrder(
                $moduleKey,
                $preferences['module_menu_orders'][$moduleKey],
            );
        }

        return [
            'module_menu_orders' => $moduleOrders,
        ];
    }

    public function teamDistributions()
    {
        return $this->hasMany(TeamDistribution::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashInsCreated()
    {
        return $this->hasMany(CashIn::class, 'created_by');
    }

    public function cashOutsCreated()
    {
        return $this->hasMany(CashOut::class, 'created_by');
    }
}
