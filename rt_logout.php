<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Hapus hanya session RT/RW, bukan session admin
unset($_SESSION['rt_akun_id'], $_SESSION['rt_username'], $_SESSION['rt_jabatan'],
      $_SESSION['rt_nomor_rt'], $_SESSION['rt_nomor_rw'], $_SESSION['rt_nama']);
header('Location: rt_login.php');
exit;
