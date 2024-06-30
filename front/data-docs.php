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
                            <p class="fw-bold">Svetainės ir jos duomenų naudojimo sąlygos</p>
                            <p>Šioje svetainėje pateikiami Juridinių asmenų registro duomenys atnaujinami kartą per mėnesį, mėnesio pradžioje, o atnaujinimo data nurodoma juridinio asmens detaliuose duomenyse. Svetainė papildomai teikia veikiančių individualių įmonių ir komanditinių ūkinių bendrijų pavadinimus, kurie atnaujinami rečiau nei kartą per mėnesį. Sistema rodo, kada paskutinį kartą buvo atnaujinti konkretūs pavadinimai. Šiuos duomenis galite <a href="<?= BASE_URL ?>scrapit.php?download=individual">atsisiųsti</a>.</p>
                            <p>Svetainės duomenys gali būti naudojami tokiomis pačiomis sąlygomis kaip ir pirminiai registro duomenys. Be to, teikiama vieša API paslauga, kuri yra nemokama, tačiau jos veikimas nėra garantuotas.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>