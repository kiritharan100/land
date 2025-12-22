<?php 
include 'header.php';
checkPermission(12);

$orderOptions = [
  'month' => 'Month',
  'file_number' => 'File Number',
  'next_month' => 'Only Next Month',
  'outstanding' => 'Outstanding'
];

$selectedOrder = '';
if (isset($_GET['order_filter']) && array_key_exists($_GET['order_filter'], $orderOptions)) {
  $selectedOrder = $_GET['order_filter'];
} elseif (isset($_COOKIE['ltl_order_filter']) && array_key_exists($_COOKIE['ltl_order_filter'], $orderOptions)) {
  $selectedOrder = $_COOKIE['ltl_order_filter'];
}

$remindExpr = "DATE(
    DATE_ADD(
        MAKEDATE(YEAR(CURDATE()), 1)
        + INTERVAL (MONTH(l.start_date) - 1) MONTH
        + INTERVAL (DAY(l.start_date) - 1) DAY
        - INTERVAL 1 MONTH,
        INTERVAL (YEAR(CURDATE()) - YEAR(
            MAKEDATE(YEAR(CURDATE()), 1)
            + INTERVAL (MONTH(l.start_date) - 1) MONTH
            + INTERVAL (DAY(l.start_date) - 1) DAY
            - INTERVAL 1 MONTH
        )) YEAR
    )
)";


 ?>

<style>
/* Wrapper */
.checkbox-wrap {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
    font-family: Arial, sans-serif;
    font-size: 14px;
}

/* Hide real checkbox */
.checkbox-wrap input[type="checkbox"] {
    display: none;
}

/* Fake box */
.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #28a745;
    border-radius: 4px;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.2s ease;
}

/* Tick when checked */
.checkbox-wrap input[type="checkbox"]:checked+.checkbox-custom {
    background-color: #28a745;
    color: #fff;
}

/* Tick icon */
.checkbox-custom::after {
    content: "✔";
    font-size: 14px;
    display: none;
}

.checkbox-wrap input[type="checkbox"]:checked+.checkbox-custom::after {
    display: block;
}
</style>


