<?php
/**
 * footer.php — Layout principal (pie de página)
 * 
 * Cierra la estructura HTML y carga los scripts JS.
 */
?>
        </div><!-- /.content-wrapper o /.auth-layout -->

<?php if (Auth::isLoggedIn()): ?>
    </main><!-- /.main-content -->
<?php else: ?>
    </div><!-- /.auth-layout -->
<?php endif; ?>

    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
<?php if (isset($loadRfidJs) && $loadRfidJs): ?>
    <script src="<?= BASE_URL ?>assets/js/rfid.js"></script>
<?php endif; ?>
</body>
</html>
