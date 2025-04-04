<?php
require_once(__DIR__ . '/config.php');
require_once(BASE_DIR . 'front/front-functions.php');
if (!file_exists(DBFILE)) {
    header("Location:" . BASE_URL . "import.php");
    die();
}
?>

<?php include(BASE_DIR . 'views/header.php'); ?>

<p class="mt-3">Norite sužinoti, <em>kiek įmonių įregistruota jūsų name</em>? Ar name, gatvėje, miestelyje <em>yra religinių
        bendruomenių</em>? Ar <em>Jūsų planuojamame įsigyti būste nėra įregistruotų įmonių</em>? Kiek mažųjų bendrijų
    yra jūsų miestelyje? O kiek – Lietuvoje? Visa tai galite sužinoti čia. Ir – Jūsų neerzins <em>Captcha</em> paveikslėliai. </p>
<p><em>Programoje naudojami Juridinių asmenų registro atviri duomenys (<a href="#dataDocs">plačiau</a>).</em></p>


<div class="row">
    <div class="col-12 col-lg-8 col-xl-6 order-2 order-lg-1 my-5">

        <h1 class="display-4 d-block d-lg-none mb-3">Atlikti paiešką</h1>
        <div class="row">
            <div class="col col-12">
                <div class="form-floating mb-3">
                    <input id="ids" name="ids" type="text" class="form-control"
                        placeholder="Juridinių asmenų kodas (ar kodai, atskirti kableliais)" x-model="legalPersonIds"
                        @keyup.enter="resetPageAndFetch">
                    <label for="ids">Kodas (arba kodai, atskirti kableliais)</label>
                </div>
            </div>
            <div class="col col-12">
                <div class="form-floating mb-3">
                    <input id="title" name="title" type="text" class="form-control" placeholder="Pavadinimas"
                        x-model="legalPersonTitle" @keyup.enter="resetPageAndFetch">
                    <label for="title">Pavadinimas</label>
                </div>
            </div>
            <div class="col col-12">
                <div class="form-floating mb-3">
                    <input id="addr" name="addr" type="text" class="form-control" placeholder="Adresas"
                        x-model="legalPersonAddr" @keyup.enter="resetPageAndFetch">
                    <label for="addr">Adresas</label>
                </div>
            </div>
            <div class="col col-12">
                <div class="form-floating mb-3">
                    <select id="form" name="form" class="form-select" x-model="legalPersonForm">
                        <option value=""></option>
                        <template x-for="form in legalForms" :key="form.form_kodas">
                            <option :value="form.form_kodas" x-text="form.form_pav_ilgas"></option>
                        </template>
                    </select>
                    <label for="form">Teisinė forma</label>
                </div>
            </div>
            <div class="col col-12">
                <div class="form-floating mb-3">
                    <select id="status" name="status" class="form-select" x-model="legalPersonStatus">
                        <option value=""></option>
                        <template x-for="status in legalStatuses" :key="status.stat_kodas">
                            <option :value="status.stat_kodas" x-text="status.stat_pavadinimas"></option>
                        </template>
                    </select>
                    <label for="status">Teisinis statusas</label>
                </div>
            </div>
            <div class="col col-12 col-sm-6">
                <div class="form-floating mb-3">
                    <input id="reg_from" name="reg_from" type="text" class="form-control date-input"
                        placeholder="Registravimo data, nuo" x-model="regFrom" @keyup.enter="resetPageAndFetch">
                    <label for="reg_from">Registravimo data, nuo</label>
                </div>
            </div>
            <div class="col col-12 col-sm-6">
                <div class="form-floating mb-3">
                    <input id="reg_to" name="reg_to" type="text" class="form-control date-input"
                        placeholder="Registravimo data, iki" x-model="regTo" @keyup.enter="resetPageAndFetch">
                    <label for="reg_to">Registravimo data, iki</label>
                </div>
            </div>
            <div class="col col-12">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="" name="showLiquidated" id="showLiquidated"
                        @click="$nextTick(() => clearUnregDates());" x-model="showLiquidated">
                    <label class="form-check-label" for="showLiquidated">Rodyti ir išregistruotus</label>
                </div>
            </div>
            <div class="col col-12 col-sm-6" x-show="showLiquidated">
                <div class="form-floating mb-3">
                    <input id="unreg_from" name="unreg_from" type="text" class="form-control date-input"
                        placeholder="Išregistravimo data, nuo" x-model="unregFrom" @keyup.enter="resetPageAndFetch">
                    <label for="unreg_from">Išregistravimo data, nuo</label>
                </div>
            </div>
            <div class="col col-12 col-sm-6" x-show="showLiquidated">
                <div class="form-floating mb-3">
                    <input id="unreg_to" name="unreg_to" type="text" class="form-control date-input"
                        placeholder="Išregistravimo data, iki" x-model="unregTo" @keyup.enter="resetPageAndFetch">
                    <label for="unreg_to">Išregistravimo data, iki</label>
                </div>
            </div>
            <div class="col col-12">
                <div class="mb-3 btn-group">
                    <button class="btn btn-primary my-1" type="button" style="width:160px;" @click="resetPageAndFetch">
                        <span class="bi bi-binoculars"></span> Ieškoti
                    </button>
                    <button class="btn btn-danger my-1" type="button" @click="clearForm">
                        <span class="bi bi-eraser"></span><span class="d-none d-md-inline"> Išvalyti formą</span>
                    </button>
                    <button x-show="legalPersons.length > 0" class="btn btn-secondary my-1" type="button"
                        @click="shareRequestUrl" title="Kopijuoti paskutinės atliktos paieškos nuorodą">
                        <i class="bi bi-clipboard"></i><span class="d-none d-md-inline"> Kopijuoti</span>
                    </button>
                    <button data-bs-toggle="modal" class="btn btn-success my-1" data-bs-target="#searchHelpModal">
                        <span class="bi bi-question-circle"></span><span class="d-none d-md-inline"> Kaip
                            ieškoti?</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4 col-xl-6  order-1 order-lg-2 my-5">
        <div class="bg-light p-5 rounded">
            <div class="d-none d-lg-block">
                <h1 class="display-4">NAUJA!</h1>
                <p class="lead">Galite <strong>užsiprenumeruoti</strong> pranešimus apie Juridinių asmenų duomenų pakeitimus ir jų viešus pranešimus.</p>
                <hr class="my-4">
                <p>Tai galite padaryti paspaudę <button class="btn btn-sm btn-danger"><i class="bi bi-bell"></i></button> ties juridinio asmens įrašu paieškos rezultatų lentelėje.</p>
            </div>
            <h2 class="display-6">Norite tvarkyti prenumeratas?</h2>
            <p>Gaukite nuorodą į jų tvarkymo puslapį įvesdami savo el. pašto adresą:</p>
            <form method="get" action="manage.php">
                <div class="input-group mb-3">
                    <input type="email" class="form-control" name="email" placeholder="Įveskite savo el. pašto adresą" required>
                    <input type="hidden" name="key" value="GetNewKey">
                    <button class="btn btn-primary" type="submit">Gauti nuorodą</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="table-responsive">
    <nav x-show="count.total > count.limit && count.returned > 0" aria-label="Page navigation example">
        <ul class="pagination justify-content-start">
            <!-- First page button -->
            <li class="page-item" :class="{ disabled: page === 1 }">
                <a title="pirmas" class="page-link" href="#" @click.prevent="changePage(1)"><i
                        class="bi bi-chevron-bar-left"></i></a>
            </li>
            <!-- 5 pages back button -->
            <li class="page-item" x-show="count.total > (count.limit * 5)" :class="{ disabled: page <= 5 }">
                <a title="5 atgal" class="page-link" href="#" @click.prevent="changePage(page - 5)"><i
                        class="bi bi-chevron-double-left"></i></a>
            </li>
            <!-- Previous page button -->
            <li class="page-item" :class="{ disabled: page === 1 }">
                <a title="1 atgal" class="page-link" href="#" @click.prevent="changePage(page - 1)"><i
                        class="bi bi-chevron-left"></i></a>
            </li>
            <!-- Current page indicator -->
            <li class="page-item current">
                <span title="dabartinis puslapis" class="page-link">
                    psl. <span x-text="page"></span> iš <span x-text="Math.ceil(count.total / limit)"></span>
                </span>
            </li>
            <!-- Next page button -->
            <li class="page-item" :class="{ disabled: page === Math.ceil(count.total / limit) }">
                <a title="1 į priekį" class="page-link" href="#" @click.prevent="changePage(page + 1)"><i
                        class="bi bi-chevron-right"></i></a>
            </li>
            <!-- 5 pages forward button -->
            <li class="page-item" x-show="count.total > (count.limit * 5)"
                :class="{ disabled: page + 5 >= Math.ceil(count.total / limit) }">
                <a title="5 į priekį" class="page-link" href="#" @click.prevent="changePage(page + 5)"><i
                        class="bi bi-chevron-double-right"></i></a>
            </li>
            <!-- Last page button -->
            <li class="page-item" :class="{ disabled: page === Math.ceil(count.total / limit) }">
                <a title="paskutinis" class="page-link" href="#"
                    @click.prevent="changePage(Math.ceil(count.total / limit))"><i
                        class="bi bi-chevron-bar-right"></i></a>
            </li>
        </ul>
    </nav>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" x-show="isLoading">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Įkeliama...</span>
        </div>
    </div>

    <div style="padding: 5px;" class="alert" :class="count.returned === count.total ? 'alert-success' : 'alert-warning'"
        role="alert" x-show="legalPersons.length > 0">
        Rodomi įrašai: <span x-text="count.page * count.limit - count.limit + 1"></span> - <span
            x-text="count.page * count.limit - count.limit + count.returned"></span> iš <span
            x-text="count.total"></span>
    </div>
    <table x-show="legalPersons.length > 0" class="table table-striped">
        <thead>
            <tr>
                <th>Kodas</th>
                <th>Pavadinimas</th>
                <th>Adresas</th>
                <th style="white-space: nowrap;">Registr. data</th>
                <th>Teisinė forma</th>
                <th>Teisinis statusas</th>
                <th>Veiksmai</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="person in legalPersons" :key="person.ja_kodas">
                <tr>
                    <td x-text="person.ja_kodas"></td>
                    <td x-text="person.ja_pavadinimas"></td>
                    <td x-html="addressWithLink(person.adresas)"></td>
                    <td x-text="person.ja_reg_data"></td>
                    <td x-text="person.form_pavadinimas"></td>
                    <td x-text="person.stat_pavadinimas"></td>
                    <td class="text-end">
                        <div class="btn-group">
                            <a href="#" class="btn btn-outline-success btn-sm" title="Peržiūrėti duomenis"
                                class="text-success" @click.prevent="fetchDetails(person.ja_kodas)"
                                data-bs-toggle="modal" data-bs-target="#detailsModal"><i class="bi bi-search"></i></a>
                            <a href="#" class="btn btn-danger btn-sm" title="Prenumeruoti informacinius pranešimus"
                                class="text-danger" @click.prevent="subscribe(person.ja_kodas)" data-bs-toggle="modal"
                                data-bs-target="#subscribeModal"><i class="bi bi-bell"></i></a>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<div class="row">
    <div class="col col-12 col-lg-6">
        <?php require_once('front/api-docs.php') ?>
    </div>
    <div class="col col-12 col-lg-6">
        <?php require_once('front/data-docs.php') ?>
    </div>
