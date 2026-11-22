<?php

/**
 * Pesan validasi dalam Bahasa Indonesia (aturan bawaan CodeIgniter + aturan aplikasi App\Validation\AppRules).
 */
return [
    // Core Messages
    'noRuleSets'      => 'Tidak ada aturan validasi.',
    'ruleNotFound'    => '"{0}" bukan aturan yang valid.',
    'groupNotFound'   => '"{0}" bukan grup aturan validasi.',
    'groupNotArray'   => 'Grup aturan "{0}" harus berupa array.',
    'invalidTemplate' => '"{0}" bukan template validasi yang valid.',

    // Rule Messages
    'alpha'                 => '{field} hanya boleh berisi huruf.',
    'alpha_dash'            => '{field} hanya boleh berisi huruf, angka, garis bawah dan tanda hubung.',
    'alpha_numeric'         => '{field} hanya boleh berisi huruf dan angka.',
    'alpha_numeric_punct'   => '{field} hanya boleh berisi huruf, angka, spasi dan karakter ~ ! # $ % & * - _ + = | : .',
    'alpha_numeric_space'   => '{field} hanya boleh berisi huruf, angka dan spasi.',
    'alpha_space'           => '{field} hanya boleh berisi huruf dan spasi.',
    'decimal'               => '{field} harus berupa angka desimal.',
    'differs'               => '{field} harus berbeda dari {param}.',
    'equals'                => '{field} harus tepat: {param}.',
    'exact_length'          => '{field} harus tepat {param} karakter.',
    'field_exists'          => '{field} harus ada.',
    'greater_than'          => '{field} harus lebih besar dari {param}.',
    'greater_than_equal_to' => '{field} harus lebih besar atau sama dengan {param}.',
    'hex'                   => '{field} hanya boleh berisi karakter heksadesimal.',
    'in_list'               => '{field} harus salah satu dari: {param}.',
    'integer'               => '{field} harus berupa bilangan bulat.',
    'is_natural'            => '{field} hanya boleh berisi angka.',
    'is_natural_no_zero'    => '{field} wajib dipilih.',
    'is_not_unique'         => '{field} tidak ditemukan.',
    'is_unique'             => '{field} sudah dipakai.',
    'less_than'             => '{field} harus lebih kecil dari {param}.',
    'less_than_equal_to'    => '{field} harus lebih kecil atau sama dengan {param}.',
    'matches'               => '{field} tidak sama dengan {param}.',
    'max_length'            => '{field} maksimal {param} karakter.',
    'min_length'            => '{field} minimal {param} karakter.',
    'not_equals'            => '{field} tidak boleh: {param}.',
    'not_in_list'           => '{field} tidak boleh salah satu dari: {param}.',
    'numeric'               => '{field} hanya boleh berisi angka.',
    'regex_match'           => 'Format {field} tidak benar.',
    'required'              => '{field} wajib diisi.',
    'required_with'         => '{field} wajib diisi jika {param} diisi.',
    'required_without'      => '{field} wajib diisi jika {param} kosong.',
    'string'                => '{field} harus berupa teks.',
    'timezone'              => '{field} harus zona waktu yang valid.',
    'valid_base64'          => '{field} harus berupa base64 yang valid.',
    'valid_email'           => '{field} harus berisi alamat email yang valid.',
    'valid_emails'          => '{field} harus berisi alamat email yang valid.',
    'valid_ip'              => '{field} harus berisi IP yang valid.',
    'valid_url'             => '{field} harus berisi URL yang valid.',
    'valid_url_strict'      => '{field} harus berisi URL yang valid.',
    'valid_date'            => '{field} harus berisi tanggal yang valid.',
    'valid_json'            => '{field} harus berisi JSON yang valid.',

    // Credit Cards
    'valid_cc_number' => '{field} bukan nomor kartu kredit yang valid.',

    // Files
    'uploaded' => '{field} bukan file unggahan yang valid.',
    'max_size' => 'File {field} terlalu besar.',
    'is_image' => '{field} bukan gambar yang valid.',
    'mime_in'  => 'Jenis file {field} tidak diizinkan.',
    'ext_in'   => 'Ekstensi file {field} tidak diizinkan.',
    'max_dims' => '{field} bukan gambar, atau terlalu lebar/tinggi.',
    'min_dims' => '{field} bukan gambar, atau kurang lebar/tinggi.',

    // App rules (App\Validation\AppRules)
    'npwpChars'    => 'NPWP hanya boleh berisi angka, titik, tanda hubung dan spasi.',
    'npwpDigits'   => 'NPWP harus 15 atau 16 angka.',
    'phoneInvalid' => 'Masukkan nomor telepon yang valid (angka, +, spasi, tanda hubung).',
];
