  <!-- Bootstrap bundle (for any existing Bootstrap components) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Existing app script -->
  <script src="<?= $base ?>/assets/js/script.js"></script>
  <script>
    (function removeLegacyWorkflowPanel() {
      const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .module-title, .card-title'));
      headings.forEach((el) => {
        const text = (el.textContent || '').trim().toLowerCase();
        if (text !== 'logistics workflow') return;
        const block = el.closest('.table-card, .card, .module-section, .content-card, .panel');
        if (block) block.remove();
      });
    })();
  </script>
</body>
</html>
