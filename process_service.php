<?php
// AutoCare Garage - booking processor.
//
// Pricing policy (single authoritative server-side list, integers in KSh):
//   engine_oil         => Engine Oil Change            2000
//   brake_inspection   => Brake Inspection             3000
//   wheel_alignment    => Wheel Alignment              3500
//   computer_diagnosis => Computer Diagnosis           2500
//   full_service       => Full Service                 8000
//   pickup_delivery    => Vehicle Pickup and Delivery  1500 (individualization requirement)
//
// The browser total is never trusted. The total is always recalculated here
// from the validated service identifiers.

function autocare_price_list() {
    return array(
        'engine_oil'         => array('label' => 'Engine Oil Change',           'price' => 2000),
        'brake_inspection'   => array('label' => 'Brake Inspection',            'price' => 3000),
        'wheel_alignment'    => array('label' => 'Wheel Alignment',             'price' => 3500),
        'computer_diagnosis' => array('label' => 'Computer Diagnosis',          'price' => 2500),
        'full_service'       => array('label' => 'Full Service',                'price' => 8000),
        'pickup_delivery'    => array('label' => 'Vehicle Pickup and Delivery', 'price' => 1500),
    );
}

function autocare_vehicle_types() {
    return array('Saloon Car', 'SUV', 'Pickup', 'Van');
}

function autocare_format_ksh($amount) {
    return 'KSh ' . number_format((int)$amount);
}

function autocare_esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Validate a booking submission. Returns array($errors, $clean).
// $todayStr is 'Y-m-d' used as the reference "today" (lets tests inject a date).
function autocare_validate_booking($data, $todayStr) {
    $prices = autocare_price_list();
    $allowedTypes = autocare_vehicle_types();
    $errors = array();
    $clean = array();

    $name  = isset($data['customer_name'])    ? trim((string)$data['customer_name'])    : '';
    $phone = isset($data['phone'])            ? (string)$data['phone']                  : '';
    $email = isset($data['email'])            ? trim((string)$data['email'])            : '';
    $reg   = isset($data['vehicle_reg'])      ? trim((string)$data['vehicle_reg'])      : '';
    $type  = isset($data['vehicle_type'])     ? (string)$data['vehicle_type']           : '';
    $model = isset($data['vehicle_model'])    ? trim((string)$data['vehicle_model'])    : '';
    $yearRaw = isset($data['manufacture_year']) ? trim((string)$data['manufacture_year']) : '';
    $dateRaw = isset($data['service_date'])   ? trim((string)$data['service_date'])     : '';
    $services = isset($data['services']) ? $data['services'] : null;

    if ($name === '') {
        $errors[] = 'Customer name is required.';
    }
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Phone number must contain exactly 10 digits (numbers only).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($reg === '') {
        $errors[] = 'Vehicle registration number is required.';
    }
    if ($model === '') {
        $errors[] = 'Vehicle model is required.';
    }
    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Vehicle type must be one of: Saloon Car, SUV, Pickup, Van.';
    }

    $currentYear = (int)date('Y');
    if (!preg_match('/^\d+$/', $yearRaw)) {
        $errors[] = 'Year of manufacture must be a whole number between 1900 and ' . $currentYear . '.';
        $year = null;
    } else {
        $year = (int)$yearRaw;
        if ($year < 1900 || $year > $currentYear) {
            $errors[] = 'Year of manufacture must be between 1900 and ' . $currentYear . '.';
        }
    }

    $today = DateTime::createFromFormat('Y-m-d', $todayStr);
    if (!$today) {
        $today = new DateTime('today');
    } else {
        $today->setTime(0, 0, 0);
    }
    $chosen = DateTime::createFromFormat('Y-m-d', $dateRaw);
    // Strict check: the parsed value must round-trip to the same string.
    if (!$chosen || $chosen->format('Y-m-d') !== $dateRaw) {
        $errors[] = 'Preferred service date must be a valid date (YYYY-MM-DD).';
    } else {
        $chosen->setTime(0, 0, 0);
        if ($chosen < $today) {
            $errors[] = 'Preferred service date must not be in the past.';
        }
    }

    if (!is_array($services) || count($services) === 0) {
        $errors[] = 'Please select at least one service.';
        $services = array();
    } else {
        foreach ($services as $s) {
            if (!is_string($s) || !array_key_exists($s, $prices)) {
                $errors[] = 'Invalid service selected: ' . (is_string($s) ? $s : 'unknown') . '.';
                break;
            }
        }
    }

    $total = 0;
    if (empty($errors) || true) {
        // Total is computed only from known identifiers; unknown ones add nothing
        // but the submission is still rejected above.
        foreach ((array)$services as $s) {
            if (isset($prices[$s])) {
                $total += (int)$prices[$s]['price'];
            }
        }
    }

    $clean = array(
        'customer_name'    => $name,
        'phone'            => $phone,
        'email'            => $email,
        'vehicle_reg'      => $reg,
        'vehicle_type'     => $type,
        'vehicle_model'    => $model,
        'manufacture_year' => isset($year) ? $year : $yearRaw,
        'service_date'     => $dateRaw,
        'services'         => array_values((array)$services),
        'total'            => $total,
    );

    return array($errors, $clean);
}

