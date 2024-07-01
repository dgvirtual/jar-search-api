# Lithuanian Legal Persons Search tool and API

This project allows to do a convenient search of Lithuanian company and other legal persons data, also providing an API for that purpose. 

On the development side, the idea was to build an app backend using only the libraries available within PHP itself (so, no composer, no frameworks, no external database server), and simple UI libraries on the frontend (Bootstrap 5 and Alpine.js 3).

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Contributing](#contributing)
- [License](#license)

## Features

- Automatically imports legal persons data from VĮ Registrų centras (initially and, if configured via cron - regularly)
- Offers convenient search by legal person code, name, address, legal form, legal status, registration date, etc.
- Offers free API to use outside of this tool's scope 

## Requirements

- PHP 7.4 or higher, with:
- SQLite3, mbstring, curl PHP modules
- exec() and shell_exec() commands not disabled (they are on some shared hosting systems)
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

## Contributing

Contributions are welcome! Fork the project and so some pull requests!

A fork using MySQL would be appreciated, to help compare the performance of the two DB's.

## Licence

The code is made available under MIT licence. See file [LICENCE.md](LICENCE.md) for details.

The open data, which the app imports, is distributed under the [Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/deed.lt). More info is available here: https://www.registrucentras.lt/atviri_duomenys/