</div>

<!-- Subscribe Modal -->
<div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subscribeModalLabel">Prenumeruoti pranešimus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Žemiau įrašę savo el. pašto adresą galite prenumeruoti šio juridinio asmens viešus pranešimus bei
                    Registro tvarkytojo pranešimus apie šio juridinio asmens duomenų ir informacijos pakeitimus. </p>
                <form id="subscribeForm" @submit.prevent="submitSubscription">
                    <input type="hidden" id="legalPersonCode" x-model="subscription.ja_kodas">
                    <div class="mb-3">
                        <label for="legalPersonName" class="form-label">Juridinio asmens pavadinimas</label>
                        <input type="text" class="form-control" id="legalPersonName"
                            x-model="subscription.ja_pavadinimas" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="subscriberEmail" class="form-label">Jūsų el. pašto adresas</label>
                        <input type="email" class="form-control" id="subscriberEmail" x-model="subscription.email"
                            required>
                    </div>
                    <div class="btn-group float-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Atšaukti</button>
                        <button type="submit" class="btn btn-primary">Prenumeruoti</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Juridinio asmens duomenys</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Loading Spinner in Modal -->
                <div class="text-center my-3" x-show="modalLoading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Įkeliama...</span>
                    </div>
                </div>
                <template x-if="selectedPerson">
                    <div x-show="!modalLoading">
                        <table class="table table-bordered">
                            <tr>
                                <th>Kodas</th>
                                <td x-text="selectedPerson.ja_kodas"></td>
                            </tr>
                            <tr>
                                <th>Pavadinimas</th>
                                <td x-text="selectedPerson.ja_pavadinimas"></td>
                            </tr>
                            <tr>
                                <th>Adresas</th>
                                <td x-html="addressWithLink(selectedPerson.adresas)"></td>
                            </tr>
                            <tr>
                                <th>Reg. data</th>
                                <td x-text="selectedPerson.ja_reg_data"></td>
                            </tr>
                            <tr>
                                <th>Teisinė forma</th>
                                <td x-text="selectedPerson.form_pav_ilgas"></td>
                            </tr>
                            <tr>
                                <th>Tipas</th>
                                <td x-text="selectedPerson.tipas"></td>
                            </tr>
                            <tr>
                                <th>Teisinis statusas</th>
                                <td x-text="selectedPerson.stat_pavadinimas"></td>
                            </tr>
                            <tr x-show="selectedPerson.isreg_data">
                                <th>Išregistravimo data</th>
                                <td x-text="selectedPerson.isreg_data"></td>
                            </tr>
                            <tr x-show="selectedPerson.stat_kodas != 0 && !selectedPerson.isreg_data">
                                <th>Teisinio statuso įgijimo data:</th>
                                <td x-text="selectedPerson.stat_data_nuo"></td>
                            </tr>
                            <?php /*
                                    <tr>
                                        <th>Duomenys paimti iš registro:</th>
                                        <td>
                                            <span x-text="selectedPerson.formavimo_data"></span><span x-show="selectedPerson.tikr_data">; individualios įmonės pavadinimo paėmimo iš registro data: <span x-text="selectedPerson.tikr_data"></span></span>
                                        </td>
                                    </tr>
                                    */ ?>
                            <tr>
                                <th>Duomenys ir dokumentai Registre:</th>
                                <td>
                                    <a rel="noreferrer"
                                        :href="'https://www.registrucentras.lt/jar/p/index.php?kod=' + selectedPerson.ja_kodas"
                                        target="_blank">
                                        <i class="bi bi-person"></i> Pagr. duomenys (reikės captcha)
                                    </a> &nbsp;
                                    <a rel="noreferrer"
                                        :href="'https://www.registrucentras.lt/jar/p/dok.php?kod=' + selectedPerson.ja_kodas"
                                        target="_blank">
                                        <i class="bi bi-person-vcard"></i> Dok. sąrašas
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div x-show="!selectedPerson.pakeitimai_po_formavimo">
                            <div class="alert alert-danger" role="alert">
                                Papildomų patikrinimų atlikti nepavyko.
                            </div>
                        </div>
                        <?php /*
                                <div x-show="selectedPerson.pakeitimai_po_formavimo.length === 0">
                                    <div class="alert alert-success" role="alert">
                                        Po duomenų paėmimo iš Registro nebuvo užregistruota naujų dokumentų, susijusių su šiuo juridiniu asmeniu, taigi, duomenys yra aktualūs.
                                    </div>
                                </div>
                                */ ?>
                        <div x-show="selectedPerson.pakeitimai_po_formavimo.length > 0">
                            <div class="alert alert-warning" role="alert">
                                Yra naujų, po duomenų paėmimo iš Registro sukurtų dokumentų, tad šie duomenys gali būti
                                neaktualūs.
                            </div>
                        </div>

                        <div x-show="selectedPerson.pakeitimai_po_formavimo.length > 0">
                            <p>Pakeitimai po duomenų paėmimo iš Registro</p>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Vėliau registruoti dokumentai</th>
                                        <th>Registracijos data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="change in selectedPerson.pakeitimai_po_formavimo"
                                        :key="change.dok_reg_data">
                                        <tr>
                                            <td x-text="change.naujas_dok"></td>
                                            <td x-text="change.dok_reg_data"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <p x-html="queries" style="font-size: 0.8em"></p>
                    </div>
                </template>
                <div x-show="!modalLoading && !selectedPerson">
                    <p>Detalių duomenų rasti nepavyko.</p>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Help Modal -->
