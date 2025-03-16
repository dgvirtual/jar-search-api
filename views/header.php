<?php
if (count(get_included_files()) == 1) {
    die('This file is not meant to be accessed directly.');
}
?>
<!DOCTYPE html>
<html lang="lt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>favicon.png">
    <title>Juridinių asmenų paieška (patobulinta)</title>
    <meta name="description" content="Juridinių asmenų paieška pagal įvairius kriterijus, sukurta remiantis atvirais Juridinių asmenų registro duomenimis">
    <link rel="canonical" href="<?= BASE_URL ?>">
    <meta name="keywords" content="juridinis asmuo, teisinis statusas, teisinė forma, registracijos data, adresas, kodas, pavadinimas">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.10.2/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

    <!-- Open Graph tags -->
    <meta property="og:title" content="Juridinių asmenų paieška (patobulinta)">
    <meta property="og:description" content="Juridinių asmenų paieška pagal įvairius kriterijus, sukurta remiantis atvirais Juridinių asmenų registro duomenimis">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= BASE_URL ?>">
    <meta property="og:image" content="<?= BASE_URL ?>favicon.png">

    <!-- Twitter Card tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Juridinių asmenų paieška (patobulinta)">
    <meta name="twitter:description" content="Juridinių asmenų paieška pagal įvairius kriterijus, sukurta remiantis atvirais Juridinių asmenų registro duomenimis">
    <meta name="twitter:image" content="<?= BASE_URL ?>favicon.png">

    <style>
        .form-floating label {
            color: grey;
            /* Default color for labels */
        }

        .form-floating input:focus~label,
        .form-floating input:not(:placeholder-shown)~label {
            color: grey;
            /* Color when input is focused or not empty */
        }

        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.4);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
    </style>

</head>

<body>
    <div class="container" x-data="legalPersonApp()">

        <nav class="navbar bg-body-tertiary">
            <div class="container">
                <a class="navbar-brand text-uppercase" href="<?= BASE_URL ?>">
                    <img src="<?= BASE_URL ?>favicon.png" alt="Logo" width="32" height="32" style="margin-top:-6px"> Juridinių asmenų paieška
                </a>
            </div>
        </nav>