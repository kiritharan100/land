  <?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$ben = null; $land = null; $lease = null; $error = '';
$land_ltl = null; // Land info from Land Information tab (ltl_land_registration)

if ($md5 !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT ben_id, name, contact_person, address, district, ds_division_id, ds_division_text, gn_division_id, gn_division_text, nic_reg_no, nationality, telephone, email, language FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int)$ben['ben_id'];
            // Try: latest lease directly by beneficiary
            if ($stL = mysqli_prepare($con, 'SELECT * FROM leases WHERE beneficiary_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')) {
              mysqli_stmt_bind_param($stL, 'i', $ben_id);
              mysqli_stmt_execute($stL);
              $rL = mysqli_stmt_get_result($stL);
              if ($rL) { $lease = mysqli_fetch_assoc($rL); }
              mysqli_stmt_close($stL);
            }

            // If no lease found, fallback: latest land by beneficiary then find lease by land_id
            if (!$lease) {
              if ($st2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address, lcg_area FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                  $land_id = (int)$land['land_id'];
                  if ($st3 = mysqli_prepare($con, 'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')) {
                    mysqli_stmt_bind_param($st3, 'i', $land_id);
                    mysqli_stmt_execute($st3);
                    $r3 = mysqli_stmt_get_result($st3);
                    if ($r3) { $lease = mysqli_fetch_assoc($r3); }
                    mysqli_stmt_close($st3);
                  }
                }
                mysqli_stmt_close($st2);
              }
            }

            // Land details fallback: if we have a lease with land_id, get details from main land_registration
            if ($lease && isset($lease['land_id'])) {
              $lid_for_land = (int)$lease['land_id'];
            //   if ($st4 = mysqli_prepare($con, 'SELECT land_id, address AS land_address, lcg_area FROM land_registration WHERE land_id=? LIMIT 1')) {
            //     mysqli_stmt_bind_param($st4, 'i', $lid_for_land);
            //     mysqli_stmt_execute($st4);
            //     $r4 = mysqli_stmt_get_result($st4);
            //     if ($r4) { $land_from_main = mysqli_fetch_assoc($r4); }
            //     mysqli_stmt_close($st4);
            //     if (!empty($land_from_main)) { $land = $land_from_main; }
            //   }
              // If still no land, fallback to ltl_land_registration by land_id
              if (!$land) {
                // if ($st5 = mysqli_prepare($con, 'SELECT land_id, land_address, lcg_area FROM ltl_land_registration WHERE land_id=? LIMIT 1')) {
                //   mysqli_stmt_bind_param($st5, 'i', $lid_for_land);
                //   mysqli_stmt_execute($st5);
                //   $r5 = mysqli_stmt_get_result($st5);
                //   if ($r5) { $land = mysqli_fetch_assoc($r5); }
                //   mysqli_stmt_close($st5);
                // }
              }
            }

            // Always try to fetch detailed land info as captured in Land Information tab (ltl_land_registration)
            if ($st6 = mysqli_prepare($con, 'SELECT l.land_id, l.ds_id, cr.client_name AS ds_name, l.gn_id, gn.gn_name AS gn_name, l.land_address, l.sketch_plan_no, l.plc_plan_no, l.survey_plan_no, l.extent_ha
                             FROM ltl_land_registration l
                             LEFT JOIN client_registration cr ON l.ds_id = cr.c_id
                             LEFT JOIN gn_division gn ON l.gn_id = gn.gn_id
                             WHERE l.ben_id = ?
                             ORDER BY l.land_id DESC LIMIT 1')) {
              mysqli_stmt_bind_param($st6, 'i', $ben_id);
              mysqli_stmt_execute($st6);
              $r6 = mysqli_stmt_get_result($st6);
              if ($r6) { $land_ltl = mysqli_fetch_assoc($r6); }
              mysqli_stmt_close($st6);
            }

            // Error messaging: only if neither lease nor land info exists
            if (!$lease && !$land_ltl) { $error = 'Land information pending.'; }
        } else { $error = 'Invalid beneficiary reference.'; }
        mysqli_stmt_close($stmt);
    }
} else { $error = 'Missing beneficiary id.'; }

// Outstanding calculations (reuse logic from payment tab)
$rent_outstanding = 0.0; $penalty_outstanding = 0.0; $premium_outstanding = 0.0; $total_outstanding = 0.0;
$next_schedule = null; $next_payment_amount = 0.0; $next_discount_amount = 0.0; $next_discount_deadline = '';
$schedule_stats = ['total'=>0,'completed'=>0];
$payment_stats = ['count'=>0,'total_paid'=>0.0];

