<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Katalog harga supplier (Google Sheets → database lokal)
    |--------------------------------------------------------------------------
    |
    | Spreadsheet harus bisa diakses "Anyone with the link can view".
    | Data di-sync ke tabel supplier_catalog_items via artisan supplier-catalog:sync
    | (dijadwalkan harian). UI/API membaca dari database, bukan langsung ke sheet.
    |
    | sheet_name harus persis sama dengan nama tab di Google Sheets.
    |
    */

    'spreadsheet_id' => env('SUPPLIER_CATALOG_SPREADSHEET_ID', '18EC7x3oI6nO0xfhrMud9C6DS5w8jNF1N75FSAOnWUWc'),

    'supplier_name' => env('SUPPLIER_CATALOG_SUPPLIER_NAME', 'PL TUNAS JAYA ELEKTRONIK'),

    'sync_time' => env('SUPPLIER_CATALOG_SYNC_TIME', '02:00'),

    'sheets' => [
        ['key' => 'hikvision', 'label' => 'HIKVISION', 'sheet_name' => 'HIKVISION.'],
        ['key' => 'hilook', 'label' => 'HILOOK', 'sheet_name' => 'HILOOK.'],
        ['key' => 'ezviz', 'label' => 'EZVIZ', 'sheet_name' => 'EZVIZ.'],
        ['key' => 'dahua', 'label' => 'DAHUA', 'sheet_name' => 'DAHUA.'],
        ['key' => 'tiandy', 'label' => 'TIANDY', 'sheet_name' => 'TIANDY.'],
        ['key' => 'ruijie', 'label' => 'RUIJIE', 'sheet_name' => 'RUIJIE.'],
        ['key' => 'imou', 'label' => 'IMOU', 'sheet_name' => 'IMOU.'],
        ['key' => 'microsd', 'label' => 'MICROSD', 'sheet_name' => 'MICROSD.'],
        ['key' => 'hdd', 'label' => 'HDD', 'sheet_name' => 'HDD.'],
        ['key' => 'ups', 'label' => 'UPS', 'sheet_name' => 'UPS'],
        ['key' => 'mikrotik', 'label' => 'MIKROTIK', 'sheet_name' => 'MIKROTIK.'],
        ['key' => 'vention', 'label' => 'VENTION', 'sheet_name' => 'VENTION.'],
        ['key' => 'tplink', 'label' => 'TPLINK', 'sheet_name' => 'TPLINK.'],
        ['key' => 'foredge', 'label' => 'FOREDGE', 'sheet_name' => 'FOREDGE.'],
        ['key' => 'mercusys-huawei', 'label' => 'MERCUSYS&HUAWEI', 'sheet_name' => 'MERCUSYS&HUAWEI.'],
        ['key' => 'robot', 'label' => 'ROBOT', 'sheet_name' => 'ROBOT.'],
        ['key' => 'takasi', 'label' => 'TAKASI', 'sheet_name' => 'TAKASI.'],
        ['key' => 'konektor', 'label' => 'KONEKTOR', 'sheet_name' => 'KONEKTOR'],
        ['key' => 'toa', 'label' => 'TOA', 'sheet_name' => 'TOA.'],
        ['key' => 'ht', 'label' => 'HT', 'sheet_name' => 'HT.'],
        ['key' => 'adaptor', 'label' => 'ADAPTOR', 'sheet_name' => 'ADAPTOR.'],
        ['key' => 'hdmi-connlexwebsong', 'label' => 'HDMI CONNLEXWEBSONG', 'sheet_name' => 'HDMI CONNLEXWEBSONG.'],
        ['key' => 'rak-server', 'label' => 'RAK SERVER', 'sheet_name' => 'RAK SERVER'],
        ['key' => 'kabel-lanrg', 'label' => 'KABEL LANRG', 'sheet_name' => 'KABEL LANRG'],
        ['key' => 'patchcord', 'label' => 'PATCHCORD', 'sheet_name' => 'PATCHCORD'],
        ['key' => 'precon-fiber', 'label' => 'PRECON FIBER', 'sheet_name' => 'PRECON FIBER'],
    ],

];
