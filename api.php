<?php

require_once(__DIR__ . '/config.php');
if (!file_exists(DBFILE)) {
    header("Location:" . BASE_URL . "import.php");
    die();
    #require_once(BASE_DIR . 'data/initialize-db.php');
}
require_once(BASE_DIR . 'back/api-functions.php');
require_once('common/classes.php');

// to show spinners locally (remove in production);
//usleep(300000);

// Connect to the SQLite database
if (!isset($db)) {
    $db = new mySQLite3(BASE_DIR . DBFILE);
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['extra']) && ($_GET['extra'] === 'statuses' || $_GET['extra'] === 'forms')) {

        $results = $db->query("SELECT * FROM " . $_GET['extra'] . " ORDER BY " . substr($_GET['extra'], 0, 4)  . "_pavadinimas ASC");

        $data = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        respond(200, 'Duomenys sėkmingai gauti: ' . $_GET['extra'], $data, null, null, true);
    }

    if (
        isset($_GET['ids'])
        || isset($_GET['title'])
        || isset($_GET['addr'])
        || isset($_GET['form'])
        || isset($_GET['status'])
        || isset($_GET['reg_from'])
        || isset($_GET['unreg_from'])
        || isset($_GET['unreg_to'])
    ) {
        $conditions = [];
        $params = [];

        if (isset($_GET['reg_from']) && !empty($_GET['reg_from'])) {
            $conditions[] = "ja_reg_data >= ?";
            $params[] = $_GET['reg_from'];
        }

        if (isset($_GET['reg_to']) && !empty($_GET['reg_to'])) {
            $conditions[] = "ja_reg_data <= ?";
            $params[] = $_GET['reg_to'];
        }

        if (isset($_GET['unreg_from']) && !empty($_GET['unreg_from'])) {
            $conditions[] = "isreg_data >= ?";
            $params[] = $_GET['unreg_from'];
        }

        if (isset($_GET['unreg_to']) && !empty($_GET['unreg_to'])) {
            $conditions[] = "isreg_data <= ?";
            $params[] = $_GET['unreg_to'];
        }

        // Validate and process ids parameter
        if (isset($_GET['ids']) && !empty($_GET['ids'])) {


            if (!validateIdsFirst($_GET['ids'])) {
                respond(400, 'Blogai suformuota užklausa: juridinio asmens kode turi būti 9 skaičiai');
            }
            if (!validateIds($_GET['ids'])) {
                respond(400, 'Blogai suformuota užklausa: nenaudotini ženklai "ids" parametre');
            }
            if (!isValidId($_GET['ids'])) {
                respond(400, 'Blogai suformuota užklausa: neteisingai sudarytas (pirmas) juridinio asmens kodas');
            }
            $ids = explode(',', $_GET['ids']);
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $conditions[] = "persons.ja_kodas IN ($placeholders)";
            foreach ($ids as $id) {
                $params[] = $id;
            }
        }

        // Validate and process title parameter
        if (isset($_GET['title']) && !empty($_GET['title'])) {
            if (!validateTextField($_GET['title'])) {
                respond(400, 'Blogai suformuota užklausa: nenaudotini ženklai "title" parametre');
            }
            $title = str_replace('"', '', $_GET['title']); // Remove double quotes
            $words = explode(' ', $title);
            foreach ($words as $word) {
                $normalizedWord = mb_strtolower($word);
                $conditions[] = "LOWER(persons.ja_pavadinimas) LIKE ?";
                $params[] = '%' . SQLite3::escapeString($normalizedWord) . '%';
            }
        }

        //Validate and process form parameter
        if (isset($_GET['form']) && !empty($_GET['form']) && is_numeric($_GET['form'])) {
            if (!is_numeric($_GET['form'])) {
                respond(400, 'Blogai suformuota užklausa: teisinės formos kodas turi būti skaičius');
            }
            $conditions[] = "persons.form_kodas = ?";
            $params[] = $_GET['form'];
        }

        //Validate and process status parameter
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            if (!is_numeric($_GET['status'])) {
                respond(400, 'Blogai suformuota užklausa: teisinio statuso kodas turi būti skaičius');
            }
            $conditions[] = "persons.stat_kodas = ?";
            $params[] = $_GET['status'];
        }

        // Validate and process addr parameter
        if (isset($_GET['addr'])) {
            // if (!validateTextField($_GET['addr'])) {
            //     respond(400, 'Blogai suformuota užklausa: nenaudotini ženklai "addr" parametre');
            // }

            preg_match_all('/"([^"]+)"|(\S+)/', $_GET['addr'], $matches);
            $tokens = array_map('mb_strtolower', array_filter(array_merge($matches[1], $matches[2])));

            foreach ($tokens as $index => $token) {
                $conditions[] = "LOWER(adresas) REGEXP ?";
                $params[] = $token;
            }
        }

        // Unless explicitly state NOT TO FILTER OUT DE-REGISTERED, filter them out
        if (!isset($_GET['show_l']) || isset($_GET['show_l']) && $_GET['show_l'] != 'true') {
            $conditions[] = "persons.stat_kodas != ?";
            $params[] = 10;
        }

        if (!isset($_GET['limit'])) {
            $limitValue = 100;
        } elseif (!is_int($_GET['limit']) && $_GET['limit'] > 100) {
            $limitValue = 100;
        } else {
            $limitValue = $_GET['limit'];
        }
        $limitClause = ' LIMIT ' . $limitValue;

        if (
            !isset($_GET['page'])
            || (isset($_GET['page']) && !is_numeric($_GET['page']))
            || (isset($_GET['page']) && $_GET['page'] == 1)
        ) {
            $offsetValue = 0;
            $offsetClause = '';
        } else {
            $offsetValue = (int) ((($_GET['page'] - 1) * $limitValue));
            $offsetClause = ' OFFSET ' . $offsetValue;
        }

        $sql = "SELECT persons.*, forms.form_pavadinimas, forms.form_pav_ilgas, forms.tipas, statuses.stat_pavadinimas, individual.tikr_data FROM persons";
        $sql .= " LEFT JOIN forms ON persons.form_kodas = forms.form_kodas";
        $sql .= " LEFT JOIN statuses ON persons.stat_kodas = statuses.stat_kodas";
        $sql .= " LEFT JOIN individual ON persons.ja_kodas = individual.ja_kodas";
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions) . $limitClause . $offsetClause;
        }

        // Debug output for SQL query and parameters
        if (isset($_GET['debug']) && $_GET['debug']) {
            echo "SQL Query: " . $sql . "<br>";
            echo "Parameters: " . implode(', ', $params) . "<br>";
            exit;
        }

        // Execute the query
        $stmt = $db->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        $results = $stmt->execute();

        $data = [];
        $queries = null;
        $recordCount = 0;
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
            $recordCount++;

            if (isset($ids) && count($ids) === 1 && isset($_GET['single'])) {
                $events = scrapHtml($ids[0], $data[0]['formavimo_data']);
                if (isset($events) && !empty($events['new_events'])) {
                    $data[0]['pakeitimai_po_formavimo'] = $events['new_events'];
                } elseif (!$events['success']) {
                    $data[0]['pakeitimai_po_formavimo'] = false;
                } else {
                    $data[0]['pakeitimai_po_formavimo'] = [];
                }

                $queries = $events['queries'] ?? null;
            }
        }

        // count all records matching query:

        $countQuery = "SELECT COUNT(*) as count FROM persons";
        if (!empty($conditions)) {
            $countQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $countStmt = $db->prepare($countQuery);
        foreach ($params as $i => $param) {
            $countStmt->bindValue($i + 1, $param);
        }
        $countResult = $countStmt->execute();
        $totalCount = $countResult->fetchArray(SQLITE3_ASSOC)['count'];

        if ($data) {

            respond(
                200,
                'Success',
                $data,
                $queries,
                [
                    'returned' => $recordCount,
                    'total' =>  (int) $totalCount,
                    'page' => (int) ($_GET['page'] ?? 1),
                    'limit' => (int) $limitValue,
                ]
            );
        } else {
            respond(404, 'Duomenų pagal užklausą rasti nepavyko. Patikrinkite, ar teisingai įvedėte paieškos tekstą ir kitus parametrus. ');
        }
    } else {
        respond(400, 'Blogai suformuota užklausa');
    }
} else {
    respond(405, 'Tokio metodo nėra');
}
