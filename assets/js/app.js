/* ==========================================================================
   assets/js/app.js — Interaksi dasar Kerjain! (vanilla JS, tanpa library)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Toggle menu navbar di layar HP
  var toggle = document.querySelector('.nav-toggle');
  var links = document.querySelector('.nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      links.classList.toggle('is-open');
    });
  }

  // 2. Konfirmasi hapus: tambahkan data-confirm ke <form> atau <a>
  //    Contoh: <form data-confirm="Yakin hapus project ini?">
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });
  document.querySelectorAll('a[data-confirm]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (!window.confirm(link.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  // 3. Pesan flash otomatis hilang setelah 5 detik
  document.querySelectorAll('.alert').forEach(function (alert) {
    window.setTimeout(function () {
      alert.style.transition = 'opacity 0.4s ease';
      alert.style.opacity = '0';
      window.setTimeout(function () {
        alert.remove();
      }, 400);
    }, 5000);
  });

  // 4. Validasi form sederhana: input wajib bertanda required
  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var kosong = false;
      form.querySelectorAll('[required]').forEach(function (input) {
        if (!input.value.trim()) {
          kosong = true;
          input.classList.add('input-error');
        } else {
          input.classList.remove('input-error');
        }
      });
      if (kosong) {
        event.preventDefault();
        window.alert('Mohon isi semua kolom yang wajib diisi.');
      }
    });
  });

  // 5. Filter kategori di daftar project: langsung diterapkan saat diganti
  //    (progressive enhancement — tanpa JS tetap jalan lewat tombol "Terapkan").
  document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.form) {
        select.form.submit();
      }
    });
  });
});

// Helper: format angka jadi Rupiah (dipakai form lamaran nanti)
function formatRupiah(angka) {
  var bilangan = parseInt(String(angka).replace(/[^0-9]/g, ''), 10);
  if (isNaN(bilangan)) bilangan = 0;
  return 'Rp ' + bilangan.toLocaleString('id-ID');
}
