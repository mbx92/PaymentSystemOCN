<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpSetting extends Model
{
    public const MODULE_MENU_LAYOUT_GRID = 'grid';

    public const MODULE_MENU_LAYOUT_LIST = 'list';

    public const SCREEN_MODE_AUTO = 'auto';

    public const SCREEN_MODE_DESKTOP = 'desktop';

    public const SCREEN_MODE_TABLET = 'tablet';

    public const SCREEN_MODE_IPAD_9_2021 = 'ipad_9_2021';

    public const SCREEN_MODE_MOBILE = 'mobile';

    public const SCREEN_DENSITY_COMFORTABLE = 'comfortable';

    public const SCREEN_DENSITY_COMPACT = 'compact';

    protected $fillable = [
        'app_name',
        'app_tagline',
        'app_logo_path',
        'module_menu_layout',
        'screen_mode',
        'screen_density',
        'object_storage_enabled',
        'object_storage_access_key',
        'object_storage_secret_key',
        'object_storage_bucket',
        'object_storage_region',
        'object_storage_endpoint',
        'object_storage_use_path_style',
        'object_storage_prefix',
        'object_storage_archive_pdf',
        'object_storage_archive_excel',
        'object_storage_archive_database',
        'thermal_printer_enabled',
        'thermal_printer_host',
        'thermal_printer_port',
        'thermal_paper_width',
        'thermal_pos_header_template',
        'thermal_pos_item_line_template',
        'thermal_pos_footer_template',
        'thermal_pos_margin_left_mm',
        'thermal_pos_header_align',
        'thermal_pos_item_align',
        'thermal_pos_footer_align',
        'thermal_pos_section_gap',
        'thermal_pos_header_emphasis',
        'label_smb_enabled',
        'label_smb_unc',
        'label_smb_protocol',
        'label_smb_profile_id',
        'label_lan_enabled',
        'label_lan_host',
        'label_lan_port',
        'label_lan_profile_id',
        'maintenance_global_enabled',
        'maintenance_global_message',
        'maintenance_modules',
    ];

    protected function casts(): array
    {
        return [
            'object_storage_enabled' => 'boolean',
            'object_storage_secret_key' => 'encrypted',
            'object_storage_use_path_style' => 'boolean',
            'object_storage_archive_pdf' => 'boolean',
            'object_storage_archive_excel' => 'boolean',
            'object_storage_archive_database' => 'boolean',
            'thermal_printer_enabled' => 'boolean',
            'thermal_printer_port' => 'integer',
            'thermal_pos_margin_left_mm' => 'decimal:2',
            'thermal_pos_section_gap' => 'integer',
            'thermal_pos_header_emphasis' => 'boolean',
            'label_smb_enabled' => 'boolean',
            'label_smb_profile_id' => 'integer',
            'label_lan_enabled' => 'boolean',
            'label_lan_port' => 'integer',
            'label_lan_profile_id' => 'integer',
            'maintenance_global_enabled' => 'boolean',
            'maintenance_modules' => 'array',
        ];
    }

    public function labelProfile(): BelongsTo
    {
        return $this->belongsTo(LabelProfile::class, 'label_smb_profile_id');
    }

    public function labelLanProfile(): BelongsTo
    {
        return $this->belongsTo(LabelProfile::class, 'label_lan_profile_id');
    }

    /**
     * @return list<string>
     */
    public static function moduleMenuLayoutOptions(): array
    {
        return [
            self::MODULE_MENU_LAYOUT_GRID,
            self::MODULE_MENU_LAYOUT_LIST,
        ];
    }

    public function resolvedModuleMenuLayout(): string
    {
        return in_array($this->module_menu_layout, self::moduleMenuLayoutOptions(), true)
            ? $this->module_menu_layout
            : self::MODULE_MENU_LAYOUT_GRID;
    }

    /**
     * @return list<string>
     */
    public static function screenModeOptions(): array
    {
        return [
            self::SCREEN_MODE_AUTO,
            self::SCREEN_MODE_DESKTOP,
            self::SCREEN_MODE_TABLET,
            self::SCREEN_MODE_IPAD_9_2021,
            self::SCREEN_MODE_MOBILE,
        ];
    }

    public function resolvedScreenMode(): string
    {
        return in_array($this->screen_mode, self::screenModeOptions(), true)
            ? $this->screen_mode
            : self::SCREEN_MODE_AUTO;
    }

    /**
     * @return list<string>
     */
    public static function screenDensityOptions(): array
    {
        return [
            self::SCREEN_DENSITY_COMFORTABLE,
            self::SCREEN_DENSITY_COMPACT,
        ];
    }

    public function resolvedScreenDensity(): string
    {
        return in_array($this->screen_density, self::screenDensityOptions(), true)
            ? $this->screen_density
            : self::SCREEN_DENSITY_COMFORTABLE;
    }

    public function resolvedObjectStorageSecretKey(): ?string
    {
        $secret = $this->object_storage_secret_key;

        return is_string($secret) && trim($secret) !== '' ? $secret : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function objectStorageSettingsPayload(): array
    {
        return [
            'object_storage_enabled' => (bool) $this->object_storage_enabled,
            'object_storage_access_key' => $this->object_storage_access_key,
            'object_storage_secret_key_configured' => filled($this->object_storage_secret_key),
            'object_storage_bucket' => $this->object_storage_bucket,
            'object_storage_region' => $this->object_storage_region ?: 'us-east-1',
            'object_storage_endpoint' => $this->object_storage_endpoint,
            'object_storage_use_path_style' => (bool) $this->object_storage_use_path_style,
            'object_storage_prefix' => $this->object_storage_prefix ?: 'erp-archive',
            'object_storage_archive_pdf' => (bool) $this->object_storage_archive_pdf,
            'object_storage_archive_excel' => (bool) $this->object_storage_archive_excel,
            'object_storage_archive_database' => (bool) $this->object_storage_archive_database,
        ];
    }

    /**
     * Profil ukuran label untuk cetak TSPL lewat LAN: khusus LAN jika diisi, jika tidak memakai profil SMB.
     */
    public function resolveLabelProfileForLanPrinting(): ?LabelProfile
    {
        if ($this->label_lan_profile_id) {
            return $this->labelLanProfile ?? LabelProfile::query()->find($this->label_lan_profile_id);
        }

        return $this->labelProfile;
    }

    /**
     * @return array<string, array{enabled: bool, message: string|null}>
     */
    public static function defaultMaintenanceModules(): array
    {
        $keys = ['accounting', 'sales', 'purchasing', 'inventory', 'projects', 'hr', 'reporting', 'administration'];

        return array_fill_keys($keys, ['enabled' => false, 'message' => null]);
    }

    /**
     * @return array<string, array{enabled: bool, message: string|null}>
     */
    public function mergedMaintenanceModules(): array
    {
        $base = self::defaultMaintenanceModules();
        $saved = $this->maintenance_modules ?? [];
        if (! is_array($saved)) {
            return $base;
        }
        foreach ($base as $key => $defaults) {
            if (! isset($saved[$key]) || ! is_array($saved[$key])) {
                continue;
            }
            $base[$key]['enabled'] = self::coerceMaintenanceEnabled($saved[$key]['enabled'] ?? false);
            $msg = $saved[$key]['message'] ?? null;
            $base[$key]['message'] = is_string($msg) && trim($msg) !== '' ? trim($msg) : null;
        }

        return $base;
    }

    public static function coerceMaintenanceEnabled(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (int) $v !== 0;
        }
        if (is_string($v)) {
            $t = strtolower(trim($v));
            if (in_array($t, ['', '0', 'false', 'no', 'off'], true)) {
                return false;
            }

            return in_array($t, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