// ---- Request handling ----
$isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');
$bookingRef = 'AGC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
$errors = array();
$clean = array();
if ($isPost) {
    list($errors, $clean) = autocare_validate_booking($_POST, date('Y-m-d'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $isPost && empty($errors) ? 'Booking Confirmation' : 'Booking Result'; ?> — AutoCare Garage</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="site-header">
    <div class="wrap">
      <div class="brand">AutoCare Garage<small>Vehicle service booking &amp; cost estimation</small></div>
      <nav class="nav" aria-label="Main navigation">
        <a href="index.html">Home</a>
        <a href="service.html">Book Service</a>
      </nav>
    </div>
  </header>

  <main class="wrap">
<?php if (!$isPost): ?>
    <div class="confirmation">
      <h1>Invalid access</h1>
      <p>This page accepts bookings submitted from the booking form using POST.</p>
      <p><a class="btn" href="service.html">Go to the booking form</a></p>
    </div>
<?php elseif (!empty($errors)): ?>
    <div class="confirmation">
      <h1>Booking could not be processed</h1>
      <p>Please correct the following and try again:</p>
      <ul class="error-list">
<?php foreach ($errors as $e): ?>
        <li><?php echo autocare_esc($e); ?></li>
<?php endforeach; ?>
      </ul>
      <p><a class="btn" href="service.html">Back to the booking form</a></p>
    </div>
<?php else: ?>
    <?php $prices = autocare_price_list(); ?>
    <div class="confirmation">
      <h1>SERVICE REQUEST RECEIVED</h1>
      <p>Thank you, <?php echo autocare_esc($clean['customer_name']); ?>. Your service request has been
         processed successfully. Booking reference: <strong><?php echo autocare_esc($bookingRef); ?></strong>
         (booking date: <?php echo autocare_esc(date('Y-m-d')); ?>).</p>
      <table class="details-table">
        <tr><th>Customer name</th><td><?php echo autocare_esc($clean['customer_name']); ?></td></tr>
        <tr><th>Phone number</th><td><?php echo autocare_esc($clean['phone']); ?></td></tr>
        <tr><th>Email address</th><td><?php echo autocare_esc($clean['email']); ?></td></tr>
        <tr><th>Vehicle registration</th><td><?php echo autocare_esc($clean['vehicle_reg']); ?></td></tr>
        <tr><th>Vehicle type</th><td><?php echo autocare_esc($clean['vehicle_type']); ?></td></tr>
        <tr><th>Vehicle model</th><td><?php echo autocare_esc($clean['vehicle_model']); ?></td></tr>
        <tr><th>Year of manufacture</th><td><?php echo autocare_esc($clean['manufacture_year']); ?></td></tr>
        <tr><th>Preferred service date</th><td><?php echo autocare_esc($clean['service_date']); ?></td></tr>
        <tr><th>Booking reference</th><td><?php echo autocare_esc($bookingRef); ?></td></tr>
      </table>
      <h2>Selected services and prices</h2>
      <table class="details-table">
        <thead><tr><th>Service</th><th>Price</th></tr></thead>
        <tbody>
<?php foreach ($clean['services'] as $s): ?>
          <tr><td><?php echo autocare_esc($prices[$s]['label']); ?></td><td><?php echo autocare_esc(autocare_format_ksh($prices[$s]['price'])); ?></td></tr>
<?php endforeach; ?>
          <tr class="total-row"><td>Estimated service cost</td><td><?php echo autocare_esc(autocare_format_ksh($clean['total'])); ?></td></tr>
        </tbody>
      </table>
      <p class="disclaimer">This is a cost estimate, not a final garage invoice. Final charges are
        confirmed after vehicle inspection. This confirmation shows a successfully processed
        service request for demonstration purposes.</p>
      <p><a class="btn btn-secondary" href="index.html">Back to homepage</a>
         <a class="btn" href="service.html">Make another booking</a></p>
    </div>
<?php endif; ?>
  </main>

  <footer class="site-footer">
    <div class="wrap">AutoCare Garage &middot; Estimates in Kenyan shillings.</div>
  </footer>
</body>
</html>
