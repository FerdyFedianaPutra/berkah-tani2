    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrapper -->

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
window.CSRF_TOKEN = '<?= csrf_token() ?>';

function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('sidebarOverlay');
  s.classList.toggle('open');
  o.classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('show');
}

// Image preview for file inputs
document.querySelectorAll('[data-preview]').forEach(input => {
  input.addEventListener('change', function() {
    const previewId = this.dataset.preview;
    const preview   = document.getElementById(previewId);
    if (!preview || !this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(this.files[0]);
    this.closest('.img-upload-wrap')?.classList.add('has-file');
  });
});

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Apakah Anda yakin?')) e.preventDefault();
  });
});
</script>
</body>
</html>
