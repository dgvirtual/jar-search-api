# Lithuanian Legal Persons Search tool and API

This project allows to do a convenient search of Lithuanian company and other legal persons data, also providing an API for that purpose. 

On the development side, the idea was to build an app backend using only the libraries available within PHP itself (so, no composer, no frameworks, no external database server), and simple UI libraries on the frontend (Bootstrap 5 and Alpine.js 3).

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Keeping data up-to-date](#keeping-data-up-to-date)
- [Speeding up search](#speeding-up-search)
- [License](#license)

## Features

- Automatically imports legal persons data from VĮ Registrų centras (initially and, if configured via cron - regularly, so the data is only up to 1 day old)
- Offers convenient search by legal person code, name, address, legal form, legal status, registration date, etc.
- Offers free API to use outside of this tool's scope 

## Requirements

- PHP 7.4 or higher, with:
- SQLite3, mbstring, curl PHP modules
- exec() and shell_exec() commands not disabled (they are on some shared hosting systems)
- Linux `pdftohtml` tool (available on Linux for install) for data scrapping from the official journal; not necessary for regular operation
- Web server (Apache or PHP's native development server; easy to adapt to other servers supporting PHP)

No Composer; no external PHP libraries; no external DB (runs on SQlite3)

Tested mostly on Linux, should work on Windows with php as well

## Installation

### Step 1: Clone the repository

```bash
git clone https://github.com/dgvirtual/jar-search-api.git
```

### Step 2: Set up site config variables

```bash
cd jar-search-api
cp env .env
```

### Step 3: Review the settings in config.php, adjust to your environment

Review and change constants (only the first two are absolutely necessary):
  * `BASE_URL` (full url of the website, like `https://example.com/jar`)
  * `SUBDIR` (leave empty if the site will run at root and not in subfolder, otherwise - value is subfolder name, like `jar`)
  * `ADMIN_EMAIL`
  * `FROM_EMAIL`

### Step 4: Upload the files to the web server 

The base folder with index.php should be accessible at the address specified in `BASE_URL` 
constant. 

Alternatively, launch local php web server, something like: 

```bash 
php -S localhost:5000
```

### Step 5: Open the `BASE_URL` address with your web browser

And push the start button. 

The app will download the necessary data and set up it's database. 

## Usage

Web app usage is self-explanatory and detailed in the app itself. The web app also contains documentation for the usage of the API.

To update the app regularly with the newest data some cronjobs need to be added to crontab. 

TODO: info to be added.

## Keeping data up to date
To update the data in the app cron functionality can be used. 

Here is a sample cron script: 

```bash
## ensure no output is sent by cron itself
MAILTO=''
## run scrapping script continuously at the end of the day
18 22 * * * /var/www/projectdir/run_scrapping_script.sh
## report the results of scrapping (email is sent by php script itself)
59 23 * * * /usr/bin/php /var/www/projectdir/public/jar/data/scrapit.php report
## check and import new data from RC: each individual csv file at the beginning of the month
30 0 1-5 * * /usr/bin/php /var/www/projectdir/public/jar/data/importnew.php checkifnew persons
31 0 1-5 * * /usr/bin/php /var/www/projectdir/public/jar/data/importnew.php checkifnew persons unreg
32 0 1-5 * * /usr/bin/php /var/www/projectdir/public/jar/data/importnew.php checkifnew forms
34 0 1-5 * * /usr/bin/php /var/www/projectdir/public/jar/data/importnew.php checkifnew statuses
## run big update of individual enterprises names at the start of the month
50 0 1-5 * * /usr/bin/php /var/www/projectdir/public/jar/data/scrapit.php update ifnewmonth
## run daily update of individual enterprises names
55 0 * * * /usr/bin/php /var/www/projectdir/public/jar/data/scrapit.php update
## daily export of individual enterprises names to a new file (for others to import)
56 0 * * * /usr/bin/php /var/www/projectdir/public/jar/data/scrapit.php export_individual
## the RC journal is published at about 9:30 AM Vilnius time
35 9 * * * /usr/bin/php /var/www/projektai/public/jar/data/scrapjournal.php report sendemail
```

And here is the bash script to run the scrapping script example (`run_scrapping_script.sh`):

```bash
#!/bin/bash

# Check for verbose flag
verbose=false
if [ "$1" == "-v" ]; then
    verbose=true
fi

# Function to print messages if verbose is enabled
log() {
    if [ "$verbose" == true ]; then
        echo "$1"
    fi
}

# Loop to run the script every 3 seconds 1000 times (about 1 hour, good for 5 proxies)
for ((i=0; i<1000; i++)); do
    log "Executing PHP script at $(date), count: $i"
    /usr/bin/php /var/www/YOUR_PROJECTDIR/public/jar/data/scrapit.php
    log "Sleeping for 3 seconds"
    sleep 3
done

log "Executed script $i times, done"
```

## Speeding up search
Enabling SQLite3 ICU extension will halve the speed of most time-consuming searches. 
See tutorial [Enhancing SQlite3 with ICU extension](https://dg.lapas.info/irasas/enhancing-sqlite3-with-icu-extension/) 
on how to do that on Ubuntu. If you are running this app on Linux, you can try using one of the two binaries distributed 
with this app (files libSqliteIcu_*.so, with sqlite version and Ubuntu version specified in the file name).

The ICU extension name should be put into the constant `SQLITE_ICU_EXT` in `.env` file.

## Contributing

Contributions are welcome! Fork the project and so some pull requests!

A fork using MySQL would be appreciated, to help compare the performance of the two DB's.

## Licence

The code is made available under MIT licence. See file [LICENCE.md](LICENCE.md) for details.

The open data, which the app imports, is distributed under the [Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/deed.lt). More info is available here: https://www.registrucentras.lt/atviri_duomenys/

## Random notes

To fix database if index errors are reported, you can recreate all the indexes this way: 

```sql
BEGIN TRANSACTION;

DROP INDEX idx_stat_pavadinimas;
DROP INDEX idx_stat_kodas;
DROP INDEX idx_ja_pavadinimas;
DROP INDEX idx_ja_kodas;
DROP INDEX idx_form_tipas;
DROP INDEX idx_form_pavadinimas;
DROP INDEX idx_form_pav_ilgas;
DROP INDEX idx_form_kodas;
DROP INDEX idx_adresas;

CREATE INDEX idx_stat_pavadinimas ON statuses(stat_pavadinimas COLLATE utf8_lithuanian_ci);
CREATE INDEX idx_stat_kodas ON statuses(stat_kodas);
CREATE INDEX idx_ja_pavadinimas ON persons(ja_pavadinimas COLLATE utf8_lithuanian_ci);
CREATE INDEX idx_ja_kodas ON persons(ja_kodas);
CREATE INDEX idx_form_tipas ON forms(tipas);
CREATE INDEX idx_form_pavadinimas ON forms(form_pavadinimas COLLATE utf8_lithuanian_ci);
CREATE INDEX idx_form_pav_ilgas ON forms(form_pav_ilgas COLLATE utf8_lithuanian_ci);
CREATE INDEX idx_form_kodas ON forms(form_kodas);
CREATE INDEX idx_adresas ON persons(adresas COLLATE utf8_lithuanian_ci);

COMMIT;
```

