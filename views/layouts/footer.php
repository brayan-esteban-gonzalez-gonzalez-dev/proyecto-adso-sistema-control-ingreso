<?php
/**
 * footer.php — Layout principal (pie de página)
 * 
 * Cierra la estructura HTML y carga los scripts JS.
 */
?>
<?php if (Auth::isLoggedIn()): ?>
        </div><!-- /.content-wrapper -->
    </main><!-- /.main-content -->
<?php else: ?>
    </div><!-- /.auth-layout -->
<?php endif; ?>


    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
<?php if (isset($loadRfidJs) && $loadRfidJs): ?>
    <script src="<?= BASE_URL ?>assets/js/rfid.js?v=<?= file_exists(ROOT_PATH . '/assets/js/rfid.js') ? filemtime(ROOT_PATH . '/assets/js/rfid.js') : '1.0' ?>"></script>
<?php endif; ?>
</body>
</html>
