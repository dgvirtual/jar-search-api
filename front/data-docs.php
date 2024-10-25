<?php if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.'); ?>

<div id="dataDocs" class="card my-3">
    <div class="card-body bg-light">
        <h5 class="card-title">Iš kur šie duomenys? Ką galiu su jais daryti?</h5>
        <p>Šioje svetainėje naudojami atviri duomenys iš Juridinių asmenų registro.</p>

        <div class="accordion" id="accordionExample2">
            <div class="accordion-item">
                <h5 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" data-bs-start-collapsed="true" aria-expanded="true" aria-controls="collapseTwo">
                        Plačiau apie duomenis ir jų naudojimo sąlygas
                    </button>
                </h5>
                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample2">
                    <div class="accordion-body">
                        <div class="markdown prose w-full break-words dark:prose-invert light">
                            <p class="fw-bold">Pirminiai duomenys ir jų naudojimo sąlygos</p>
                            <p>Juridinių asmenų registro atviri duomenys, naudojami šioje svetainėje, yra prieinami pagal Creative Commons Attribution 4.0 licenciją (CC BY 4.0), leidžiančią laisvą naudojimą ir dalijimąsi su privalomu šaltinio nurodymu, licencijos nuoroda ir pažymėjimu apie pakeitimus.</p>
                            <p>Daugiau informacijos galite rasti <a target="_new" href="https://www.registrucentras.lt/p/1094">čia</a>.</p>
                            <p>Duomenys yra kas rytą atnaujinami pagal Juridinių asmenų registro <a href="https://www.registrucentras.lt/jar/infleid/publications.do">Informacinių leidinių duomenis</a>. Taigi, galimas duomenų neatitikimas lyginant su aktualiais Juridinių asmenų registro duomenimis - mažiau nei viena para. Tais atvejais, kai bus galimybė, kad duomenys nebėra aktualūs, sistema šią informaciją nurodys juridinio asmens duomenų peržiūros lange. </p>
                            <p class="fw-bold">Svetainės ir jos duomenų naudojimo sąlygos</p>
                            <p>Svetainė papildomai teikia veikiančių (nelikviduotų) individualių įmonių ir komanditinių ūkinių bendrijų pavadinimus, kurių nėra aukščiau nurodytuose Registrų centro pateikiamuose viešuosiuose duomenyse. Šiuos duomenis galite <a href="<?= BASE_URL ?>data/scrapit.php?download=individual">atsisiųsti</a>.</p>
                            <p>Svetainės duomenys gali būti naudojami tokiomis pačiomis sąlygomis kaip ir pirminiai registro duomenys. Be to, teikiama vieša ir nemokama API paslauga. </p>
                            <p>Šis projektas sukurtas mokymosi tikslais ir jo tęstinumas nėra užtikrintas (jei ketinate naudoti projekto duomenis savo aplikacijose, informuokite apie tai svetainės kūrėją).</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>