if ($lease && isset($lease['lease_id'])) {
    $lid = (int)$lease['lease_id'];
    // Rent due (end_date <= today) minus all paid
    $sqlRentDue = "SELECT COALESCE(SUM(annual_amount - COALESCE(discount_apply,0)),0) AS due_rent FROM lease_schedules WHERE lease_id=? AND end_date <= CURDATE()";
    $sqlRentPaid = "SELECT COALESCE(SUM(paid_rent),0) AS paid_rent_all FROM lease_schedules WHERE lease_id=?";
    if ($st = mysqli_prepare($con,$sqlRentDue)) { mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); $r=mysqli_stmt_get_result($st); if($r && ($rw=mysqli_fetch_assoc($r))) $due_rent=(float)$rw['due_rent']; mysqli_stmt_close($st);} else { $due_rent = 0; }
    if ($st = mysqli_prepare($con,$sqlRentPaid)) { mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); $r=mysqli_stmt_get_result($st); if($r && ($rw=mysqli_fetch_assoc($r))) $paid_rent_all=(float)$rw['paid_rent_all']; mysqli_stmt_close($st);} else { $paid_rent_all = 0; }
    $rent_outstanding = max(0, ($due_rent ?? 0) - ($paid_rent_all ?? 0));

    // Penalty outstanding
    $sqlPenDue = "SELECT COALESCE(SUM(panalty),0) AS due_penalty FROM lease_schedules WHERE lease_id=? AND end_date <= CURDATE()";
    $sqlPenPaid = "SELECT COALESCE(SUM(panalty_paid),0) AS paid_penalty_all FROM lease_schedules WHERE lease_id=?";
    if ($st = mysqli_prepare($con,$sqlPenDue)) { mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); $r=mysqli_stmt_get_result($st); if($r && ($rw=mysqli_fetch_assoc($r))) $due_penalty=(float)$rw['due_penalty']; mysqli_stmt_close($st);} else { $due_penalty = 0; }
    if ($st = mysqli_prepare($con,$sqlPenPaid)) { mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); $r=mysqli_stmt_get_result($st); if($r && ($rw=mysqli_fetch_assoc($r))) $paid_penalty_all=(float)$rw['paid_penalty_all']; mysqli_stmt_close($st);} else { $paid_penalty_all = 0; }
    $penalty_outstanding = max(0, ($due_penalty ?? 0) - ($paid_penalty_all ?? 0));

    // Premium outstanding
    $sqlPremDue = "SELECT COALESCE(SUM(premium),0) AS due_premium FROM lease_schedules WHERE lease_id=? AND end_date <= CURDATE()";
    $sqlPremPaid = "SELECT COALESCE(SUM(premium_paid),0) AS paid_premium_all FROM lease_schedules WHERE lease_id=?";
    if ($st = mysqli_prepare($con,$sqlPremDue)) { mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); $r=mysqli_stmt_get_result($st); if($r && ($rw=mysqli_fetch_assoc($r))) $due_premium=(float)$rw['due_premium']; mysqli_stmt_close($st);} else { $due_premium = 0; }
    if ($st = mysqli_prepare($con,$sqlPremPaid)) { mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); $r=mysqli_stmt_get_result($st); if($r && ($rw=mysqli_fetch_assoc($r))) $paid_premium_all=(float)$rw['paid_premium_all']; mysqli_stmt_close($st);} else { $paid_premium_all = 0; }
    $premium_outstanding = max(0, ($due_premium ?? 0) - ($paid_premium_all ?? 0));

    $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;

    // Schedule stats & next schedule detection
    $schedules = [];
    if ($st = mysqli_prepare($con, 'SELECT schedule_id, schedule_year, start_date, end_date, annual_amount, discount_apply, paid_rent, panalty, panalty_paid, premium, premium_paid FROM lease_schedules WHERE lease_id=? ORDER BY schedule_year')) {
        mysqli_stmt_bind_param($st,'i',$lid);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs) { $schedules = mysqli_fetch_all($rs, MYSQLI_ASSOC); }
        mysqli_stmt_close($st);
    }
    $schedule_stats['total'] = count($schedules);
    $today = date('Y-m-d');
    foreach ($schedules as $sc) {
        if ($sc['end_date'] <= $today) { $schedule_stats['completed']++; }
        $rent_due_effective = (float)$sc['annual_amount'] - (float)$sc['discount_apply'];
        $rent_remaining = $rent_due_effective - (float)$sc['paid_rent'];
        $pen_remaining = (float)$sc['panalty'] - (float)$sc['panalty_paid'];
        $prem_remaining = (float)$sc['premium'] - (float)$sc['premium_paid'];
        if ($rent_remaining > 0 || $pen_remaining > 0 || $prem_remaining > 0) {
            $next_schedule = $sc;
            $next_payment_amount = max(0,$rent_remaining) + max(0,$pen_remaining) + max(0,$prem_remaining);
            $next_discount_amount = (float)$sc['discount_apply'];
            if (!empty($sc['start_date'])) { $next_discount_deadline = date('Y-m-d', strtotime($sc['start_date'] . ' +30 days')); }
            break; // first upcoming unpaid schedule
        }
    }

    // Payment stats
    if ($st = mysqli_prepare($con, 'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total_paid FROM lease_payments WHERE lease_id=?')) {
        mysqli_stmt_bind_param($st,'i',$lid);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
        if ($r && ($rw = mysqli_fetch_assoc($r))) {
            $payment_stats['count'] = (int)$rw['cnt'];
            $payment_stats['total_paid'] = (float)$rw['total_paid'];
        }
        mysqli_stmt_close($st);
    }
}
?>
  <div class="card" id="lease-dashboard-card">
      <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-header-text mb-0">Lease Dashboard</h5>
          <div>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="lease-dashboard-refresh-btn"><i
                      class="fa fa-sync"></i> Refresh</button>
          </div>
      </div>
      <div class="card-block" style="padding:1rem;">
          <?php if ($error): ?>
          <div class="alert alert-info mb-3"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <?php if ($lease): ?>
          <div class="mb-3"
              style="background:#fff;border:2px solid #dc3545;color:#dc3545;font-size:1.05rem;font-weight:600;padding:10px 12px;border-radius:6px;letter-spacing:0.5px;">
              <span style="font-weight:700;text-transform:uppercase;">Outstanding:</span>
              Premium: <?= number_format($premium_outstanding,2) ?> &nbsp;|
              Penalty: <?= number_format($penalty_outstanding,2) ?> &nbsp;|
              Rent: <?= number_format($rent_outstanding,2) ?> &nbsp;|
              <span style="font-weight:800;">Total: <?= number_format($total_outstanding,2) ?></span>
          </div>

          <div class="row">
              <div class="col-md-6 mb-3">
                  <div class="card h-100">
                      <div class="card-header p-2"><strong>Beneficiary Information</strong></div>
                      <div class="card-block p-2" style="font-size:0.9rem;">
                          <?php if ($ben): ?>
                          <?php
                  $contact_name = !empty($ben['contact_person']) ? $ben['contact_person'] : $ben['name'];
                  $ben_address = $ben['address'] ?? '';
                  $ben_district = $ben['district'] ?? '';
                  $ben_gn = $ben['gn_division_text'] ?? '';
                  // If gn_division_text empty but id present, try lookup
                  if (empty($ben_gn) && !empty($ben['gn_division_id'])) {
                    $gstmt = mysqli_prepare($con, 'SELECT gn_name FROM gn_division WHERE gn_id = ? LIMIT 1');
                    if ($gstmt) {
                      mysqli_stmt_bind_param($gstmt, 'i', $ben['gn_division_id']);
                      mysqli_stmt_execute($gstmt);
                      $gr = mysqli_stmt_get_result($gstmt);
                      if ($gr && ($grw = mysqli_fetch_assoc($gr))) { $ben_gn = $grw['gn_name']; }
                      mysqli_stmt_close($gstmt);
                    }
                  }
                ?>
                          <div><strong>Name (Contact):</strong> <?= htmlspecialchars($contact_name) ?></div>
                          <div><strong>Address:</strong> <?= htmlspecialchars($ben_address ?: '-') ?></div>
                          <div><strong>District:</strong> <?= htmlspecialchars($ben_district ?: '-') ?></div>
                          <div><strong>GN Division:</strong> <?= htmlspecialchars($ben_gn ?: '-') ?></div>
                          <div><strong>Telephone:</strong> <?= htmlspecialchars($ben['telephone'] ?? '-') ?></div>
                          <div><strong>Nationality:</strong> <?= htmlspecialchars($ben['nationality'] ?? '-') ?></div>
                          <div><strong>Email:</strong> <?= htmlspecialchars($ben['email'] ?? '-') ?></div>
                          <div><strong>Language:</strong> <?= htmlspecialchars($ben['language'] ?? '-') ?></div>
                          <?php else: ?>
                          <div class="text-muted">Beneficiary information not available.</div>
                          <?php endif; ?>
                      </div>
                  </div>
              </div>
              <div class="col-md-6 mb-3">
                  <div class="card h-100">
                      <div class="card-header p-2"><strong>Outstanding Composition</strong></div>
                      <div class="card-block p-2">
                          <div id="outstanding-pie" style="height:210px;"></div>
                      </div>
                  </div>
              </div>
          </div>

          <div class="row">
              <div class="col-md-12 mb-3">
                  <div class="card h-100">
                      <div class="card-header p-2"><strong>Land Information</strong></div>
                      <div class="card-block p-2" style="font-size:0.85rem;">
                          <?php if ($land_ltl): ?>
                          <div><strong>DS Division:</strong> <?= htmlspecialchars($land_ltl['ds_name'] ?? '-') ?></div>
                          <div><strong>GN Division:</strong> <?= htmlspecialchars($land_ltl['gn_name'] ?? '-') ?></div>
                          <div><strong>Land Address:</strong> <?= htmlspecialchars($land_ltl['land_address'] ?? '-') ?>
                          </div>
                          <div><strong>Sketch Plan No:</strong>
                              <?= htmlspecialchars($land_ltl['sketch_plan_no'] ?? '-') ?></div>
                          <div><strong>PLC Plan No:</strong> <?= htmlspecialchars($land_ltl['plc_plan_no'] ?? '-') ?>
                          </div>
                          <div><strong>Survey Plan No:</strong>
                              <?= htmlspecialchars($land_ltl['survey_plan_no'] ?? '-') ?></div>
                          <div><strong>Hectares:</strong> <?= htmlspecialchars($land_ltl['extent_ha'] ?? '-') ?></div>
                          <?php else: ?>
                          <div class="text-muted">Land information pending.</div>
                          <?php endif; ?>
                      </div>
                  </div>
              </div>
          </div>
          <?php else: ?>
          <div class="alert alert-warning">Lease not yet created. Outstanding and payment visuals will appear once a
              lease is generated.</div>
          <?php if ($land_ltl): ?>
          <div class="card mt-3">
              <div class="card-header p-2"><strong>Land Information</strong></div>
              <div class="card-block p-2" style="font-size:0.85rem;">
                  <div><strong>DS Division:</strong> <?= htmlspecialchars($land_ltl['ds_name'] ?? '-') ?></div>
                  <div><strong>GN Division:</strong> <?= htmlspecialchars($land_ltl['gn_name'] ?? '-') ?></div>
                  <div><strong>Land Address:</strong> <?= htmlspecialchars($land_ltl['land_address'] ?? '-') ?></div>
                  <div><strong>Sketch Plan No:</strong> <?= htmlspecialchars($land_ltl['sketch_plan_no'] ?? '-') ?>
                  </div>
                  <div><strong>PLC Plan No:</strong> <?= htmlspecialchars($land_ltl['plc_plan_no'] ?? '-') ?></div>
                  <div><strong>Survey Plan No:</strong> <?= htmlspecialchars($land_ltl['survey_plan_no'] ?? '-') ?>
                  </div>
                  <div><strong>Hectares:</strong> <?= htmlspecialchars($land_ltl['extent_ha'] ?? '-') ?></div>
              </div>
          </div>
          <?php endif; ?>
          <?php endif; ?>
      </div>
  </div>

  <script>
