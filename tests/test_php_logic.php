<?php
// CLI tests for PHP validation + pricing (TESTS 1-7, 9-20).
// Run: php tests/test_php_logic.php
// Includes process_service.php with output buffering so its functions can be tested directly.

$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
include __DIR__ . '/../process_service.php';
ob_end_clean();

$failures = 0;
function check($name, $cond, $detail = '') {
    global $failures;
    if ($cond) { echo "PASS $name\n"; }
    else { echo "FAIL $name $detail\n"; $failures++; }
}

function validBooking($overrides = array()) {
    $future = date('Y-m-d', strtotime('+7 days'));
    $base = array(
        'customer_name' => 'Jane Wanjiku',
        'phone' => '0712345678',
        'email' => 'jane@example.com',
        'vehicle_reg' => 'KDA 123B',
        'vehicle_type' => 'SUV',
        'vehicle_model' => 'Toyota RAV4',
        'manufacture_year' => '2018',
        'service_date' => $future,
        'services' => array('engine_oil'),
    );
    return array_merge($base, $overrides);
}

$today = date('Y-m-d');

// Pricing TESTS 1-7 + 20 (via validate_booking total)
$priceCases = array(
    array('TEST 1: engine_oil => 2000', array('engine_oil'), 2000),
    array('TEST 2: brake_inspection => 3000', array('brake_inspection'), 3000),
    array('TEST 3: wheel_alignment => 3500', array('wheel_alignment'), 3500),
    array('TEST 4: computer_diagnosis => 2500', array('computer_diagnosis'), 2500),
    array('TEST 5: full_service => 8000', array('full_service'), 8000),
    array('TEST 6: pickup_delivery => 1500', array('pickup_delivery'), 1500),
    array('TEST 7: oil+brake+pickup => 6500', array('engine_oil','brake_inspection','pickup_delivery'), 6500),
    array('TEST 20: full+pickup => 9500', array('full_service','pickup_delivery'), 9500),
);
foreach ($priceCases as $c) {
    list($errs, $clean) = autocare_validate_booking(validBooking(array('services' => $c[1])), $today);
    check($c[0], empty($errs) && $clean['total'] === $c[2], 'got total=' . $clean['total'] . ' errors=' . implode(';', $errs));
}

// TEST 9: missing name rejected
list($errs,) = autocare_validate_booking(validBooking(array('customer_name' => '   ')), $today);
check('TEST 9: empty name rejected', count($errs) > 0);

// TEST 10: phone too short rejected
list($errs,) = autocare_validate_booking(validBooking(array('phone' => '071234567')), $today);
check('TEST 10: 9-digit phone rejected', count($errs) > 0);

// TEST 11: phone too long rejected
list($errs,) = autocare_validate_booking(validBooking(array('phone' => '07123456789')), $today);
check('TEST 11: 11-digit phone rejected', count($errs) > 0);

// phone with letters rejected (extra)
list($errs,) = autocare_validate_booking(validBooking(array('phone' => '07123abc78')), $today);
check('TEST 11b: phone with letters rejected', count($errs) > 0);

// TEST 12: invalid email rejected
list($errs,) = autocare_validate_booking(validBooking(array('email' => 'not-an-email')), $today);
check('TEST 12: invalid email rejected', count($errs) > 0);

// TEST 13: no services rejected
list($errs,) = autocare_validate_booking(validBooking(array('services' => array())), $today);
check('TEST 13: no services rejected', count($errs) > 0);

// TEST 14: invalid year rejected (too old + future + non-numeric)
list($errs,) = autocare_validate_booking(validBooking(array('manufacture_year' => '1899')), $today);
check('TEST 14a: year 1899 rejected', count($errs) > 0);
list($errs,) = autocare_validate_booking(validBooking(array('manufacture_year' => (string)(date('Y') + 1))), $today);
check('TEST 14b: future year rejected', count($errs) > 0);
list($errs,) = autocare_validate_booking(validBooking(array('manufacture_year' => 'abc')), $today);
check('TEST 14c: non-numeric year rejected', count($errs) > 0);

// TEST 15: past date rejected
list($errs,) = autocare_validate_booking(validBooking(array('service_date' => date('Y-m-d', strtotime('-1 day')))), $today);
check('TEST 15: past date rejected', count($errs) > 0);

// TEST 16: unexpected service rejected
list($errs,) = autocare_validate_booking(validBooking(array('services' => array('free_tuning'))), $today);
check('TEST 16: unexpected service rejected', count($errs) > 0);

// TEST 17: valid booking accepted
list($errs, $clean) = autocare_validate_booking(validBooking(array('services' => array('engine_oil','pickup_delivery'))), $today);
check('TEST 17: valid booking accepted', empty($errs) && $clean['total'] === 3500, 'errors=' . implode(';', $errs));

// TEST 18: tampered totals are ignored (no total field trusted) - send extra fields
$tampered = validBooking(array('services' => array('engine_oil')));
$tampered['total'] = '1';
$tampered['estimatedTotal'] = '1';
$tampered['price'] = '1';
list($errs, $clean) = autocare_validate_booking($tampered, $today);
check('TEST 18: tampered price ignored, total still 2000', empty($errs) && $clean['total'] === 2000);

// TEST 19: HTML in name is preserved raw in clean data (escaping happens at render)
$evil = validBooking(array('customer_name' => '<script>alert(1)</script>'));
list($errs, $clean) = autocare_validate_booking($evil, $today);
check('TEST 19: markup accepted as text, escaped at render', empty($errs) && strpos(autocare_esc($clean['customer_name']), '<script>') === false);

// TEST 20 covered above; explicit pickup charge
list($errs, $clean) = autocare_validate_booking(validBooking(array('services' => array('pickup_delivery'))), $today);
check('TEST 20: pickup_delivery totals 1500', empty($errs) && $clean['total'] === 1500);

if ($failures > 0) { echo "$failures FAILURE(S)\n"; exit(1); }
echo "All PHP logic tests passed.\n";
