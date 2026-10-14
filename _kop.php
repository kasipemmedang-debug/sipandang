<?php
/**
 * _kop.php — Kop Surat Resmi (legacy include)
 * Sekarang memakai pengaturan Identitas Kelurahan (inc_kop_kelurahan.php).
 */
require_once __DIR__ . '/inc_kop_kelurahan.php';

function kop_surat_css(): string
{
    return sipandang_kop_kelurahan_css();
}

function kop_surat($koneksi): string
{
    return sipandang_kop_kelurahan_render_html(sipandang_kop_kelurahan_data($koneksi), true);
}
