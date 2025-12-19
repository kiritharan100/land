 <?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
$md5 = $_GET['id'] ?? '';
$lease = null; $ben = null; $land = null; $error = '';

// ----------------------------
// FETCH BENEFICIARY → LAND → LEASE
// ----------------------------
if ($md5 !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT ben_id, name FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int)$ben['ben_id'];

            if ($st2 = mysqli_prepare($con, 'SELECT land_id FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);

                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {

                    $land_id = (int)$land['land_id'];

                    if ($st3 = mysqli_prepare($con, 'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($st3, 'i', $land_id);
                        mysqli_stmt_execute($st3);
                        $r3 = mysqli_stmt_get_result($st3);
                        if ($r3) $lease = mysqli_fetch_assoc($r3);
                        mysqli_stmt_close($st3);
                    }

                    if (!$lease) $error = 'No lease found for this land.';
                } else {
                    $error = 'No land found. Please complete Land Information.';
                }
                mysqli_stmt_close($st2);
            }
        } else {
            $error = 'Invalid beneficiary';
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $error = 'Missing id';
}

$lease_id = $lease['lease_id'] ?? 0;
?>

<div>
<?php if ($error): ?>
    <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
<?php else: ?>

<style>
.fv-table th, .fv-table td { font-size: 13px; }
.fv-row-cancelled { background:#fde2e2; }
.fv-row-cancelled td { color:#842029; }
.badge-cancelled { background:#dc3545; color:#fff; padding:2px 6px; border-radius:4px; font-size:12px; }
</style>

<div class="table-responsive">
<table class="table table-bordered table-sm fv-table">
    <thead class="bg-light">
        <tr>
            <th width="4%">SN</th>
            <th width="15%">Date</th>
            <th>Officers Visited</th>
            <th width="15%">Visit Status</th>
            <th width="12%">Action</th>
        </tr>
    </thead>

    <tbody id="fv-body">
        <tr><td colspan="5" class="text-center">Loading...</td></tr>
    </tbody>

    <tfoot>
        <tr>
            <td>#</td>
            <td><input type="date" id="fv-date" class="form-control form-control-sm"></td>
            <td><input type="text" id="fv-officers" class="form-control form-control-sm" placeholder="Comma-separated names"></td>
            <td>
                <select id="fv-status" class="form-control form-control-sm">
                    <option value="Non Developed">Non Developed</option>
                    <option value="On development">On development</option>
                    <option value="Developed">Developed</option>
                </select>
            </td>
            <td>
                
                <button id="fv-add-btn" class="btn btn-success btn-sm" disabled title="Fill required fields">
                    <i class="fa fa-plus"></i> Add
                </button>
            </td>
        </tr>
    </tfoot>
</table>
</div>

<script>
(function(){

    var LEASE_ID = <?= (int)$lease_id ?>;

    var bodyEl = document.getElementById("fv-body");
    var addBtn = document.getElementById("fv-add-btn");

    var dateEl = document.getElementById("fv-date");
    var officersEl = document.getElementById("fv-officers");
    var statusEl = document.getElementById("fv-status");

    // -------------------------
    // Enable Add button only when fields filled
    // -------------------------
    function validateInputs(){
        if (dateEl.value.trim() !== "" && officersEl.value.trim() !== ""){
            addBtn.disabled = false;
            addBtn.title = "Add";
        } else {
            addBtn.disabled = true;
            addBtn.title = "Fill required fields";
        }
    }

    dateEl.addEventListener("change", validateInputs);
    officersEl.addEventListener("keyup", validateInputs);

    // -------------------------
    // Load Table
    // -------------------------
    function loadList(){

        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i>';

        fetch("ltl_ajax/list_field_visits.php?lease_id=" + LEASE_ID + "&_ts=" + Date.now())
        .then(r => r.text())
        .then(html => {
            bodyEl.innerHTML = html;
            bindCancelButtons();

            addBtn.innerHTML = '<i class="fa fa-plus"></i> Add';
            validateInputs();
        })
        .catch(() => {
            bodyEl.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Failed to load list.</td></tr>';
        });
    }

    // -------------------------
    // Add New Field Visit
    // -------------------------
    addBtn.addEventListener("click", function(){

        var date = dateEl.value.trim();
        var officers = officersEl.value.trim();
        var status = statusEl.value.trim();

        if (date === "" || officers === ""){
            alert("Fill required fields");
            return;
        }

        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> Saving...';

        let fd = new URLSearchParams();
        fd.append("lease_id", LEASE_ID);
        fd.append("date", date);
        fd.append("officers", officers);
        fd.append("status", status);

        fetch("ltl_ajax/add_field_visit.php", {
            method:"POST",
            headers:{"Content-Type":"application/x-www-form-urlencoded"},
            body:fd.toString()
        })
        .then(r => r.json())
        .then(resp => {

            if (resp.success){

                if (window.Swal){
                    Swal.fire({icon:"success", title:"Added", timer:1300, showConfirmButton:false});
                }

                // Clear inputs
                dateEl.value = "";
                officersEl.value = "";
                statusEl.value = "Non Developed";

                validateInputs();
                loadList();

            } else {
                alert(resp.message || "Insert failed");
            }

            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="fa fa-plus"></i> Add';
        })
        .catch(() => {
            alert("Network error");
            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="fa fa-plus"></i> Add';
        });
    });

    // -------------------------
    // Cancel Record
    // -------------------------
    function bindCancelButtons(){
        document.querySelectorAll(".fv-cancel-btn").forEach(btn => {
            btn.onclick = function(){

                var id = this.getAttribute("data-id");
                if (!id) return;

                if (window.Swal){
                    Swal.fire({
                        title:"Cancel record?",
                        icon:"warning",
                        showCancelButton:true
                    }).then(res => {
                        if (res.isConfirmed) doCancel(id);
                    });
                } else {
                    if (confirm("Cancel this record?")) doCancel(id);
                }
            };
        });
    }

    function doCancel(id){
        let fd = new URLSearchParams();
        fd.append("id", id);

        fetch("ltl_ajax/cancel_field_visit.php", {
            method:"POST",
            headers:{"Content-Type":"application/x-www-form-urlencoded"},
            body:fd.toString()
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) loadList();
            else alert(resp.message || "Failed to cancel");
        })
        .catch(() => alert("Network error"));
    }

    loadList();

})();
</script>

<?php endif; ?>
</div>
