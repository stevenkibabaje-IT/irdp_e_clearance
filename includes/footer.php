<?php if (current_user()): ?>
            </section>

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
<?php require __DIR__ . '/help.php'; ?>
<?php if (current_user()): require __DIR__ . '/session_warning.php'; endif; ?>
<?php if (current_user()): ?>
<script src="<?= e(url('assets/js/navigation.js?v=' . filemtime(__DIR__ . '/../assets/js/navigation.js'))) ?>" defer></script>
<?php endif; ?>
</body>
</html>