(function() {
    // Refresh button reloads dashboard via parent loader function
    var ref = document.getElementById('lease-dashboard-refresh-btn');
    if (ref) {
        ref.addEventListener('click', function() {
            if (typeof window.loadLeaseDashboard === 'function') {
                window.loadLeaseDashboard(true);
            }
        });
    }
    // Pie chart
    if (typeof Highcharts === 'undefined') {
        var hc = document.createElement('script');
        hc.src = 'https://code.highcharts.com/highcharts.js';
        hc.onload = renderChart;
        hc.onerror = renderChart; // attempt anyway
        document.head.appendChild(hc);
    } else {
        renderChart();
    }

    function renderChart() {
        if (typeof Highcharts === 'undefined') return; // give up silently
        Highcharts.chart('outstanding-pie', {
            chart: {
                type: 'pie',
                backgroundColor: 'transparent'
            },
            title: {
                text: null
            },
            tooltip: {
                pointFormat: '<b>{point.y:,.2f}</b>'
            },
            credits: {
                enabled: false
            },
            plotOptions: {
                pie: {
                    dataLabels: {
                        enabled: true,
                        format: '{point.name}: {point.y:,.0f}'
                    }
                }
            },
            series: [{
                name: 'Outstanding',
                colorByPoint: true,
                data: [{
                        name: 'Premium',
                        y: <?= json_encode($premium_outstanding) ?>
                    },
                    {
                        name: 'Penalty',
                        y: <?= json_encode($penalty_outstanding) ?>
                    },
                    {
                        name: 'Rent',
                        y: <?= json_encode($rent_outstanding) ?>
                    }
                ]
            }]
        });
    }
})();
  </script>