<div class="content-wrapper">
    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4>Long Term Lease Register </h4>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" align="right">
                <!-- create a dropdown filter for order by option  -->


                <div class="card-header" align="right">


                    <form id="orderFilterForm" method="get" class="form-inline justify-content-end">

                        <label for="month_filter" class="mr-2 mb-0">Filter by remind Month</label>
                        <select id="month_filter" class="form-control form-control-sm" style="min-width:150px;">
                            <option value="">All</option>
                            <option value="2025-01">January</option>
                            <option value="2025-02">February</option>
                            <option value="2025-03">March</option>
                            <option value="2025-04">April</option>
                            <option value="2025-05">May</option>
                            <option value="2025-06">June</option>
                            <option value="2025-07">July</option>
                            <option value="2025-08">August</option>
                            <option value="2025-09">September</option>
                            <option value="2025-10">October</option>
                            <option value="2025-11">November</option>
                            <option value="2025-12">December</option>
                        </select>






                        <?php if (hasPermission(13)): ?>
                        <button type='button' class="btn btn-primary float-right" data-toggle="modal"
                            data-target="#benModal">
                            Add Lease Application
                        </button>
                        <?php endif; ?>
                    </form>
                </div>


                <hr>

            </div>

            <div class="card-body">
                <table id="benTable" class="table table-bordered table-striped"
                    Style='padding-right: 15px; padding-left: 15px;'>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <!-- <th>Address</th> -->
                            <th>Telephone</th>
                            <!-- <th>Language</th> -->
                            <th>Land Address</th>

                            <th>GN Division</th>
                            <th>Type</th>
                            <th>Lease Number</th>
                            <th>File Number</th>
                            <th>Remind Date</th>
                            <th class="text-right"> Outstanding</th>

                            <th width='120'>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
            // Load beneficiaries with limited columns and related land + latest lease
            $rows = [];
            if (isset($con)) {
              $sql = "SELECT 
                        b.ben_id,
                        b.md5_ben_id,
                        b.name,
                        b.address,
                        b.telephone,
                        b.language,
                        lr.land_address,
                        lr.extent_ha,
                        lr.developed_status,
                        COALESCE(gn.gn_name, b.gn_division_text) AS gn_name,
                        l.file_number,
                        l.start_date,
                        l.lease_id,
                        l.type_of_project,
                        l.lease_number,
 {$remindExpr} AS remind_date,
                        (
                          SELECT COUNT(*)
                          FROM ltl_reminders lr
                          WHERE lr.lease_id = l.lease_id
                            AND lr.reminders_type = 'Annexure 09'
                            AND lr.status = 1
                            AND lr.sent_date BETWEEN DATE_SUB({$remindExpr}, INTERVAL 45 DAY)
                                AND STR_TO_DATE(CONCAT(YEAR({$remindExpr}), '-12-31'), '%Y-%m-%d')
                        ) AS annexure09_sent
                        
                      FROM beneficiaries b
                      LEFT JOIN ltl_land_registration lr ON lr.ben_id = b.ben_id
                      LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id
                      LEFT JOIN (
                        SELECT l2.beneficiary_id, l2.file_number, l2.start_date, l2.lease_id ,l2.type_of_project,l2.lease_number
                        FROM leases l2
                        INNER JOIN (
                          SELECT beneficiary_id, MAX(lease_id) AS max_id
                          FROM leases
                          GROUP BY beneficiary_id
                        ) lm ON lm.beneficiary_id = l2.beneficiary_id AND lm.max_id = l2.lease_id
                      ) l ON l.beneficiary_id = b.ben_id
                      WHERE b.location_id = ?
                      ORDER BY b.ben_id ASC";
              if ($stmt = mysqli_prepare($con, $sql)) {
                mysqli_stmt_bind_param($stmt, 'i', $location_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($r = mysqli_fetch_assoc($result)) { $rows[] = $r; }
                mysqli_stmt_close($stmt);
              }
            }
$count = 1;
            // Helper to compute outstanding for a lease (rent+penalty+premium up to today)
            function compute_outstanding($con, $lease_id){
              $out = ['total'=>0.0];
              if (!$lease_id) return $out;
              $lid = (int)$lease_id;
              $rent_due = $rent_paid = 0; $pen_due = $pen_paid = 0; $prem_due = $prem_paid = 0;
              // Rent
              if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(annual_amount - COALESCE(discount_apply,0)),0) FROM lease_schedules WHERE lease_id=? AND start_date <= CURDATE()")){
                mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$rent_due); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
              }
              if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(paid_rent),0) FROM lease_schedules WHERE lease_id=?")){
                mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$rent_paid); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
              }
              // Penalty
              if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(panalty),0) FROM lease_schedules WHERE lease_id=? AND start_date <= CURDATE()")){
                mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$pen_due); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
              }
              if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(panalty_paid),0) FROM lease_schedules WHERE lease_id=?")){
                mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$pen_paid); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
              }
              // Premium
              if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(premium),0) FROM lease_schedules WHERE lease_id=? AND start_date <= CURDATE()")){
                mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$prem_due); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
              }
              if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(premium_paid),0) FROM lease_schedules WHERE lease_id=?")){
                mysqli_stmt_bind_param($st,'i',$lid); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$prem_paid); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
              }
              $rent_outstanding = max(0,$rent_due - $rent_paid);
              $pen_outstanding  = max(0,$pen_due - $pen_paid);
              $prem_outstanding = max(0,$prem_due - $prem_paid);
              $out['total'] = $rent_outstanding + $pen_outstanding + $prem_outstanding;
              return $out;
            }

            foreach ($rows as $r):
              $out = compute_outstanding($con, $r['lease_id'] ?? null);
              $reminderSent = !empty($r['annexure09_sent']);
              $reminderClass = $reminderSent ? ' class="bg-success text-white"' : '';
            ?>
                        <tr>
                            <td><?= $count ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <!-- <td><?= htmlspecialchars($r['address'] ?? '') ?></td> -->
                            <td><?= htmlspecialchars($r['telephone'] ?? '') ?></td>
                            <!-- <td><?= htmlspecialchars($r['language'] ?? '') ?></td> -->
                            <td>
                                <?php if (!empty($r['land_address'])): ?>
                                <?= htmlspecialchars($r['land_address']) ?>
                                <?php else: ?>
                                <span class="badge badge-pill badge-danger">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td><?= htmlspecialchars($r['gn_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['type_of_project'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['lease_number'] ?? '') ?></td>
                            <td align='center'>
                                <?php if (!empty($r['file_number'])): ?>
                                <span class="badge badge-info"><?= htmlspecialchars($r['file_number']) ?></span>
                                <?php else: ?>
                                <span class="badge badge-pill badge-danger">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td align='center' <?= $reminderClass ?>>
                                <?php if (!empty($r['remind_date'])): ?>
                                <?= htmlspecialchars($r['remind_date']) ?>
                                <?php else: ?>
                                <!-- <span class="badge badge-pill badge-danger">Pending</span> -->
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= number_format($out['total'],2) ?></td>

                            <td>
                                <a class="btn btn-sm btn-info"
                                    href="long_term_lease_open.php?id=<?= urlencode($r['md5_ben_id'] ?? '') ?>">
                                    <i class="fa fa-folder-open"></i> Open
                                </a>
                                <?php if (hasPermission(13)): ?>
                                <button type="button" class="btn btn-sm btn-primary editBen"
                                    data-id="<?= (int)$r['ben_id'] ?>">
                                    <i class="fa fa-edit"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php $count++;  endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="benModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="benForm" class='processing_form'>
                <div class="modal-header">
                    <h5 class="modal-title">Add Lease Application</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="ben_id" id="ben_id">
                    <input type="hidden" name="location_id" id="location_id" value='<?= $location_id ?>'>

                    <div class="form-row">

                        <div class="form-group col-md-4">
                            <label>Name in English </label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Name in Tamil </label>
                            <input type="text" name="name_tamil" id="name_tamil" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Name in Sinhala </label>
                            <input type="text" name="name_sinhala" id="name_sinhala" class="form-control">
                        </div>


                        <label class="checkbox-wrap">
                            <input type="checkbox" id="is_individual" name="is_individual" value="1" checked>
                            <span class="checkbox-custom"></span>
                            Individual
                        </label>
                        <div class="form-group" id="contact_person_group" style="display:none;">
                            <label>Contact Person (for Institutions)</label>
                            <input type="text" name="contact_person" id="contact_person" class="form-control">
                        </div>
                    </div>






                    <!-- <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_individual" name="is_individual" value="1" checked>
            <label class="form-check-label" for="is_individual">Individual</label>
          </div> -->



                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Address - English</label>
                            <textarea name="address" id="address" class="form-control"></textarea>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Address - Tamil</label>
                            <textarea name="address_tamil" id="address_tamil" class="form-control"></textarea>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Address - Sinhala</label>
                            <textarea name="address_sinhala" id="address_sinhala" class="form-control"></textarea>
                        </div>

                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>District</label>
                            <select name="district" id="district" class="form-control">
                                <option value="Ampara">Ampara</option>
                                <option value="Anuradhapura">Anuradhapura</option>
                                <option value="Badulla">Badulla</option>
                                <option value="Batticaloa">Batticaloa</option>
                                <option value="Colombo">Colombo</option>
                                <option value="Galle">Galle</option>
                                <option value="Gampaha">Gampaha</option>
                                <option value="Hambantota">Hambantota</option>
                                <option value="Jaffna">Jaffna</option>
                                <option value="Kalutara">Kalutara</option>
                                <option value="Kandy">Kandy</option>
                                <option value="Kegalle">Kegalle</option>
                                <option value="Kilinochchi">Kilinochchi</option>
                                <option value="Kurunegala">Kurunegala</option>
                                <option value="Mannar">Mannar</option>
                                <option value="Matale">Matale</option>
                                <option value="Matara">Matara</option>
                                <option value="Monaragala">Monaragala</option>
                                <option value="Mullaitivu">Mullaitivu</option>
                                <option value="Nuwara Eliya">Nuwara Eliya</option>
                                <option value="Polonnaruwa">Polonnaruwa</option>
                                <option value="Puttalam">Puttalam</option>
                                <option value="Ratnapura">Ratnapura</option>
                                <option value="Trincomalee" selected>Trincomalee</option>
                                <option value="Vavuniya">Vavuniya</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>


                        <div class="form-group col-md-4">
                            <label>DS Division</label>
                            <select name="ds_division_id" id="ds_division_id" class="form-control">
                                <option value="">Select DS Division</option>
                                <?php
                  $dsq = mysqli_query($con,"SELECT c_id, client_name FROM client_registration ORDER BY client_name");
                  while($ds = mysqli_fetch_assoc($dsq)){
                      echo "<option value='{$ds['c_id']}'>{$ds['client_name']}</option>";
                  }
                ?>
                            </select>
                            <input type="text" name="ds_division_text" id="ds_division_text" class="form-control mt-2"
                                placeholder="Enter DS Division (if not in list)" style="display:none;">
                        </div>
                        <div class="form-group col-md-4">
                            <label>GN Division</label>
                            <select name="gn_division_id" id="gn_division_id" class="form-control">
                                <option value="">Select GN Division</option>
                            </select>
                            <input type="text" name="gn_division_text" id="gn_division_text" class="form-control mt-2"
                                placeholder="Enter GN Division (if not in list)" style="display:none;">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>NIC Number / Registration No</label>
                            <input type="text" name="nic_reg_no" id="nic_reg_no" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" id="dob" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Nationality</label>
                            <select name="nationality" id="nationality" class="form-control" required>
                                <option value="">-- Select Nationality --</option>
                                <option value="Sinhalese">Sinhalese</option>
                                <option value="Sri Lankan Tamil">Sri Lankan Tamil</option>
                                <option value="Sri Lankan Muslims">Sri Lankan Muslims</option>
                                <option value="Indian Tamil">Indian Tamil</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>


                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Language</label>
                            <select name="language" id="language" class="form-control">
                                <option value="English">English</option>
                                <option value="Tamil">Tamil</option>
                                <option value="Sinhala">Sinhala</option>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label>Telephone Number</label>
                            <input type="text" name="telephone" id="telephone" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Email Address</label>
                            <input type="email" name="email" id="email" class="form-control">
                        </div>
                    </div>






                    <div align="right">
                        <button type="submit" class="btn btn-success processing" style="margin-top:20px;"> <i
                                class="bi bi-save"></i> Save</button>

                        <button type="button" class="btn btn-danger" data-dismiss="modal" style="margin-top:20px;">
                            <i class="fa fa-times"></i> Close
                        </button>
                    </div>

                </div>
                <div class="modal-footer">





                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
$(document).ready(function() {

    // Show contact person if not individual
    $('#is_individual').change(function() {
        if ($(this).is(':checked')) {
            $('#contact_person_group').hide();
        } else {
            $('#contact_person_group').show();
        }
    });

    // Show text input if district != Trincomalee
    // Initialize Select2 for District, DS Division, GN Division
    $('#district').select2({
        width: '100%',
        dropdownParent: $('#benModal')
    });
    $('#ds_division_id').select2({
        width: '100%',
        dropdownParent: $('#benModal')
    });
    $('#gn_division_id').select2({
        width: '100%',
        dropdownParent: $('#benModal')
    });

    // District change logic


    var currentDistrict = "<?php echo isset($current_district) ? $current_district : ''; ?>";
    var currentDSDivision =
        "<?php echo isset($current_DS_division) ? $current_DS_division : (isset($client_name) ? $client_name : ''); ?>";
    var isEditing = false;

    function setDefaultDSDivision() {
        if (currentDSDivision && !isEditing) {
            $('#ds_division_id option').each(function() {
                if ($(this).text().trim() === currentDSDivision.trim()) {
                    $('#ds_division_id').val($(this).val()).trigger('change');
                }
            });
        }
    }

    if (currentDistrict && !isEditing) {
        $('#district').val(currentDistrict).trigger('change');
        setTimeout(setDefaultDSDivision, 400);
    }

    $('#benModal').on('shown.bs.modal', function() {
        if (currentDistrict && !isEditing) {
            $('#district').val(currentDistrict).trigger('change');
            setTimeout(setDefaultDSDivision, 400);
        }
    });

    $('#district').change(function() {
        var district = $(this).val();
        if (district == 'Trincomalee' || district == 'Batticaloa' || district == 'Ampara') {
            // Load DS Division options via AJAX
            $.get('ajax/get_ds_divisions.php', {
                district: district
            }, function(data) {
                $('#ds_division_id').html(data);
                $('#ds_division_id').select2({
                    width: '100%',
                    dropdownParent: $('#benModal')
                });
            });
            $('#ds_division_id').prop('disabled', false).prop('required', true);
            $('#ds_division_text').hide();
            $('#gn_division_id').prop('disabled', false).prop('required', true);
            $('#gn_division_text').hide();
        } else {
            // For other districts allow typing a new DS / GN value via taggable select2
            $('#ds_division_text').hide();
            $('#gn_division_text').hide();

            // Initialize ds select as taggable to allow free text input
            $('#ds_division_id').prop('disabled', false).prop('required', false);
            try {
                $('#ds_division_id').select2('destroy');
            } catch (e) {}
            $('#ds_division_id').select2({
                width: '100%',
                dropdownParent: $('#benModal'),
                tags: true,
                tokenSeparators: [',']
            });

            // Initialize gn select as taggable
            $('#gn_division_id').prop('disabled', false).prop('required', false);
            try {
                $('#gn_division_id').select2('destroy');
            } catch (e) {}
            $('#gn_division_id').select2({
                width: '100%',
                dropdownParent: $('#benModal'),
                tags: true,
                tokenSeparators: [',']
            });
        }
    });

    // Load GN divisions when DS selected
    $('#ds_division_id').change(function() {
        var c_id = $(this).val();
        if (c_id) {
            // If c_id is numeric (existing DS id) then load GN divisions from server
            if (!isNaN(c_id)) {
                $.get("ajax/get_gn_divisions.php", {
                    c_id: c_id
                }, function(data) {
                    $("#gn_division_id").html(data);
                    // Re-init select2 for the replaced HTML
                    $('#gn_division_id').select2({
                        width: '100%',
                        dropdownParent: $('#benModal')
                    });
                });
                $('#gn_division_id').show();
                $('#gn_division_text').hide();
            } else {
                // Non-numeric DS (tag/text) was selected — don't fetch GN list (would overwrite any tag)
                // Initialize GN as taggable so user can enter free-text GN values
                try {
                    $('#gn_division_id').select2('destroy');
                } catch (e) {}
                $('#gn_division_id').select2({
                    width: '100%',
                    dropdownParent: $('#benModal'),
                    tags: true,
                    tokenSeparators: [',']
                });
                $('#gn_division_id').show();
                $('#gn_division_text').hide();
            }
        } else {
            $('#gn_division_id').hide();
            $('#gn_division_text').show();
        }
    });

    // DataTable
    // var table = $('#benTable').DataTable({
    //     processing: false,
    //     serverSide: false,
    //     pageLength: 25,
    //     order: [
    //         [0, 'asc']
    //     ]
    // });

    // Save Beneficiary
    $("#benForm").on("submit", function(e) {
        e.preventDefault();
        // Build payload explicitly to ensure ds_division_text / gn_division_text are set correctly
        var payload = {};
        // Basic fields from form
        payload.ben_id = $('#ben_id').val();
        payload.location_id = $('#location_id').val();
        payload.name = $('#name').val();
        payload.name_sinhala = $('#name_sinhala').val();
        payload.name_tamil = $('#name_tamil').val();
        payload.is_individual = $('#is_individual').is(':checked') ? 1 : 0;
        payload.contact_person = $('#contact_person').val();
        payload.address = $('#address').val();
        payload.address_sinhala = $('#address_sinhala').val();
        payload.address_tamil = $('#address_tamil').val();
        payload.district = $('#district').val();
        payload.nic_reg_no = $('#nic_reg_no').val();
        payload.dob = $('#dob').val();
        payload.nationality = $('#nationality').val();
        payload.telephone = $('#telephone').val();
        payload.email = $('#email').val();
        payload.language = $('#language').val() || 'English';

        // DS handling
        var dsVal = $('#ds_division_id').val();
        if (Array.isArray(dsVal)) dsVal = dsVal.join(', ');
        // If text input is visible and has content, prefer it
        if ($('#ds_division_text').is(':visible') && $('#ds_division_text').val().trim()) {
            payload.ds_division_text = $('#ds_division_text').val().trim();
            payload.ds_division_id = '';
        } else if (dsVal && dsVal.length && isNaN(dsVal)) {
            // taggable select returned non-numeric text
            payload.ds_division_text = dsVal;
            payload.ds_division_id = '';
        } else {
            payload.ds_division_text = $('#ds_division_text').val().trim();
            payload.ds_division_id = dsVal || '';
        }

        // GN handling
        var gnVal = $('#gn_division_id').val();
        if (Array.isArray(gnVal)) gnVal = gnVal.join(', ');
        if ($('#gn_division_text').is(':visible') && $('#gn_division_text').val().trim()) {
            payload.gn_division_text = $('#gn_division_text').val().trim();
            payload.gn_division_id = '';
        } else if (gnVal && gnVal.length && isNaN(gnVal)) {
            payload.gn_division_text = gnVal;
            payload.gn_division_id = '';
        } else {
            payload.gn_division_text = $('#gn_division_text').val().trim();
            payload.gn_division_id = gnVal || '';
        }

        // Debug: show payload in console so dev can verify gn_division_text / ds_division_text
        if (window.console && window.console.debug) {
            console.debug('Beneficiary payload:', payload);
        }

        // Post to server expecting JSON
        $.ajax({
            url: "ajax/save_ltl_beneficiary.php",
            method: "POST",
            data: payload,
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: resp.message || 'Beneficiary saved'
                        });
                    } else {
                        alert(resp.message || 'Beneficiary saved');
                    }
                    $("#benModal").modal("hide");
                    $("#benForm")[0].reset();
                    $("#ben_id").val("");
                    // Reload the page to reflect latest table data (client-side rendering)
                    location.reload();
                } else {
                    var msg = (resp && resp.message) ? resp.message : (resp ? JSON
                        .stringify(resp) : 'Unknown error');
                    if (window.Swal) {
                        Swal.fire('Error', msg, 'error');
                    } else {
                        alert('Error: ' + msg);
                    }
                }
            },
            error: function(xhr) {
                console.error('Save Beneficiary error:', xhr.responseText);
                var err = 'Server error';
                try {
                    err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON
                        .message : xhr.statusText;
                } catch (e) {}
                if (window.Swal) {
                    Swal.fire('Error', err, 'error');
                } else {
                    alert('Error: ' + err);
                }
            }
        });
    });

    // Edit Beneficiary
    $(document).on("click", ".editBen", function() {
        isEditing = true;
        var id = $(this).data("id");
        $.ajax({
            url: "ajax/get_ltl_beneficiary.php",
            method: "POST",
            data: {
                ben_id: id
            },
            dataType: "json",
            success: function(data) {
                $("#ben_id").val(data.ben_id);
                $("#name").val(data.name);
                $("#name_tamil").val(data.name_tamil);
                $("#name_sinhala").val(data.name_sinhala);
                if (data.is_individual == 1) {
                    $("#is_individual").prop("checked", true);
                    $("#contact_person_group").hide();
                } else {
                    $("#is_individual").prop("checked", false);
                    $("#contact_person_group").show();
                    $("#contact_person").val(data.contact_person);
                }
                $("#address").val(data.address);
                $("#address_tamil").val(data.address_tamil);
                $("#address_sinhala").val(data.address_sinhala);
                $("#district").val(data.district).trigger("change");
                setTimeout(function() {
                    if (data.ds_division_text) {
                        // Prefill taggable select with text value so user can edit it as a tag
                        try {
                            $('#ds_division_id').select2('destroy');
                        } catch (e) {}
                        $('#ds_division_id').select2({
                            width: '100%',
                            dropdownParent: $('#benModal'),
                            tags: true,
                            tokenSeparators: [',']
                        });
                        var val = data.ds_division_text;
                        // create an option and select it
                        var newOption = new Option(val, val, true, true);
                        $('#ds_division_id').append(newOption).trigger('change');
                        $('#ds_division_text').hide();
                        // If GN is also text (no id), hide GN select2 and show text input
                        if (data.gn_division_text) {
                            try {
                                $('#gn_division_id').select2('destroy');
                            } catch (e) {}
                            $('#gn_division_id').select2({
                                width: '100%',
                                dropdownParent: $('#benModal'),
                                tags: true,
                                tokenSeparators: [',']
                            });
                            var gval = data.gn_division_text;
                            var gOption = new Option(gval, gval, true, true);
                            $('#gn_division_id').append(gOption).trigger('change');
                            $('#gn_division_text').hide();
                        } else if (data.gn_division_id) {
                            $('#gn_division_id').val(data.gn_division_id).trigger(
                                'change');
                            $('#gn_division_id').show();
                            $('#gn_division_text').hide();
                        } else {
                            $('#gn_division_id').hide();
                            $('#gn_division_text').show();
                        }
                    } else if (data.ds_division_id) {
                        $('#ds_division_id').val(data.ds_division_id).trigger(
                            'change');
                        $('#ds_division_id').show();
                        $('#ds_division_text').hide();
                        // GN Division logic
                        $.get("ajax/get_gn_divisions.php", {
                            c_id: data.ds_division_id
                        }, function(gnData) {
                            $('#gn_division_id').html(gnData);
                            $('#gn_division_id').select2({
                                width: '100%',
                                dropdownParent: $('#benModal')
                            });
                            if (data.gn_division_id) {
                                $('#gn_division_id').val(data
                                    .gn_division_id).trigger('change');
                                $('#gn_division_id').show();
                                $('#gn_division_text').hide();
                            } else {
                                $('#gn_division_text').val(data
                                    .gn_division_text);
                                $('#gn_division_id').hide();
                                $('#gn_division_text').show();
                            }
                        });
                    } else {
                        // Both DS and GN are text only
                        $('#ds_division_id').prop('disabled', true);
                        $('#ds_division_text').show();
                        $('#gn_division_id').prop('disabled', true);
                        $('#gn_division_text').show();
                        $('#ds_division_text').val(data.ds_division_text);
                        $('#gn_division_text').val(data.gn_division_text);
                    }
                }, 400);
                $("#nic_reg_no").val(data.nic_reg_no);
                $("#dob").val(data.dob);
                $("#nationality").val(data.nationality);
                $("#telephone").val(data.telephone);
                $("#email").val(data.email);
                $("#language").val(data.language || 'English');
                $("#benModal").modal("show");
            }
        });
    });

    $('#benModal').on('hidden.bs.modal', function() {
        isEditing = false;
        // Clear form fields when modal is closed
        $('#benForm')[0].reset();
        $('#ben_id').val('');
        $('#language').val('English');
        $('#ds_division_id').val('').trigger('change');
        $('#ds_division_text').val('');
        $('#gn_division_id').val('').trigger('change');
        $('#gn_division_text').val('');
        $('#contact_person').val('');
        $('#address').val('');
        $('#name').val('');
        $('#nic_reg_no').val('');
        $('#dob').val('');
        $('#nationality').val('');
        $('#telephone').val('');
        $('#email').val('');
        // Hide text inputs and show select2 for new
        $('#ds_division_text').hide();
        $('#ds_division_id').show();
        $('#gn_division_text').hide();
        $('#gn_division_id').show();
        $('#contact_person_group').hide();
        // Enable and reset Save button text
        var button = document.querySelector('button.processing');
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-save"></i> Save';
        }
    });




    var $orderSelect = $('#order_filter');
    if ($orderSelect.length) {
        $orderSelect.on('change', function() {
            var value = this.value || '';
            var maxAgeSeconds = 30 * 24 * 60 * 60;
            document.cookie = 'ltl_order_filter=' + encodeURIComponent(value) + ';path=/;max-age=' +
                maxAgeSeconds;
            this.form.submit();
        });
    }



    // Your DataTable initialization
    var table = $('#benTable').DataTable({
        processing: false,
        serverSide: false,
        pageLength: 25,
        order: [
            [0, 'asc']
        ]
    });

    // ---- MONTH FILTER START ----

    // Extend DataTables search for month filter
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var selectedMonth = $('#month_filter').val(); // 2025-01, 2025-02 etc.
        var remindDate = data[8]; // "Remind Date" column index

        if (selectedMonth === '') {
            return true; // show all
        }

        // match year-month only: 2025-01
        if (remindDate.startsWith(selectedMonth)) {
            return true;
        }

        return false;
    });

    $('#month_filter').on('change', function() {
        table.draw();
    });

    // ---- MONTH FILTER END ----

});
</script>