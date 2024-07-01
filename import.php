<?php
require_once('config.php');
if (file_exists(DBFILE)) {
    header("Location:" . BASE_URL);
    die();
}
?>
<!DOCTYPE html>
<html lang="lt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sukurti duomenų bazę ir importuoti duomenis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.10.2/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container mt-5" x-data="progressApp">
        <h1 class="text-center mb-4">Sukurti duomenų bazę ir importuoti duomenis</h1>
        <div class="text-center">
            <div x-cloak x-show="!isProcessing && !isComplete">
                <p>Duomenų bazės dar nėra. Norint pradėti naudoti svetainę reikia sukurti duomenų bazę ir importuoti į ją atvirus duomenis iš VĮ Registrų centro. <br>Tai galite padaryti paspausdami mygtuką „Pradėti“. Procesas neturėtų užtrukti ilgiau nei minutę.</p>
                <button @click="startProcess" class="btn btn-primary btn-lg">Pradėti</button>
            </div>
            <div x-cloak x-show="isProcessing || isComplete" class="progress mb-3" style="height: 25px;">
                <div class="progress-bar progress-bar-striped" role="progressbar" :style="`width: ${progress}%`" aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                    <span x-text="`${progress}%`"></span>
                </div>
            </div>
            <p x-cloak x-show="isProcessing" x-text="currentJob"></p>
            <div x-cloak x-show="isComplete">

                <h3 class="my-3">Duomenų bazė parengta!</h3>
                <a x-cloak x-show="isComplete" href="<?= BASE_URL ?>" class="btn btn-success btn-lg">Eiti į paieškos puslapį</a>
            </div>
        </div>
    </div>

    <script>
        function progressApp() {
            return {
                isProcessing: false,
                isComplete: false,
                progress: 0,
                next: 3,
                currentJob: '',
                lastProgress: 0,
                timer: null,

                async startProcess() {
                    this.isProcessing = true;
                    this.isComplete = false;
                    this.progress = 0;
                    this.next = 3;
                    this.currentJob = '';

                    // Start the import process
                    await fetch('<?= BASE_URL ?>import-api.php?action=start');

                    // Start polling for progress
                    this.pollProgress();
                },

                async pollProgress() {
                    try {
                        const response = await fetch('<?= BASE_URL ?>import-api.php?action=progress');
                        const data = await response.json();

                        if (data.progress === this.lastProgress) {
                            if (this.progress < this.next) {
                                this.progress += 1;
                            }
                        } else {
                            this.progress = data.progress;
                            this.lastProgress = data.progress;
                            this.next = data.next;
                        }

                        this.currentJob = data.current;

                        if (this.progress < 100) {
                            this.timer = setTimeout(() => this.pollProgress(), 1000);
                            // Increment progress every 1/3 second
                            if (this.incrementTimer) {
                                clearInterval(this.incrementTimer);
                            }
                            this.incrementTimer = setInterval(() => {
                                if (this.progress < this.next) {
                                    this.progress += 1;
                                }
                            }, 333); // approximately 1/3 second
                        } else {
                            this.isComplete = true;
                            setTimeout(() => {
                                this.isProcessing = false;
                            }, 1000);
                        }
                    } catch (error) {
                        console.error('Error fetching progress:', error);
                        this.isProcessing = false;
                    }
                }
            };
        }
    </script>

</body>

</html>