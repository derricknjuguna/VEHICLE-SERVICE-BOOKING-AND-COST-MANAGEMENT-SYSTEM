# Vehicle Service Booking and Cost Estimation System — AutoCare Garage

## Purpose
A simple booking system for AutoCare Garage. Customers choose one or more
vehicle services, see a live cost estimate in the browser, and submit a
booking request. The PHP server validates everything independently and
returns a booking confirmation with the authoritative total.

## Student
- Admission number: **CT100/G/23745/24**
- Individualization digit: **5** (from 23745)
- Individual requirement: **Vehicle Pickup and Delivery as a selectable service costing KSh 1,500**, implemented in JavaScript and PHP independently.

## Technologies
- HTML5, CSS3, Vanilla JavaScript (no frameworks)
- PHP (built-in development server, no database)
- Python 3 (test runner), Node.js (JS pricing tests, optional)

## File structure
```
index.html            Landing page + price list + booking link
service.html          Booking form (POST to process_service.php)
process_service.php   Server validation + authoritative pricing + confirmation
css/style.css         Professional automotive theme, responsive
js/script.js          Validation, dynamic summary, cost calculator
tests/
  run_tests.py        HTTP end-to-end tests (TESTS 1-20 over HTTP)
  test_php_logic.php  PHP validation/pricing unit tests (CLI)
  test_js_pricing.js  JS calculator tests (Node)
screenshots/          Demonstration screenshots
README.md             This file
.gitignore
```

## Official service price list (single pricing policy)
| Service | Price |
|---|---|
| Engine Oil Change | KSh 2,000 |
| Brake Inspection | KSh 3,000 |
| Wheel Alignment | KSh 3,500 |
| Computer Diagnosis | KSh 2,500 |
| Full Service | KSh 8,000 |
| Vehicle Pickup and Delivery (additional service) | KSh 1,500 |

The first two prices follow this table (not the reversed example in the brief).

## How JavaScript calculates the estimate
`js/script.js` keeps `SERVICE_PRICES` (integer KSh amounts) and
`calculateServiceCost()` sums the values of the currently checked
`services[]` boxes. `updateSelectedServices()` and `updateServiceCount()`
refresh the summary list, count, and total on every `change` so all three
always agree. `pickup_delivery` contributes exactly 1,500 when checked, 0
otherwise. Example: Engine Oil (2,000) + Brake (3,000) + Pickup (1,500) = **KSh 6,500**.

## How PHP independently calculates the estimate
`process_service.php` keeps its own `autocare_price_list()` and never reads
any browser-supplied price/total. After validating every field and rejecting
unknown service identifiers, it sums the server-side prices for the validated
`services[]` array. Tampered `total`/`price` fields are ignored because they
are never read.

## Validation rules
- Name / registration / model: must not be empty (whitespace-only rejected).
- Phone: exactly 10 digits (`^[0-9]{10}$`); letters/punctuation rejected, never auto-fixed.
- Email: valid format (JS regex + PHP `FILTER_VALIDATE_EMAIL`).
- Vehicle type: exactly one of Saloon Car, SUV, Pickup, Van.
- Year: integer 1900–current year.
- Date: valid `YYYY-MM-DD`, today or future (server uses actual system date).
- Services: at least one; every identifier must be a known key.

## Installing PHP
- Ubuntu/Debian with admin rights: `sudo apt install php-cli`, then use `php`
  in all commands below.
- On this Ubuntu computer, PHP is already available at `/tmp/opencode/php`
  (no installation needed), so use `/tmp/opencode/php` exactly as shown below.
  Verify with: `/tmp/opencode/php -v`

## Starting the development server (from the project root)
```bash
cd ~/VEHICLE-SERVICE-BOOKING-AND-COST-ESTIMATION-SYSTEM
/tmp/opencode/php -S localhost:8000
```
(If port 8000 is occupied, use another free port, e.g.
`/tmp/opencode/php -S localhost:8001`, and replace `8000` with `8001`
in the URLs and test commands below.)

## Browser URL
```
http://localhost:8000/index.html
```
Booking form: `http://localhost:8000/service.html`

## Running the automated tests
```bash
# 1. PHP syntax check
/tmp/opencode/php -l process_service.php

# 2. PHP logic tests (no server needed)
/tmp/opencode/php tests/test_php_logic.php

# 3. JS calculator tests (needs node)
node tests/test_js_pricing.js

# 4. End-to-end HTTP tests (needs the dev server running in another terminal)
/tmp/opencode/php -S localhost:8000
python3 tests/run_tests.py --base http://localhost:8000
```

## Sample booking and expected calculation
Jane Wanjiku, 0712345678, SUV “Toyota RAV4”, date 7 days ahead:
Engine Oil Change (2,000) + Vehicle Pickup and Delivery (1,500) = **KSh 3,500**.
The confirmation page lists both services with prices and the total.

## Practical demonstration steps
1. `/tmp/opencode/php -S localhost:8000` from the project root.
2. Open `http://localhost:8000/index.html` → click “Book a Vehicle Service”.
3. Tick services; watch the summary, count, and total update live.
4. Change vehicle type (info box) and set year e.g. 2000 (age warning).
5. Submit with a missing name → validation error; submit valid data → confirmation.
6. Screenshots in `screenshots/` show each stage.

## Known limitations
- No database: bookings are validated and displayed, not stored permanently.
  The confirmation is worded as a “processed service request”, not saved storage.
- `/tmp/opencode/php` is the PHP binary available on this Ubuntu computer
  (PHP 8.5). If `/tmp/opencode/php` is ever missing, reinstall with
  `sudo apt install php-cli` and substitute plain `php` in the commands above.
