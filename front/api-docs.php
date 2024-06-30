<?php if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.'); ?>

<div class="card my-3">
    <div class="card-body bg-light">
        <h5 class="card-title">Geriau API?</h5>
        <p>Jei norite pasinaudoti svetainės viešuoju API, skaitykite dokumentaciją. </p>

        <div class="accordion" id="accordionExample">
            <div class="accordion-item">
                <h5 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" data-bs-start-collapsed="true" aria-expanded="true" aria-controls="collapseOne">
                        API dokumentacija
                    </button>
                </h5>
                <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                    <div class="accordion-body">

                        <p>API užklausos pavyzdys: <br>
                            <a href="<?= BASE_URL ?><?= API_FILE ?>?title=uab&addr=švenčionys&amp;reg_from=2021-01-21"><?= BASE_URL ?><?= API_FILE ?>?title=uab&addr=švenčionys&amp;reg_from=2021-01-21</a>
                        </p>

                        <p class="fw-bold">API taškas</p>
                        <p><strong>GET</strong> /jar/<?= API_FILE ?></p>

                        <p class="fw-bold my-3">Užklausos parametrai</p>
                        <ul>
                            <li><strong>ids</strong> (string): Juridinių asmenų kodai, atskirti kableliais. Tik skaitmenys ir kableliai leidžiami.</li>
                            <li><strong>title</strong> (string): Juridinio asmens pavadinimas. Tik raidės, skaičiai, tarpai, kableliai ir brūkšneliai leidžiami.</li>
                            <li><strong>addr</strong> (string): Juridinio asmens adresas. Tik pilni žodžiai. Jei norite ieškoti gatvių su numeriais, apskliauskite frazę kabutėmis (pvz., "Justiniškių g. 10"). Kaimų, miestelių pavadinimai rašomi kilmininko linksniu („Stačiūnų k.“, o ne „Stačiūnai“)</li>
                            <li><strong>form</strong> (integer): Teisinės formos kodas, jų gavimas dokumentuotas žemiau (teisinės formos pavyzdžiai: uždaroji akcinė bendruomenė, mažoji bendrija, ir pan.).</li>
                            <li><strong>status</strong> (integer): Teisinio statuso kodas, jų gavimas dokumentuotas žemiau (teisinis statusas nusako juridinio asmens veikimo stadiją: bankrutuojantis, likviduojamas ir pan.).</li>
                            <li><strong>reg_from</strong> (date): Registravimo data, nuo (formatu Y-m-d).</li>
                            <li><strong>reg_to</strong> (date): Registravimo data, iki (formatu Y-m-d).</li>
                            <li><strong>show_l</strong> (boolean): Jei reikšmė yra 'true', bus rodomi ir išregistruoti juridiniai asmenys.</li>
                            <li><strong>unreg_from</strong> (date): Išregistravimo data, nuo (formatu Y-m-d).</li>
                            <li><strong>unreg_to</strong> (date): Išregistravimo data, iki (formatu Y-m-d).</li>
                            <li><strong>limit</strong> (integer): Kiek įrašų atsiųsti (<= 100, numatytoji reikšmė 20)</li>
                            <li><strong>page</strong> (integer): Paiešką atitikusių duomenų puslapis.</li>
                        </ul>

                        <p class="my-3">Teksto laukų turinys ieškomas neskiriant didžiųjų ir mažųjų raidžių. Galima įvesti nepilnus žodžius. Bus ieškoma visų įvestų žodžių atitikmenų.</p>

                        <p class="fw-bold my-3">Atsakymas</p>
                        <div class="card">
                            <pre class="card-body">
{
    "status_code": 200,
    "status_message": "Success",
    "execution_time": 0.1234,
    "data": [
        {
            "ja_kodas": "123456789",
            "ja_pavadinimas": "Įmonės pavadinimas",
            "adresas": "Adreso informacija",
            "form_pavadinimas": "Teisinė forma",
            "stat_pavadinimas": "Teisinis statusas",
            "ja_reg_data": "2023-01-01",
            "isreg_data": null
        }
    ],
    "count": {
        "returned": 1,
        "total": 1
        "page": 1,
        "limit": 100
    }
}
</pre>
                        </div>
                        <p class="my-3">Objekte 'count' rodomas grąžintų įrašų skaičius ('returned'), įrašų puslapis ('page'), ir bendras užklausą atitikusių įrašų skaičius ('total'). Didžiausias galimas (ir numatytasis) grąžinamas įrašų skaičius - 100 (parametras 'limit').</p>

                        <p class="fw-bold my-3">Registro klasifikatoriai</p>

                        <p class="my-3">Norint gauti Juridinių asmenų registro teisinių formų ir Teisinių statusų klasifikatoriaus duomenis (atskirai nuo juridinių asmenų duomenų):</p>
                        <ul>
                            <li><strong>extra=forms</strong> (text `forms`): grąžina teisinių formų duomenis.</li>
                            <li><strong>extra=statuses</strong> (text `statuses`): grąžina teisinių statusų duomenis.</li>
                        </ul>

                        <p class="fw-bold my-3">Klaidų tvarkymas</p>
                        <p class="my-3">Netinkamo užklausos duomenų formato atveju (pvz., yra raidžių `ids` parametre), API grąžina `400 Bad Request` būsenos kodą su tokiu atsakymu:</p>
                        <div class="card bg-light">
                            <pre class="card-body">
{
    "status_code": 400,
    "status_message": "Blogai suformuota užklausa: nenaudotini ženklai "ids" parametre",
    "execution_time": 0.004,
    "data": []
}
</pre>
                        </div>

                        <p class="my-3">Jei nerasta rezultatų, API grąžina `404 Not Found` būsenos kodą su tokiu atsakymu:</p>
                        <div class="card bg-light">
                            <pre class="card-body">
{
    "status_code": 404,
    "status_message": "Duomenų pagal užklausą rasti nepavyko",
    "execution_time": 0.0023,
    "data": []
}
</pre>
                        </div>

                        <p class="my-3">Nepalaikomo užklausos metodo atveju API grąžina `405 Method Not Allowed` būsenos kodą su tokiu atsakymu:</p>
                        <div class="card bg-light">
                            <pre class="card-body">
{
    "status_code": 405,
    "status_message": "tokio metodo nėra",
    "execution_time": 0.0002,
    "data": []
}
</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>
</div>