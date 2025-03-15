<?php
if (count(get_included_files()) == 1) {
    die('This file is not meant to be accessed directly.');
}
?>
        <footer class="bg-light text-center text-lg-start mt-5">
            <div class="row p-3">

                <div class="col text-start">
                    <button type="button" class="btn p-0 m-0 align-baseline border-0" x-ref="icuButton"
                        @click="fetchICUInfo()">&copy;</button>
                    <?php if (date('Y') != '2024') {
                        echo '2024-';
                    }
        echo date("Y"); ?>
                    Donatas Glodenis, visos teisės saugomos <br><span class="small" id='icuExtensionInUse'></span>

                </div>
                <div class="col text-end">


                    <a href="https://github.com/dgvirtual" class="text-decoration-none text-dark">
                        <i class="bi bi-github"></i> GitHub &nbsp;&nbsp;&nbsp;
                    </a>

                    <a href="https://dg.lapas.info" class="text-decoration-none text-dark">
                        <i class="bi bi-wordpress"></i> Blog
                    </a>

                </div>


            </div>
        </footer>
    </div>
    <script>
        <?php require_once(BASE_DIR . 'front/script.js'); ?>
    </script>
</body>

</html>