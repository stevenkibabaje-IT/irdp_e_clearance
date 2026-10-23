<footer class="site-footer" aria-label="Site footer">
    <div class="site-footer-inner">
        <div class="site-footer-grid">
            <div class="site-footer-contact">
                <div class="site-footer-brand">
                    <img src="<?= e(url('assets/img/irdp-logo-web.png')) ?>" width="52" height="52" alt="IRDP">
                    <div><strong>IRDP</strong><span>Student Clearance System</span></div>
                </div>
                <h2>Contact</h2>
                <address>Institute of Rural Development Planning,<br>Donbosco Road, 41213 Mbwanga,<br>P.O. Box 138, Dodoma, Tanzania.<br>
                    Phone: <a href="tel:+255262963037">(+255) 26 2963037</a><br>
                    Email: <a href="mailto:rector@irdp.ac.tz">rector@irdp.ac.tz</a>
                </address>
            </div>
            <nav class="site-footer-links" aria-label="IRDP services">
                <h2>Staff Links</h2>
                <a href="https://www.irdp.ac.tz/" target="_blank" rel="noopener noreferrer">IRDP website <?= icon('external-link') ?></a>
                <a href="<?= e(url('auth/login.php')) ?>">Clearance Staff Login <?= icon('chevron-right') ?></a>
                <a href="https://srs.irdp.ac.tz/" target="_blank" rel="noopener noreferrer">Student Record System <?= icon('external-link') ?></a>
            </nav>
            <nav class="site-footer-links" aria-label="Student links">
                <h2>Student Links</h2>
                <a href="https://srs.irdp.ac.tz/" target="_blank" rel="noopener noreferrer">SRS <?= icon('external-link') ?></a>
                <a href="https://timetable.irdp.ac.tz/" target="_blank" rel="noopener noreferrer">Timetable <?= icon('external-link') ?></a>
                <a href="<?= e(url('auth/login.php')) ?>">Student Clearance <?= icon('chevron-right') ?></a>
            </nav>
            <div class="site-footer-support site-footer-links">
                <h2>Clearance support</h2>
                <div class="site-footer-phones">
                    <a href="tel:+255659913570"><?= icon('phone') ?> 0659 913 570</a>
                    <a href="tel:+255662632565"><?= icon('phone') ?> 0662 632 565</a>
                </div>
            </div>
        </div>
        <div class="site-footer-bottom"><span>&copy; <?= date('Y') ?> Institute of Rural Development Planning</span><span>&ldquo;Kupanga ni Kuchagua&rdquo;</span></div>
    </div>
</footer>
