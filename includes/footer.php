<?php if (current_user()): ?>
            </section>

            <footer class="footer">
                <span>IRDP Student Clearance System</span>
                <span>Modern · Secure · Transparent</span>
                <span>© <?= date('Y') ?> IRDP</span>
            </footer>
        </main>
    </div>
<?php else: ?>
    </main>
<?php endif; ?>

<script>
    function confirmAction(message) {
        return window.confirm(message || 'Are you sure?');
    }
</script>
<script src="<?= e(url('assets/js/validation.js?v=' . filemtime(__DIR__ . '/../assets/js/validation.js'))) ?>" defer></script>
</body>
</html>
