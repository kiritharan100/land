<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

// Ensure discount column exists to avoid SQL errors if migrations weren't applied
if ($con) {
  $q = mysqli_query($con, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='lease_schedules' AND COLUMN_NAME='discount_apply' LIMIT 1");
  if ($q && mysqli_num_rows($q) === 0) {
    @mysqli_query($con, "ALTER TABLE `lease_schedules` ADD COLUMN `discount_apply` DECIMAL(12,2) NOT NULL DEFAULT 0");
  }
}

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$ben = null; $land = null; $lease = null; $error = '';

if ($md5 !== ''){
  if ($stmt = mysqli_prepare($con, 'SELECT ben_id, name FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')){
    mysqli_stmt_bind_param($stmt, 's', $md5);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($ben = mysqli_fetch_assoc($res))) {
      $ben_id = (int)$ben['ben_id'];
      if ($st2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')){
        mysqli_stmt_bind_param($st2, 'i', $ben_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);
        if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
          $land_id = (int)$land['land_id'];
          if ($st3 = mysqli_prepare($con, 'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')){
            mysqli_stmt_bind_param($st3, 'i', $land_id);
            mysqli_stmt_execute($st3);
            $r3 = mysqli_stmt_get_result($st3);
            if ($r3) { $lease = mysqli_fetch_assoc($r3); }
            mysqli_stmt_close($st3);
          }
          if (!$lease) { $error = 'No lease found for this land.'; }
        } else { $error = 'No land found. Please complete Land Information.'; }
        mysqli_stmt_close($st2);
      }
    } else { $error = 'Invalid beneficiary'; }
    mysqli_stmt_close($stmt);
  }
} else { $error = 'Missing id'; }
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-header-text mb-0">Payments</h5>
    <div>
      <?php if ($lease): ?>
        <?php if (hasPermission(18)): ?>
        <button type="button" class="btn btn-success btn-sm" id="ltl-record-payment-btn"><i class="fa fa-plus"></i> Record Payment</button>
          <?php endif; ?>
        <?php endif; ?>
    </div>
  </div>
  <div class="card-block" style="padding: 1rem;">
    <?php if ($error): ?>
      <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="row mb-2">
        <div class="col-sm-12">
          <div>
            <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number'] ?? '-') ?> &nbsp;|
            <strong>Lessee:</strong> <?= htmlspecialchars($ben['name'] ?? '-') ?> &nbsp;|
            <strong>Land:</strong> <?= htmlspecialchars($land['land_address'] ?? ('Land #' . (int)$land['land_id'])) ?>
          </div>
        </div>
      </div>

      <?php
      // Compute outstanding totals for Rent, Penalty, Premium
      $rent_outstanding = 0.0; $penalty_outstanding = 0.0; $premium_outstanding = 0.0; $total_outstanding = 0.0;
      if ($lease && isset($lease['lease_id'])) {
        $lid = (int)$lease['lease_id'];
        // Query 1 (UPDATED): Rent outstanding should include any schedule that has STARTED (start_date <= today), not only those fully ended.
        // This counts current in-progress schedule rent as due if unpaid.
        $sqlRentDue = "SELECT COALESCE(SUM(annual_amount - COALESCE(discount_apply,0)),0) AS due_rent FROM lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
        $sqlRentPaid = "SELECT COALESCE(SUM(paid_rent),0) AS paid_rent_all FROM lease_schedules WHERE lease_id=?";
        $due_rent = 0.0; $paid_rent_all = 0.0;
        if ($st1 = mysqli_prepare($con,$sqlRentDue)) { mysqli_stmt_bind_param($st1,'i',$lid); mysqli_stmt_execute($st1); $r1 = mysqli_stmt_get_result($st1); if ($r1 && ($rw=mysqli_fetch_assoc($r1))) $due_rent = (float)$rw['due_rent']; mysqli_stmt_close($st1);}        
        if ($st2 = mysqli_prepare($con,$sqlRentPaid)) { mysqli_stmt_bind_param($st2,'i',$lid); mysqli_stmt_execute($st2); $r2 = mysqli_stmt_get_result($st2); if ($r2 && ($rw=mysqli_fetch_assoc($r2))) $paid_rent_all = (float)$rw['paid_rent_all']; mysqli_stmt_close($st2);}        
        $rent_outstanding = max(0, $due_rent - $paid_rent_all);

        // Query 2: Penalty outstanding (due up to today minus ALL penalty paid)
        $sqlPenDue = "SELECT COALESCE(SUM(panalty),0) AS due_penalty FROM lease_schedules WHERE lease_id=? AND end_date <= CURDATE()";
        $sqlPenPaid = "SELECT COALESCE(SUM(panalty_paid),0) AS paid_penalty_all FROM lease_schedules WHERE lease_id=?";
        $due_penalty = 0.0; $paid_penalty_all = 0.0;
        if ($st3 = mysqli_prepare($con,$sqlPenDue)) { mysqli_stmt_bind_param($st3,'i',$lid); mysqli_stmt_execute($st3); $r3 = mysqli_stmt_get_result($st3); if ($r3 && ($rw=mysqli_fetch_assoc($r3))) $due_penalty = (float)$rw['due_penalty']; mysqli_stmt_close($st3);}        
        if ($st4 = mysqli_prepare($con,$sqlPenPaid)) { mysqli_stmt_bind_param($st4,'i',$lid); mysqli_stmt_execute($st4); $r4 = mysqli_stmt_get_result($st4); if ($r4 && ($rw=mysqli_fetch_assoc($r4))) $paid_penalty_all = (float)$rw['paid_penalty_all']; mysqli_stmt_close($st4);}        
        $penalty_outstanding = max(0, $due_penalty - $paid_penalty_all);

        // Query 3 (UPDATED): Premium outstanding should include premiums for any schedule that has STARTED (start_date <= today).
        $sqlPremDue = "SELECT COALESCE(SUM(premium),0) AS due_premium FROM lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
        $sqlPremPaid = "SELECT COALESCE(SUM(premium_paid),0) AS paid_premium_all FROM lease_schedules WHERE lease_id=?";
        $due_premium = 0.0; $paid_premium_all = 0.0;
        if ($st5 = mysqli_prepare($con,$sqlPremDue)) { mysqli_stmt_bind_param($st5,'i',$lid); mysqli_stmt_execute($st5); $r5 = mysqli_stmt_get_result($st5); if ($r5 && ($rw=mysqli_fetch_assoc($r5))) $due_premium = (float)$rw['due_premium']; mysqli_stmt_close($st5);}        
        if ($st6 = mysqli_prepare($con,$sqlPremPaid)) { mysqli_stmt_bind_param($st6,'i',$lid); mysqli_stmt_execute($st6); $r6 = mysqli_stmt_get_result($st6); if ($r6 && ($rw=mysqli_fetch_assoc($r6))) $paid_premium_all = (float)$rw['paid_premium_all']; mysqli_stmt_close($st6);}        
        $premium_outstanding = max(0, $due_premium - $paid_premium_all);

        $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;
      }
      ?>
      <div class="row mb-3">
        <div class="col-sm-12">
          <div class="mb-0" role="alert" style="background:#ffffff;border:2px solid #dc3545;color:#dc3545;font-size:1.15rem;font-weight:600;padding:14px 16px;border-radius:6px;letter-spacing:0.5px;">
            <span style="font-weight:700;text-transform:uppercase;">Outstanding:</span>
            Premium: <?= number_format($premium_outstanding, 2) ?> &nbsp;|
            Penalty: <?= number_format($penalty_outstanding, 2) ?> &nbsp;|
            Rent: <?= number_format($rent_outstanding, 2) ?> &nbsp;|
            <span style="font-weight:800;">Total: <?= number_format($total_outstanding, 2) ?></span>
          </div>
        </div>
      </div>

      <?php
      // fetch payments with allocated year
      $payments = [];
      if ($stP = mysqli_prepare($con, 'SELECT lp.*, YEAR(lp.payment_date) AS payment_year, ls.schedule_year AS allocated_year FROM lease_payments lp LEFT JOIN lease_schedules ls ON lp.schedule_id = ls.schedule_id WHERE lp.lease_id=? ORDER BY lp.payment_date ASC, lp.payment_id ASC')){
        mysqli_stmt_bind_param($stP, 'i', $lease['lease_id']);
        mysqli_stmt_execute($stP);
        $rp = mysqli_stmt_get_result($stP);
        if ($rp) { $payments = mysqli_fetch_all($rp, MYSQLI_ASSOC); }
        mysqli_stmt_close($stP);
      }
      ?>
      <style>
        /* Payments table formatting */
        .ltl-payments-table { width: 100%; }
        .ltl-payments-table th.col-date, .ltl-payments-table td.col-date { width: 9.5rem; }
        .ltl-payments-table th.col-amt, .ltl-payments-table td.col-amt { width: 10rem; text-align: right; }
        .ltl-payments-table tr.cancelled-payment-row { background:#fde2e2; }
        .ltl-payments-table tr.cancelled-payment-row td { color:#842029; }
        .cancelled-label { display:inline-block; padding:2px 8px; background:#dc3545; color:#fff; font-size:12px; border-radius:4px; }
      </style>
      <div class="table-responsive">
        <table class="table table-bordered table-sm ltl-payments-table">
          <thead class="bg-light">
            <tr>
              <th class="col-date">Date</th>
              <th>Reference No</th>
              <th>Method</th>
              <th class="col-amt">Rent Paid</th>
              <th class="col-amt">Penalty Paid</th>
              <th class="col-amt">Premium Paid</th>
              <th class="col-amt">Discount</th>
              <th class="col-amt">Total Payment</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$payments): ?>
              <tr><td colspan="9" class="text-center">No payments recorded</td></tr>
            <?php else: foreach ($payments as $p): ?>
              <?php $isCancelled = isset($p['status']) && (string)$p['status'] === '0'; ?>
              <tr class="<?= $isCancelled ? 'cancelled-payment-row' : '' ?>">
                <td class="col-date"><?= htmlspecialchars($p['payment_date']) ?></td>
                <td><?= htmlspecialchars($p['reference_number']) ?></td>
                <td><?= htmlspecialchars(ucfirst($p['payment_method'])) ?></td>
                <td class="col-amt"><?= number_format((float)($p['rent_paid'] ?? 0), 2) ?></td>
                <td class="col-amt"><?= number_format((float)($p['panalty_paid'] ?? 0), 2) ?></td>
                <td class="col-amt"><?= number_format((float)($p['premium_paid'] ?? 0), 2) ?></td>
                <td class="col-amt"><?= number_format((float)($p['discount_apply'] ?? 0), 2) ?></td>
                <td class="col-amt"><?= number_format((float)$p['amount'], 2) ?></td>
                <td>
                  <?php if ($isCancelled): ?>
                    <span class="cancelled-label">Cancelled</span>
                  <?php else: ?>
                    <?php if (hasPermission(19)): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm ltl-cancel-payment-btn"
                            data-payment-id="<?= (int)($p['payment_id'] ?? $p['id'] ?? 0) ?>"
                            data-receipt="<?= htmlspecialchars($p['receipt_number']) ?>"
                            data-date="<?= htmlspecialchars($p['payment_date']) ?>"
                            data-amount="<?= htmlspecialchars($p['amount']) ?>">
                      <i class="fa fa-times"></i> Cancel
                    </button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Payment Modal -->
      <div class="modal fade" id="ltl-payment-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Record Payment</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="ltl-payment-modal-body">
              <div style="text-align:center;padding:16px">
                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <script>
        (function(){
          var btn = document.getElementById('ltl-record-payment-btn');
          // Recovery letter moved to Reminders tab; related controls removed here.
          if (btn){
            btn.addEventListener('click', function(){
              var body = document.getElementById('ltl-payment-modal-body');
              if (body){ body.innerHTML = '<div style="text-align:center;padding:16px"><img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" /></div>'; }
              // Load the payment form from ltl_ajax, which internally posts to existing ajax endpoints
              var url = 'ltl_ajax/payment_form.php?lease_id=<?= (int)($lease['lease_id'] ?? 0) ?>&_ts=' + Date.now();
              fetch(url)
                .then(function(r){ return r.text(); })
                .then(function(html){
                  body.innerHTML = html;
                  try {
                    // Bind events for the injected form without relying on inline scripts
                    var form = body.querySelector('#paymentForm');
                    var calcBtn = body.querySelector('#ltl-calc-outstanding-btn');
                    if (calcBtn){
                      calcBtn.addEventListener('click', function(){
                        var paymentDateEl = body.querySelector('#payment_date');
                        var paymentDate = paymentDateEl ? paymentDateEl.value : '';
                        if (!paymentDate) { Swal.fire('Validation', 'Please select payment date first', 'warning'); return; }
                        var resultEl = body.querySelector('#calculationResult');
                        if (resultEl){ resultEl.style.display='block'; resultEl.textContent = 'Calculating...'; }
                        var calcUrl = 'ajax/calculate_outstanding.php?lease_id=<?= (int)($lease['lease_id'] ?? 0) ?>&payment_date=' + encodeURIComponent(paymentDate);
                        fetch(calcUrl)
                          .then(function(r){ return r.json(); })
                          .then(function(resp){
                            if (resp && resp.success){
                              if (resultEl){
                                resultEl.innerHTML = '<strong>Outstanding Calculation:</strong><br>'+
                                  'Rent: LKR ' + Number(resp.outstanding_rent).toLocaleString('en-US', {minimumFractionDigits:2}) + '<br>'+
                                  'Penalty: LKR ' + Number(resp.penalty_amount).toLocaleString('en-US', {minimumFractionDigits:2}) + '<br>'+
                                  '<strong>Total Due: LKR ' + Number(resp.total_due).toLocaleString('en-US', {minimumFractionDigits:2}) + '</strong>';
                              }
                              var amountEl = body.querySelector('#amount');
                              if (amountEl){ amountEl.value = resp.total_due; }
                            } else {
                              if (resultEl){ resultEl.textContent = 'Error calculating outstanding amount'; }
                            }
                          })
                          .catch(function(){ if (resultEl){ resultEl.textContent = 'Error calculating outstanding amount'; } });
                      });
                    }
                    if (form){
                      form.addEventListener('submit', function(ev){
                        ev.preventDefault();
                        Swal.fire({ title:'Recording Payment', text:'Please wait...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
                        var fd = new URLSearchParams(new FormData(form));
                        fetch(form.getAttribute('action') || 'ajax/record_payment_simple.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
                          .then(function(r){ return r.text(); })
                          .then(function(txt){
                            var resp;
                            try { resp = JSON.parse(txt); }
                            catch(e){
                              Swal.close();
                              Swal.fire({ title:'Error!', text: 'Server response was not JSON: ' + (txt ? txt.substring(0,300) : 'Empty response'), icon:'error' });
                              return;
                            }
                            Swal.close();
                            if (resp && resp.success){
                              Swal.fire({ title:'Success!', text: resp.message || 'Payment recorded', icon:'success' }).then(function(){
                                if (window.jQuery) { jQuery('#ltl-payment-modal').modal('hide'); }
                                if (typeof window.dispatchEvent === 'function') { window.dispatchEvent(new Event('ltl:payments-updated')); }
                              });
                            } else {
                              Swal.fire({ title:'Error!', text: (resp && resp.message) || 'Failed to record payment', icon:'error' });
                            }
                          })
                          .catch(function(){ Swal.close(); Swal.fire({ title:'Error!', text:'Network error occurred.', icon:'error' }); });
                      });
                    }
                  } catch(e) { /* no-op */ }
                })
                .catch(function(){ body.innerHTML = '<div class="text-danger">Failed to load payment form.</div>'; });
              if (window.jQuery) { jQuery('#ltl-payment-modal').modal('show'); }
            });
          }
          // Recovery letter logic removed; now handled inside Reminders tab.
          // Note: outer page listens for 'ltl:payments-updated' and reloads the Payment tab

          // Cancellation handler
          document.querySelectorAll('.ltl-cancel-payment-btn').forEach(function(b){
            b.addEventListener('click', function(){
              var pid = this.getAttribute('data-payment-id');
              var receipt = this.getAttribute('data-receipt') || '';
              var pdate = this.getAttribute('data-date') || '';
              var amount = this.getAttribute('data-amount') || '';
              if (!pid || pid === '0') { Swal.fire('Error', 'Invalid payment reference', 'error'); return; }
              Swal.fire({
                title: 'Cancel this payment?',
                html: 'Receipt: <strong>' + receipt + '</strong><br>Date: ' + pdate + '<br>Amount: LKR ' + Number(amount).toLocaleString('en-US', {minimumFractionDigits:2}),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel',
                cancelButtonText: 'No'
              }).then(function(res){
                if (!res.isConfirmed) return;
                Swal.fire({title:'Cancelling...', text:'Please wait', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
                var fd = new URLSearchParams(); fd.set('payment_id', pid);
                fetch('ajax/cancel_payment.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
                  .then(function(r){ return r.json(); })
                  .then(function(resp){
                    Swal.close();
                    if (resp && resp.success){
                      Swal.fire('Cancelled', resp.message || 'Payment cancelled and penalties recalculated', 'success').then(function(){
                        if (typeof window.dispatchEvent === 'function') { window.dispatchEvent(new Event('ltl:payments-updated')); }
                      });
                    } else {
                      Swal.fire('Error', (resp && resp.message) || 'Failed to cancel payment', 'error');
                    }
                  })
                  .catch(function(){ Swal.close(); Swal.fire('Error', 'Network error', 'error'); });
              });
            });
          });

          // Regenerate penalty handler
         
        })();
      </script>
    <?php endif; ?>
  </div>
</div>
