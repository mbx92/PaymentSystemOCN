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
    | gid dipakai untuk fetch CSV yang lebih andal (nama tab dengan / atau &).
    |
    */

    'spreadsheet_id' => env('SUPPLIER_CATALOG_SPREADSHEET_ID', '1aaKkgM9NVRsdKTFhqE46lvyLZ4rsLtxcq3_ninX6ncg'),

    'supplier_name' => env('SUPPLIER_CATALOG_SUPPLIER_NAME', 'PL TUNAS JAYA ELEKTRONIK'),

    'sync_time' => env('SUPPLIER_CATALOG_SYNC_TIME', '02:00'),

    'sheets' => [
        ['key' => 'hikvision-analog-dvr', 'label' => 'HIKVISION ANALOG & DVR', 'sheet_name' => 'HIKVISION ANALOG DAN DVR', 'gid' => '1662963114'],
        ['key' => 'hikvision-ip-part-1', 'label' => 'HIKVISION IP CAMERA PART 1', 'sheet_name' => 'HIKVISION IP CAMERA PART 1', 'gid' => '743532671'],
        ['key' => 'hikvision-ip-ptz', 'label' => 'HIKVISION IP CAMERA & PTZ', 'sheet_name' => 'HIKVISION IP CAMERA DAN IP CAMERA PTZ', 'gid' => '2009540950'],
        ['key' => 'hikvision-nvr', 'label' => 'HIKVISION NVR', 'sheet_name' => 'HIKVISION NVR', 'gid' => '612304622'],
        ['key' => 'hikvision-access-poe', 'label' => 'HIKVISION ACCESS / POE / PSU', 'sheet_name' => 'HIKVISION ACCES CONTROL, POE SWITCH, PSU', 'gid' => '20866649'],
        ['key' => 'hilook', 'label' => 'HILOOK', 'sheet_name' => 'HILOOK', 'gid' => '1048074398'],
        ['key' => 'ezviz', 'label' => 'EZVIZ', 'sheet_name' => 'EZVIZ', 'gid' => '80707125'],
        ['key' => 'dahua-analog-dvr', 'label' => 'DAHUA ANALOG & DVR', 'sheet_name' => 'DAHUA ANALOG CAMERA DAN DVR', 'gid' => '2140306103'],
        ['key' => 'dahua-ip-nvr', 'label' => 'DAHUA IP CAMERA & NVR', 'sheet_name' => 'DAHUA IP CAMERA DAN NVR', 'gid' => '532806829'],
        ['key' => 'dahua-poe-psu', 'label' => 'DAHUA POE / PSU', 'sheet_name' => 'DAHUA POE SWITCH, PSU, DLL', 'gid' => '1919306222'],
        ['key' => 'tiandy', 'label' => 'TIANDY', 'sheet_name' => 'TIANDY', 'gid' => '2124632806'],
        ['key' => 'ruijie', 'label' => 'RUIJIE', 'sheet_name' => 'RUIJIE', 'gid' => '489488932'],
        ['key' => 'imou', 'label' => 'IMOU', 'sheet_name' => 'IMOU', 'gid' => '697625412'],
        ['key' => 'microsd', 'label' => 'MICROSD', 'sheet_name' => 'MICROSD', 'gid' => '974456429'],
        ['key' => 'hdd', 'label' => 'HDD', 'sheet_name' => 'HDD', 'gid' => '34342766'],
        ['key' => 'ups', 'label' => 'UPS', 'sheet_name' => 'UPS', 'gid' => '1499124706'],
        ['key' => 'mikrotik', 'label' => 'MIKROTIK', 'sheet_name' => 'MIKROTIK', 'gid' => '2072391326'],
        ['key' => 'vention', 'label' => 'VENTION', 'sheet_name' => 'VENTION', 'gid' => '1729234644'],
        ['key' => 'tplink', 'label' => 'TPLINK', 'sheet_name' => 'TPLINK', 'gid' => '1663184314'],
        ['key' => 'foredge', 'label' => 'FOREDGE', 'sheet_name' => 'FOREDGE', 'gid' => '1060392296'],
        ['key' => 'mercusys-huawei', 'label' => 'MERCUSYS & HUAWEI', 'sheet_name' => 'MERCUSYS&HUAWEI', 'gid' => '1157509812'],
        ['key' => 'robot', 'label' => 'ROBOT', 'sheet_name' => 'ROBOT', 'gid' => '1662273075'],
        ['key' => 'takasi', 'label' => 'TAKASI', 'sheet_name' => 'TAKASI', 'gid' => '935348596'],
        ['key' => 'konektor', 'label' => 'KONEKTOR', 'sheet_name' => 'KONEKTOR', 'gid' => '1240664764'],
        ['key' => 'toa', 'label' => 'TOA', 'sheet_name' => 'TOA', 'gid' => '270211487'],
        ['key' => 'ht', 'label' => 'HT', 'sheet_name' => 'HT', 'gid' => '2124552687'],
        ['key' => 'adaptor', 'label' => 'ADAPTOR', 'sheet_name' => 'ADAPTOR', 'gid' => '1444735387'],
        ['key' => 'hdmi-connlex-websong', 'label' => 'HDMI CONNLEX / WEBSONG', 'sheet_name' => 'HDMI CONNLEX/WEBSONG', 'gid' => '734294976'],
        ['key' => 'rak-server', 'label' => 'RAK SERVER', 'sheet_name' => 'RAK SERVER', 'gid' => '1035273974'],
        ['key' => 'kabel-lan-rg', 'label' => 'KABEL LAN / RG', 'sheet_name' => 'KABEL LAN/RG', 'gid' => '1700583241'],
        ['key' => 'patchcord', 'label' => 'PATCHCORD', 'sheet_name' => 'PATCHCORD', 'gid' => '1933301537'],
        ['key' => 'precon-fiber', 'label' => 'PRECON FIBER', 'sheet_name' => 'PRECON FIBER', 'gid' => '235156957'],
    ],

];
