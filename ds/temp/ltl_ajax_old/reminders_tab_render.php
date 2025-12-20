<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$ben = $land = $lease = null; $error='';
if ($md5 !== '') {
  if ($st = mysqli_prepare($con,'SELECT ben_id,name FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
    mysqli_stmt_bind_param($st,'s',$md5); mysqli_stmt_execute($st); $rs = mysqli_stmt_get_result($st);
    if ($rs && ($ben = mysqli_fetch_assoc($rs))) {
      $ben_id = (int)$ben['ben_id'];
      if ($st2 = mysqli_prepare($con,'SELECT land_id, ben_id, land_address FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')){
        mysqli_stmt_bind_param($st2,'i',$ben_id); mysqli_stmt_execute($st2); $r2 = mysqli_stmt_get_result($st2);
        if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
          $land_id = (int)$land['land_id'];
          if ($st3 = mysqli_prepare($con,'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')){
            mysqli_stmt_bind_param($st3,'i',$land_id); mysqli_stmt_execute($st3); $r3 = mysqli_stmt_get_result($st3);
            if ($r3) { $lease = mysqli_fetch_assoc($r3); }
            mysqli_stmt_close($st3);
          }
          if (!$lease) { $error='No lease found for this land.'; }
        } else { $error='No land found for beneficiary.'; }
        mysqli_stmt_close($st2);
      }
    } else { $error='Invalid beneficiary reference.'; }
    mysqli_stmt_close($st);
  }
} else { $error='Missing id parameter.'; }
?>
<div class="card">

    <div class="card-block" style="padding:1rem;">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
        <!-- <div class="mb-3" style="font-size:13px;color:#555;">
            <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number'] ?? '-') ?> &nbsp;|&nbsp;
            <strong>Lessee:</strong> <?= htmlspecialchars($ben['name'] ?? '-') ?> &nbsp;|&nbsp;
            <strong>Land:</strong>
            <?= htmlspecialchars($land['land_address'] ?? ('Land #' . (int)($land['land_id'] ?? 0))) ?>
        </div> -->

        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">01. Recovery Letter</span><br>
                    <small style="color:#666;">Generate recovery letter for outstanding amounts as at selected
                        date.</small>
                </div>
                <div style="margin-top:8px;">
                    <label for="ltl-recovery-date" style="font-size:12px;font-weight:600;margin-right:6px;">As At
                        Date</label>
                    <input type="date" id="ltl-recovery-date" class="form-control form-control-sm d-inline-block"
                        style="width:160px;" value="<?= date('Y-m-d') ?>" />
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ltl-recovery-letter-btn"
                        style="margin-left:6px;">
                        <i class="fa fa-print"></i> Print Letter
                    </button>
                </div>
            </div>
        </div>

        <div class="reminder-item"
            style="border:1px solid #805858ff; background-color:#f8f0f0; padding:12px 14px;border-radius:6px; ">
            <div
                style=" border-radius:6px; background-color:#F0D89C; padding:8px; display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
                <div>
                    <span style="font-weight:600;font-size:14px;">Add Payment Reminders</span><br>
                    <small style="color:#666;">Track sent reminders. Add new entries and mange it .</small>
                </div>
                <div style="margin-top:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap; ">
                    <lable>Letter Type:</lable>
                    <select id="rem-type" class="form-control form-control-sm"
                        style="width:160px;  background-color: #ffffff !important; ">
                        <option value="">Select</option>
                        <!-- <option>Recovery Letter</option> -->
                        <option>Annexure 09</option>
                        <option>Annexure 12A</option>
                        <option>Annexure 12</option>
                    </select>
                    <lable>Letter Date:</lable>
                    <input type="date" id="rem-date" class="form-control form-control-sm" style="width:150px;"
                        value="<?= date('Y-m-d') ?>" />
                    <button type="button" id="rem-add-btn" class="btn btn-success btn-sm" disabled
                        title="Select type & date"><i class="fa fa-plus"></i> Add Record</button>
                </div>
            </div>
            <style>
            .rem-table th,
            .rem-table td {
                font-size: 13px;
            }

            .rem-row-cancelled {
                background: #fde2e2 !important;
            }

            .rem-row-cancelled td {
                color: #842029 !important;
            }
            </style>
            <div class="table-responsive" style="max-height:320px;overflow:auto;">
                <table class="table table-bordered table-sm mb-0 rem-table">
                    <thead class="bg-light">
                        <tr>
                            <th style="width:5%;">SN</th>
                            <th style="width:18%;">Sent Date</th>
                            <th>Reminder Type</th>
                            <th style="width:12%;">Status</th>
                            <th style="width:14%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="rem-body">
                        <tr>
                            <td colspan="5" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <hr>
        <h5>Print Letter</h5>
        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">


            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">02. Annexure 09</span><br>
                    <small style="color:#666;">Generate Annexure 09 letter for outstanding amounts as at selected
                        date.</small>
                </div>
                <div style="margin-top:8px;">
                    <label for="ltl-annexure-09-date" style="font-size:12px;font-weight:600;margin-right:6px;">
                        As At Date
                    </label>
                    <input type="date" id="ltl-annexure-09-date" name="as_at_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />
                    <!-- Tamil Button -->
                    <button type="button" class="btn btn-outline-primary btn-sm" style="margin-left:6px;"
                        onclick="printAnnexure09('TA')">
                        <i class="fa fa-print"></i> Print Tamil Letter
                    </button>
                    <!-- Sinhala Button -->
                    <button type="button" class="btn btn-outline-success btn-sm" style="margin-left:6px;"
                        onclick="printAnnexure09('SN')">
                        <i class="fa fa-print"></i> Print Sinhala Letter
                    </button>
                </div>
            </div>
        </div>

        <script>
        function printAnnexure09(lang) {
            const date = document.getElementById('ltl-annexure-09-date').value;
            const url = `letters/ltl_annexure_09.php?id=<?= urlencode($md5) ?>&as_at_date=${date}&language=${lang}`;
            window.open(url, '_blank');
        }
        </script>




        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">02. Annexure 12</span><br>
                    <small style="color:#666;">Generate Annexure 12 letter for outstanding amounts as at selected
                        date.</small>
                </div>

                <div style="margin-top:8px;">
                    <label for="ltl-annexure-12-date" style="font-size:12px;font-weight:600;margin-right:6px;">
                        As At Date
                    </label>

                    <input type="date" id="ltl-annexure-12-date" name="as_at_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />

                    <!-- Tamil Button -->
                    <button type="button" class="btn btn-outline-primary btn-sm" style="margin-left:6px;"
                        onclick="printAnnexure12('TA')">
                        <i class="fa fa-print"></i> Print Tamil Letter
                    </button>

                    <!-- Sinhala Button -->
                    <button type="button" class="btn btn-outline-success btn-sm" style="margin-left:6px;"
                        onclick="printAnnexure12('SN')">
                        <i class="fa fa-print"></i> Print Sinhala Letter
                    </button>
                </div>
            </div>
        </div>

        <script>
        function printAnnexure12(lang) {
            const date = document.getElementById('ltl-annexure-12-date').value;
            const url = `letters/ltl_annexure_12.php?id=<?= urlencode($md5) ?>&as_at_date=${date}&language=${lang}`;
            window.open(url, '_blank');
        }
        </script>


        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">02. Annexure 12A</span><br>
                    <small style="color:#666;">Generate Annexure 12A letter for outstanding amounts as at selected
                        date.</small>
                </div>

                <div style="margin-top:8px;">
                    <label for="ltl-annexure-12a-date1" style="font-size:12px;font-weight:600;margin-right:6px;">
                        last reminder date
                    </label>
                    <input type="date" id="ltl-annexure-12a-date1" name="last_reminder_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />


                    <label for="ltl-annexure-12a-date" style="font-size:12px;font-weight:600;margin-right:6px;">
                        As At Date
                    </label>
                    <input type="date" id="ltl-annexure-12a-date" name="as_at_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />

                    <!-- Tamil Button -->
                    <button type="button" class="btn btn-outline-primary btn-sm" style="margin-left:6px;"
                        onclick="printAnnexure12A('TA')">
                        <i class="fa fa-print"></i> Print Tamil Letter
                    </button>

                    <!-- Sinhala Button -->
                    <button type="button" class="btn btn-outline-success btn-sm" style="margin-left:6px;"
                        onclick="printAnnexure12A('SN')">
                        <i class="fa fa-print"></i> Print Sinhala Letter
                    </button>
                </div>
            </div>
        </div>

        <script>
        function printAnnexure12A(lang) {
            const date = document.getElementById('ltl-annexure-12a-date').value;
            const lastReminderDate = document.getElementById('ltl-annexure-12a-date1').value;
            const url =
                `letters/ltl_annexure_12A.php?id=<?= urlencode($md5) ?>&as_at_date=${date}&last_reminder_date=${lastReminderDate}&language=${lang}`;
            window.open(url, '_blank');
        }
        </script>






        <script>
        (function() {
            var rDate = document.getElementById('ltl-recovery-date');
            var rBtn = document.getElementById('ltl-recovery-letter-btn');
            var leaseId = <?= isset($lease['lease_id']) ? (int)$lease['lease_id'] : 0 ?>;
            // Removed outstanding calculation – direct print only
            if (rDate) {
                rDate.addEventListener('change', function() {
                    /* placeholder if future logic needed */
                });
            }
            if (rBtn) {
                rBtn.addEventListener('click', function() {
                    if (!rDate.value) {
                        Swal.fire('Validation', 'Select date first', 'warning');
                        return;
                    }
                    var url = 'letters/lease_recovery_letter.php?id=<?= urlencode($md5) ?>&date=' +
                        encodeURIComponent(rDate.value) + '&_ts=' + Date.now();
                    window.open(url, '_blank');
                });
            }
            // Button stays enabled (no outstanding check)

            // ---------------- Reminders Table Logic ----------------
            var LEASE_ID = leaseId;
            var remTypeEl = document.getElementById('rem-type');
            var remDateEl = document.getElementById('rem-date');
            var remAddBtn = document.getElementById('rem-add-btn');
            var remBody = document.getElementById('rem-body');

            function validateRemInputs() {
                if (remTypeEl && remDateEl && remTypeEl.value && remDateEl.value) {
                    remAddBtn.disabled = false;
                    remAddBtn.title = 'Add';
                } else {
                    remAddBtn.disabled = true;
                    remAddBtn.title = 'Select type & date';
                }
            }
            if (remTypeEl) remTypeEl.addEventListener('change', validateRemInputs);
            if (remDateEl) remDateEl.addEventListener('change', validateRemInputs);

            function loadReminders() {
                if (!LEASE_ID) {
                    remBody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">No lease.</td></tr>';
                    return;
                }
                remBody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
                fetch('ltl_ajax/list_reminders.php?lease_id=' + LEASE_ID + '&_ts=' + Date.now())
                    .then(r => r.text())
                    .then(html => {
                        remBody.innerHTML = html;
                        bindCancelReminders();
                        validateRemInputs();
                    })
                    .catch(() => {
                        remBody.innerHTML =
                            '<tr><td colspan="5" class="text-danger text-center">Load failed.</td></tr>';
                    });
            }

            function bindCancelReminders() {
                remBody.querySelectorAll('.rem-cancel-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        if (!id) return;
                        var doCancel = function() {
                            var fd = new URLSearchParams();
                            fd.append('id', id);
                            fetch('ltl_ajax/cancel_reminder.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: fd.toString()
                                })
                                .then(r => r.json())
                                .then(resp => {
                                    if (resp.success) {
                                        loadReminders();
                                    } else {
                                        Swal.fire('Error', resp.message || 'Cancel failed',
                                            'error');
                                    }
                                })
                                .catch(() => Swal.fire('Error', 'Network error', 'error'));
                        };
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Cancel this reminder?',
                                icon: 'warning',
                                showCancelButton: true
                            }).then(function(res) {
                                if (res.isConfirmed) doCancel();
                            });
                        } else {
                            if (confirm('Cancel this reminder?')) doCancel();
                        }
                    });
                });
            }

            if (remAddBtn) {
                remAddBtn.addEventListener('click', function() {
                    if (remAddBtn.disabled) return;
                    var t = remTypeEl.value;
                    var d = remDateEl.value;
                    if (!t || !d) {
                        Swal.fire('Validation', 'Select type and date', 'warning');
                        return;
                    }
                    remAddBtn.disabled = true;
                    remAddBtn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> Saving...';
                    var fd = new URLSearchParams();
                    fd.append('lease_id', LEASE_ID);
                    fd.append('reminders_type', t);
                    fd.append('sent_date', d);
                    fetch('ltl_ajax/add_reminder.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: fd.toString()
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.success) {
                                if (window.Swal) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Added',
                                        timer: 1200,
                                        showConfirmButton: false
                                    });
                                }
                                // reset type only (date stays for convenience)
                                remTypeEl.value = '';
                                validateRemInputs();
                                loadReminders();
                            } else {
                                Swal.fire('Error', resp.message || 'Insert failed', 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Network error', 'error'))
                        .finally(() => {
                            remAddBtn.disabled = false;
                            remAddBtn.innerHTML = '<i class="fa fa-plus"></i> Add';
                        });
                });
            }

            loadReminders();
        })();
        </script>
        <?php endif; ?>
    </div>
</div>