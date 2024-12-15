document.addEventListener('DOMContentLoaded', function () {
    const dateInputs = document.querySelectorAll('.date-input');

    dateInputs.forEach(input => {
        input.addEventListener('focus', function () {
            this.type = 'date';
        });

        input.addEventListener('blur', function () {
            if (!this.value) {
                this.type = 'text';
            }
        });
    });
});

function clearUrlParams() {
    const url = new URL(window.location);
    url.search = '';
    window.history.replaceState({}, document.title, url);
};

function fetchICUInfo() {
    fetch('<?= BASE_URL ?>api.php?extra=icu')
        .then(response => response.json())
        .then(data => {
            document.getElementById('icuExtensionInUse').innerText = data.data.icuStatus;
        })
        .catch(error => console.error('Error fetching ICU info:', error));
}

function legalPersonApp() {
    return {
        legalPersonIds: '',
        legalPersonTitle: '',
        legalPersonAddr: '',
        legalPersonForm: '',
        legalPersonStatus: '',
        regFrom: '',
        regTo: '',
        unregFrom: '',
        unregTo: '',
        showLiquidated: false,
        legalPersons: [],
        count: {},
        page: 1,
        limit: 20,
        isLoading: false,
        modalLoading: false,
        selectedPerson: null,
        clearUnregDates() {
            if (!this.showLiquidated) {
                this.unregFrom = this.unregTo = '';
            }
        },
        clearForm() {
            this.regFrom = this.regTo = this.unregFrom = this.unregTo = this.legalPersonIds = this.legalPersonAddr = this.legalPersonForm = this.legalPersonStatus = this.legalPersonTitle = '';
            this.showLiquidated = false;
        },
        resetPageAndFetch() {
            this.page = 1;
            this.fetchLegalPersons();
        },

        toastContent: '',
        toastMode: 'warning',
        toastVisible: false,

        get toastClass() {
            return `toast align-items-center text-bg-${this.toastMode} border-0`;
        },

        showToast(toastContent, toastMode = 'warning') {
            console.log('running showToast');
            this.toastContent = toastContent;
            this.toastMode = toastMode;
            this.toastVisible = true;

            const toastEl = this.$refs.toast;
            const toast = new bootstrap.Toast(toastEl);
            toast.show();

            toastEl.addEventListener('hidden.bs.toast', () => {
                this.toastVisible = false;
            });
        },

        hideToast() {
            const toastEl = this.$refs.toast;
            const toast = new bootstrap.Toast(toastEl);
            toast.hide();
        },

        fetchLegalPersons() {
            this.legalPersons = [];
            let ids = this.legalPersonIds
                .split(/[\s,]+/) // Split by spaces, commas, or both
                .filter(id => id.trim() !== ''); // Remove empty entries
            let title = this.legalPersonTitle.trim();
            let addr = this.legalPersonAddr.trim();
            let form = this.legalPersonForm.trim();
            let status = this.legalPersonStatus.trim();
            let show_l = this.showLiquidated;
            let page = this.page;
            let limit = this.limit;
            if (
                ids.length === 0 &&
                title.length < 3 &&
                addr.length < 3 &&
                form.length < 1 &&
                status.length < 1 &&
                this.regFrom < 10 &&
                this.regTo < 10 &&
                this.unregFrom < 10 &&
                this.unregTo < 10
            ) {
                this.showToast(
                    'Neįvesti užklausos parametrai, arba jų per mažai (reikalingi mažiausiai 3 simboliai bent viename teksto laukelyje, formos arba statuso pasirinkimas, arba pilna data viename iš datos laukelių)'
                );
                return;
            }
            this.isLoading = true;
            fetch(`<?= BASE_URL ?><?= API_FILE ?>?ids=${ids.join(',')}&title=${title}&addr=${addr}&status=${status}&form=${form}&reg_from=${this.regFrom}&reg_to=${this.regTo}&unreg_from=${this.unregFrom}&unreg_to=${this.unregTo}&show_l=${show_l}&limit=${limit}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    this.isLoading = false;
                    if (data.status_code === 200) {
                        this.legalPersons = data.data;
                        this.count = data.count;
                    } else {
                        this.legalPersons = [];
                        this.count = {};
                        this.showToast(data.status_message);
                    }
                })
                .catch(error => {
                    this.isLoading = false;
                    console.error('Error:', error);
                    this.showToast('Atliekant užklausą įvyko klaida, bandykite iš naujo', 'danger');
                });
        },
        fetchDetails(id) {
            this.modalLoading = true;
            this.selectedPerson = null;
            fetch(`<?= BASE_URL ?><?= API_FILE ?>?ids=${id}&show_l=${this.showLiquidated}&single=true`)
                .then(response => response.json())
                .then(data => {
                    this.modalLoading = false;
                    if (data.status_code === 200) {
                        this.selectedPerson = data.data[0];
                        this.queries = data.queries ?? null;
                    } else {
                        this.showToast(data.status_message);
                    }
                })
                .catch(error => {
                    this.modalLoading = false;
                    console.error('Error:', error);
                    this.showToast('Bandant atsisiųsti detales įvyko klaida, bandykite iš naujo', 'danger');
                });
        },
        changePage(pageNumber) {
            const totalPages = Math.ceil(this.count.total / this.limit);
            this.page = Math.max(1, Math.min(pageNumber, totalPages));
            this.fetchLegalPersons();
        },
        getPageNumbers() {
            const totalPages = Math.ceil(this.count.total / this.limit);
            return Array.from({
                length: totalPages
            }, (_, i) => i + 1);
        },
        addressWithLink(address) {
            return `${address} <a target="_blank" href="https://www.google.com/maps/place/${encodeURIComponent(address)}"><i class="bi bi-pin-map"></i></a>`;
        },

        // legal statuses/forms code: 
        legalForms: [],
        legalStatuses: [],
        init() {
            const urlParams = new URLSearchParams(window.location.search);

            const fetchFormsAndStatuses = () => {
                return Promise.all([
                    fetch('<?= BASE_URL ?><?= API_FILE ?>?extra=forms')
                        .then(response => response.json())
                        .then(data => {
                            this.legalForms = data.data;
                        })
                        .catch(error => {
                            console.error('Error fetching legal forms:', error);
                        }),
                    fetch('<?= BASE_URL ?><?= API_FILE ?>?extra=statuses')
                        .then(response => response.json())
                        .then(data => {
                            this.legalStatuses = data.data;
                        })
                        .catch(error => {
                            console.error('Error fetching legal statuses:', error);
                        })
                ]);
            };

            if (urlParams.toString()) {
                fetchFormsAndStatuses().then(() => {
                    this.legalPersonIds = urlParams.get('ids') || '';
                    this.legalPersonTitle = urlParams.get('title') || '';
                    this.legalPersonAddr = urlParams.get('addr') || '';
                    this.legalPersonForm = urlParams.get('form') || '';
                    this.legalPersonStatus = urlParams.get('status') || '';
                    this.regFrom = urlParams.get('reg_from') || '';
                    this.regTo = urlParams.get('reg_to') || '';
                    this.unregFrom = urlParams.get('unreg_from') || '';
                    this.unregTo = urlParams.get('unreg_to') || '';
                    this.showLiquidated = urlParams.get('show_l') === 'true';

                    clearUrlParams(); // Clear URL parameters
                    this.fetchLegalPersons(); // Fetch data immediately
                });
            } else {
                fetchFormsAndStatuses();
            }
        },
        // Add this function to build and copy the URL
        shareRequestUrl() {
            const baseUrl = '<?= BASE_URL ?>';

            const params = new URLSearchParams({
                ids: this.legalPersonIds,
                title: this.legalPersonTitle,
                addr: this.legalPersonAddr,
                form: this.legalPersonForm,
                status: this.legalPersonStatus,
                reg_from: this.regFrom,
                reg_to: this.regTo,
                unreg_from: this.unregFrom,
                unreg_to: this.unregTo,
                show_l: this.showLiquidated ? 'true' : ''
            });

            // Remove empty parameters
            for (let key of Array.from(params.keys())) {
                if (!params.get(key)) {
                    params.delete(key);
                }
            }

            // Construct the final URL
            const apiUrl = `${baseUrl}?${params.toString().replace(/&?api\.php/g, '')}`;


            // Copy to clipboard
            navigator.clipboard.writeText(apiUrl).then(() => {
                this.showToast('Užklausa su parametrais nukopijuota', 'success');
            }).catch(err => {
                this.showToast('Nepavyko', 'Užklausos nukopijuoti nepavyko', 'danger');
                console.error('Nepavyko nukopijuoti užklausos: ', err);
            });
        }

    };
}