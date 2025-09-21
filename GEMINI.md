
# Project Overview

# Project Philosophy

1.  **Backend First, API-Only:** This is a backend-only project. All features must be exposed as a JSON API. There is no frontend UI.
2.  **Spec-Driven Development:** All development must be driven by the specifications in the `spec` folder. Any changes or new features must be documented in a corresponding `.md` file in the `spec` folder *before* any code is written.

# Project Overview

This is a Laravel-based web application for monitoring and analyzing commodity prices. It appears to be designed for a TPID (Tim Pengendali Inflasi Daerah - Regional Inflation Control Team) in Indonesia.

The application fetches commodity price data from two sources:
1.  **Google Sheets:** It syncs commodity data and combined price data from a Google Sheet.
2.  **SP2KP Service:** It updates the latest data from an external service called SP2KP.

The application also features a module for generating analysis prompts for a Large Language Model (LLM) based on the collected price data. This is likely used to automate the generation of reports and insights for the TPID.

**Key Technologies:**

*   **Backend:** Laravel (PHP)
*   **Database:** Not explicitly defined, but likely MySQL or similar based on standard Laravel configurations.
*   **Other:** Google API Client for Sheets integration.

# Building and Running

## Development Environment

To run the development server, you can use the following command:

```bash
php artisan serve
```

To run the queue worker, you can use the following command:

```bash
php artisan queue:listen
```

To view the application logs, you can use the following command:

```bash
php artisan pail
```

# Development Conventions

## API Endpoints

The main API endpoints are defined in `routes/api.php`. Key routes include:

*   `/api/harga`: Fetches price data.
*   `/api/komoditas`: Fetches commodity data.
*   `/api/tpid/report/...`: A group of routes for generating TPID reports and analysis prompts.

## Data Models

The core data models are:

*   `App\Models\Harga`: Represents the price of a commodity at a specific time.
*   `App\Models\Komoditas`: Represents a commodity.

## Services

The application uses a service-oriented architecture. Key services include:

*   `App\Services\GoogleSheetsService`: For interacting with the Google Sheets API.
*   `App\Services\SP2KPService`: For interacting with the SP2KP API.
*   `App\Services\HargaService`: For handling business logic related to prices.
*   `App\Services\KomoditasService`: For handling business logic related to commodities.
*   `App\Services\TpidReportService`: For generating TPID reports and analysis prompts.

## TODO

*   The database schema is not fully clear from the provided files. It would be beneficial to document the main tables and their relationships.