<div class="modal fade" id="searchHelpModal" tabindex="-1" aria-labelledby="searchHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog  modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="searchHelpModalLabel">Kaip ieškoti?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Privalomų laukų nėra, tačiau bent vieno lauko reikšmė turi būti užpildyta. Teksto laukuose turi būti
                    mažiausiai trys simboliai.</p>

                <p>Naudokite šias gaires, kad tinkamai užpildytumėte paieškos laukus:</p>
                <ul>
                    <li><strong>Kodas</strong>: Įveskite juridinio asmens kodą; galite įvesti ir daugiau nei vieną kodą,
                        tokiu atveju kodus atskirkite kableliais. Pvz., <code>123456789, 987654321</code>.</li>
                    <li><strong>Pavadinimas</strong>: Įveskite juridinio asmens pavadinimą arba jo dalį. Pvz.,
                        <code>UAB Pavyzdinė įmonė</code>. Galima įvesti nepilnus žodžius. Bus ieškomi visi žodžiai.
                    </li>
                    <li><strong>Adresas</strong>: Įveskite adresą arba jo dalį. Naudokite kabutes frazėms, pvz.,
                        <code>"Krokodilo 3-oji g." Vilnius</code>. <em>Pastaba:</em> šiame lauke galima naudoti tik
                        pilnus žodžius.
                    </li>
                    <li><strong>Teisinė forma</strong>: Pasirinkite teisinę formą iš sąrašo.</li>
                    <li><strong>Teisinis statusas</strong>: Pasirinkite teisinį statusą iš sąrašo.</li>
                    <li><strong>Registravimo data, nuo</strong>: Įveskite registravimo datą nuo (YYYY-MM-DD formatu).
                    </li>
                    <li><strong>Registravimo data, iki</strong>: Įveskite registravimo datą iki (YYYY-MM-DD formatu).
                    </li>
                    <li><strong>Rodyti ir išregistruotus</strong>: Pažymėkite šį laukelį, jei norite matyti ir
                        išregistruotus juridinius asmenis.</li>
                    <li><strong>Išregistravimo data, nuo</strong>: Įveskite išregistravimo datą nuo (YYYY-MM-DD
                        formatu), jei pažymėjote laukelį "Rodyti ir išregistruotus".</li>
                    <li><strong>Išregistravimo data, iki</strong>: Įveskite išregistravimo datą iki (YYYY-MM-DD
                        formatu), jei pažymėjote laukelį "Rodyti ir išregistruotus".</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Uždaryti</button>
            </div>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div :class="toastClass" class="toast align-items-center text-bg-success border-0" role="alert"
        aria-live="assertive" aria-atomic="true" x-show="toastVisible" x-ref="toast">
        <div class="d-flex">
            <div class="toast-body" x-text="toastContent"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="hideToast()"
                aria-label="Close"></button>
        </div>
    </div>
</div>
<?php include(BASE_DIR . 'views/footer.php'); ?>