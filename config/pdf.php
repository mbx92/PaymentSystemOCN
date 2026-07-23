<?php

/**
 * Palet warna dokumen PDF (invoice, kwitansi, laporan, dll).
 * Sengaja dibuat gelap/netral (navy) agar dokumen cetak terasa formal
 * dan tenang, terlepas dari warna terang tema UI aplikasi.
 */
return [
    'theme' => [
        'primary' => '#1E3A5F',
        'primary_content' => '#ffffff',
        'base_100' => '#ffffff',
        'base_200' => '#f4f5f7',
        'base_300' => '#d9dde3',
        'base_content' => '#1f2937',
        'muted' => '#6b7280',
    ],

    'placeholders' => [
        'logo' => 'Logo',
        'title' => 'Nama Perusahaan',
        'tagline' => 'Tagline Perusahaan',
        'address' => 'Alamat perusahaan',
        'phone' => 'Nomor telepon',
    ],
];
