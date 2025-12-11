<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$client_prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';
$ben = null; $land = null; $error = '';
$existing_lease = null; $has_payments = 0;
if ($md5 !== ''){
  if ($stmt = mysqli_prepare($con, 'SELECT ben_id, name FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')){
    mysqli_stmt_bind_param($stmt, 's', $md5);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($ben = mysqli_fetch_assoc($res))) {
      $ben_id = (int)$ben['ben_id'];
      // latest LTL land record for this beneficiary
      if ($stmt2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address, extent, extent_unit, extent_ha FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')){
        mysqli_stmt_bind_param($stmt2, 'i', $ben_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        if ($res2 && ($land = mysqli_fetch_assoc($res2))) {
          // Using LTL land directly (FK removed) – pass ltl_land_registration.land_id to lease create
          // Check for existing lease for this land
          if ($stmt3 = mysqli_prepare($con, 'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')){
            $land_id_int = (int)$land['land_id'];
            mysqli_stmt_bind_param($stmt3, 'i', $land_id_int);
            mysqli_stmt_execute($stmt3);
            $res3 = mysqli_stmt_get_result($stmt3);
            if ($res3) { $existing_lease = mysqli_fetch_assoc($res3) ?: null; }
            mysqli_stmt_close($stmt3);
          }
          
          if ($existing_lease) {

                $lease_id_int = (int)$existing_lease['lease_id'];

                  $sql_active = "
                    SELECT 
                    (
                        (SELECT COUNT(*) FROM ltl_write_off WHERE lease_id = $lease_id_int AND status = 1)
                        +
                        (SELECT COUNT(*) FROM ltl_premium_change WHERE lease_id = $lease_id_int AND status = 1)
                    ) AS active_count
                ";

                $resA = mysqli_query($con, $sql_active);
                $rowA = mysqli_fetch_assoc($resA);
                  $has_active_changes = (int)$rowA['active_count'];  // 0 or >0

            // Count payments for guard info
            if ($stmt4 = mysqli_prepare($con, 'SELECT COUNT(*) AS cnt FROM lease_payments WHERE lease_id=?')){
              $lease_id_int = (int)$existing_lease['lease_id'];
              mysqli_stmt_bind_param($stmt4, 'i', $lease_id_int);
              mysqli_stmt_execute($stmt4);
              $res4 = mysqli_stmt_get_result($stmt4);
              if ($res4 && ($r4 = mysqli_fetch_assoc($res4))) { $has_payments = (int)$r4['cnt']; }
              mysqli_stmt_close($stmt4);
            }
          }
        } else {
          $error = 'No land record found. Please fill Land Information first.';
        }
        mysqli_stmt_close($stmt2);
      }
    } else {
      $error = 'Invalid beneficiary';
    }
    mysqli_stmt_close($stmt);
  }
} else {
  $error = 'Missing id';
}
?>
<div class="card">
    <div class="card-header">
        <h5 class="card-header-text">Create New Lease</h5>
    </div>
    <div class="card-block">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
        <?php if ($existing_lease): ?>
        <div class="alert alert-info" role="alert" style="margin-bottom:15px;">
            An existing lease (<?php echo htmlspecialchars($existing_lease['file_number']); ?>) was found for this land.
        </div>
        <?php endif; ?>
        <form id="ltlCreateLeaseForm">
            <?php if ($existing_lease): ?>
            <input type="hidden" name="lease_id" id="ltl_lease_id"
                value="<?php echo (int)$existing_lease['lease_id']; ?>" />
            <input type="hidden" name="lease_type_id1" id="ltl_lease_type_id1"
                value="<?php echo (int)$existing_lease['lease_type_id']; ?>" />
            <input type="hidden" name="has_payments" id="ltl_has_payments" value="<?php echo (int)$has_payments; ?>" />
            <?php endif; ?>
            <input type="hidden" name="land_id" id="ltl_land_id" value="<?php echo (int)$land['land_id']; ?>" />

            <input type="hidden" name="beneficiary_id" id="ltl_beneficiary_id"
                value="<?php echo (int)$land['ben_id']; ?>" />
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>File Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ltl_file_number" name="file_number" required
                            value="<?php echo htmlspecialchars($existing_lease['file_number'] ??  'Generate'); ?>"
                            <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Lease No <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ltl_lease_number" name="lease_number"
                            placeholder="Lease No" required
                            value="<?php echo htmlspecialchars($existing_lease['lease_number'] ?? 'Pending'); ?>"
                            <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Lease Type</label>
                        <select class="form-control" id="ltl_lease_type" name="lease_type_id"
                            <?php echo $existing_lease ? 'disabled' : ''; ?>>
                            <option value="">Select Lease Type</option>
                            <?php
                $lm_res = $con->query("SELECT lease_type_id, lease_type_name, base_rent_percent, economy_rate, economy_valuvation, duration_years, revision_interval, revision_increase_percent, penalty_rate, effective_from, purpose FROM lease_master WHERE status = 1 ORDER BY lease_type_name");
                while ($lm = $lm_res->fetch_assoc()):
                  $lt_id = (int)$lm['lease_type_id'];
                  $lt_name = htmlspecialchars($lm['lease_type_name']);
                  $base_pct = (float)$lm['base_rent_percent'];
                  $eco_rate = isset($lm['economy_rate']) ? (float)$lm['economy_rate'] : 0.0;
                  $eco_val  = isset($lm['economy_valuvation']) ? (float)$lm['economy_valuvation'] : 0.0;
                  $dur = (int)$lm['duration_years'];
                  $premium_times = isset($lm['premium_times']) ? (int)$lm['premium_times'] : 0;
                  $rev_period = (int)$lm['revision_interval'];
                  $rev_pct = (float)$lm['revision_increase_percent'];
                  $pen_rate = isset($lm['penalty_rate']) ? (float)$lm['penalty_rate'] : 0.0;
                  $eff = htmlspecialchars($lm['effective_from'] ?? '');
                  $purpose = htmlspecialchars($lm['purpose'] ?? '');
                  $selected = ($existing_lease && (int)$existing_lease['lease_type_id'] === $lt_id) ? 'selected' : '';
                ?>
                            <option value="<?= $lt_id ?>" <?= $selected ?> data-base-rent-percent="<?= $base_pct ?>"
                                data-premium-times="<?= $premium_times ?>" data-economy-rate="<?= $eco_rate ?>"
                                data-economy-valuvation="<?= $eco_val ?>" data-duration-years="<?= $dur ?>"
                                data-revision-interval="<?= $rev_period ?>"
                                data-revision-increase-percent="<?= $rev_pct ?>" data-purpose="<?= $purpose ?>"
                                data-penalty-rate="<?= $pen_rate ?>" data-effective-from="<?= $eff ?>">
                                <?= $lt_name ?> (Rent: <?= number_format($base_pct,2) ?>%)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Land</label>
                        <input type="text" class="form-control" id="ltl_land_address"
                            value="<?php echo htmlspecialchars($land['land_address'] ?? ('Land #' . (int)$land['land_id'])); ?>"
                            readonly />
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Lessee</label>
                        <input type="text" class="form-control" id="ltl_lessee_name"
                            value="<?php echo htmlspecialchars($ben['name'] ?? ('Beneficiary #' . (int)$land['ben_id'])); ?>"
                            readonly />
                    </div>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Type of Project</label>
                        <input type="text" class="form-control" id="ltl_type_of_project" name="type_of_project"
                            placeholder="Auto fill"
                            value="<?php echo htmlspecialchars($existing_lease['type_of_project'] ?? ''); ?>"
                            readonly />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name of the Project</label>
                        <input type="text" class="form-control" id="ltl_name_of_the_project" name="name_of_the_project"
                            placeholder="Enter project name"
                            value="<?php echo htmlspecialchars($existing_lease['name_of_the_project'] ?? ''); ?>"
                            <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Valuation Amount (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" id="ltl_valuation_amount"
                            name="valuation_amount" required
                            value="<?php echo htmlspecialchars($existing_lease['valuation_amount'] ?? ''); ?>"
                            <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Letter Date *</label>
                        <input type="date" class="form-control" id="ltl_valuation_date" name="valuation_date" value="<?php
                if ($existing_lease && !empty($existing_lease['valuation_date'])) {
                  echo htmlspecialchars(date('Y-m-d', strtotime($existing_lease['valuation_date'])));
                } else {
                  echo '';
                }
              ?>" <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Annual Rent Percentage *</label>
                        <input type="number" step="0.01" class="form-control" id="ltl_annual_rent_percentage"
                            name="annual_rent_percentage" required
                            value="<?php echo htmlspecialchars($existing_lease['annual_rent_percentage'] ?? ''); ?>"
                            <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Initial Annual Rent</label>
                        <input type="text" class="form-control" id="ltl_initial_rent" readonly
                            placeholder="Calculated automatically" />
                    </div>
                </div>
            </div>

            <div class="row" id="ltl_premium_row" style="display:none;">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Premium (Rs.)</label>
                        <input type="text" class="form-control" id="ltl_premium" name="premium" readonly
                            value="<?php echo isset($existing_lease['premium']) ? number_format((float)$existing_lease['premium'],2) : ''; ?>" />
                    </div>
                </div>
                <div class="col-md-9 d-flex align-items-center">
                    <small class="text-muted">Applies only if Start Date is before 2020-01-01. Auto-calculated as 3x
                        Initial Annual Rent.</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Revision Period (Years) *</label>
                        <input type="number" class="form-control" id="ltl_revision_period" name="revision_period"
                            required value="<?php echo htmlspecialchars($existing_lease['revision_period'] ?? ''); ?>"
                            readonly />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Revision Percentage *</label>
                        <input type="number" step="0.01" class="form-control" id="ltl_revision_percentage"
                            name="revision_percentage" required
                            value="<?php echo htmlspecialchars($existing_lease['revision_percentage'] ?? ''); ?>"
                            readonly />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Lease Duration (Years) *</label>
                        <input type="number" class="form-control" id="ltl_duration_years" name="duration_years" required
                            min="1" max="99"
                            value="<?php echo htmlspecialchars($existing_lease['duration_years'] ?? ''); ?>"
                            <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Penalty Rate (%)</label>
                        <input type="number" min="0" max="100" step="0.01" class="form-control" id="ltl_penalty_rate"
                            name="penalty_rate" placeholder="e.g. 10.00"
                            value="<?php echo htmlspecialchars($existing_lease['penalty_rate'] ?? ''); ?>" readonly />
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" class="form-control" id="ltl_start_date" name="start_date" value="<?php
                if ($existing_lease && !empty($existing_lease['start_date'])) {
                  echo htmlspecialchars(date('Y-m-d', strtotime($existing_lease['start_date'])));
                } else {
                  echo '';
                }
              ?>" required <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>End Date *</label>
                        <input type="date" class="form-control" id="ltl_end_date" name="end_date" value="<?php
                if ($existing_lease && !empty($existing_lease['end_date'])) {
                  echo htmlspecialchars(date('Y-m-d', strtotime($existing_lease['end_date'])));
                } else {
                  echo htmlspecialchars(date('Y-m-d', strtotime('+30 years')));
                }
              ?>" required <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Calculated Duration</label>
                        <input type="text" class="form-control" id="ltl_calculated_duration"
                            placeholder="Auto-calculated" readonly />
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Approved Date </label>
                        <input type="date" class="form-control" id="ltl_approved_date" name="approved_date" value="<?php
                if ($existing_lease && !empty($existing_lease['approved_date'])) {
                  echo htmlspecialchars(date('Y-m-d', strtotime($existing_lease['approved_date'])));
                } else {
                  echo '';
                }
              ?>" <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Valuvation Date </label>
                        <input type="date" class="form-control" id="ltl_value_date" name="value_date" value="<?php
                if ($existing_lease && !empty($existing_lease['value_date'])) {
                  echo htmlspecialchars(date('Y-m-d', strtotime($existing_lease['value_date'])));
                } else {
                  echo '';
                }
              ?>" <?php echo $existing_lease ? 'readonly ' : ''; ?> />
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12 text-right">
                    <button type="submit" class="btn btn-success<?php echo $existing_lease ? ' d-none' : '';?>"
                        id="ltl_create_btn"><i class="fa fa-save"></i>
                        <?php echo $existing_lease ? 'Update Lease' : 'Create Lease & Generate Schedule'; ?></button>
                    <!-- <button type="button" class="btn btn-secondary<?php echo $existing_lease ? '' : ' d-none'; ?>" id="ltl_edit_btn"><i class="fa fa-edit"></i> Edit</button> -->
                    <?php if($has_active_changes == 0){?>
                    <?php if (hasPermission(20)): ?>
                    <button type="button" class="btn btn-secondary" id="ltl_edit_btn"> <i class="fa fa-edit"></i> Edit
                    </button>
                    <?php endif; ?>
                    <?php } else {
              echo '<div class="alert alert-warning mb-0"> Cannot edit lease while there are active premium change or write-off requests. Please resolve them first. </div>';
           }?>
                </div>


            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function() {
    if (window.LTLCreateLease && typeof window.LTLCreateLease.init === 'function') {
        window.LTLCreateLease.init();
    } else {
        var s = document.createElement('script');
        s.src = 'ltl_ajax/create_lease_tab.js?_ts=' + Date.now();
        s.onload = function() {
            if (window.LTLCreateLease && typeof window.LTLCreateLease.init === 'function') {
                window.LTLCreateLease.init();
            }
        };
        document.head.appendChild(s);
    }
})();
</script>
<?php if (!$existing_lease): ?>
<script>
$(document).ready(function() {
    $.ajax({
        url: "ajax/get_last_file_number.php", // your PHP file
        method: "GET",
        success: function(prefix) {
            // Set the value to the input box
            $("#ltl_file_number").val(prefix);
        }
    });

});
</script>
<?php endif; ?>