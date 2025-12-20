<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
include '../../db.php';

// Return HTML fragment
header('Content-Type: text/html');

if (isset($_GET['lease_id'])) {
    $lease_id = (int)$_GET['lease_id'];

    // Get lease details
    $lease_sql = "SELECT l.*,  ben.name as beneficiary_name ,cr.payment_sms,ben.language,ben.telephone,ben.ben_id
                  FROM leases l
                   
                  LEFT JOIN client_registration cr ON cr.c_id = l.location_id
                  LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
                  WHERE l.lease_id = ?";
    $stmt = $con->prepare($lease_sql);
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $lease = $stmt->get_result()->fetch_assoc();
?>
<form id="paymentForm" method="post" action="ajax/record_payment_simple.php">
    <input type="hidden" name="lease_id" value="<?= $lease_id ?>">
    <input type="hidden" name="lease_type_id" value="<?= $lease['lease_type_id'] ?>">
    <input type="hidden" name="payment_sms" value="<?= htmlspecialchars($lease['payment_sms']) ?>">
    <input type="hidden" name="sms_language" value="<?= htmlspecialchars($lease['language']) ?>">
    <input type="hidden" name="telephone" value="<?= htmlspecialchars($lease['telephone']) ?>">
    <input type="hidden" name="ben_id" value="<?= htmlspecialchars($lease['ben_id']) ?>">
        
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info">
                <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number']) ?> - <?= htmlspecialchars($lease['beneficiary_name']) ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="payment_date">Payment Date *</label>
                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                       value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="payment_method">Payment Method *</label>
                <select class="form-control" id="payment_method" name="payment_method" required>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_deposit">Bank Deposit</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="reference_number">Receipt Number *</label>
                <input type="text" class="form-control" id="reference_number" name="reference_number" 
                       placeholder="Enter receipt number" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="amount">Amount (LKR) *</label>
                <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                       required placeholder="Enter payment amount">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" 
                          placeholder="Any additional notes..."></textarea>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 text-right">
            <button type="submit" class="btn btn-success">
                <i class="fa fa-save"></i> Record Payment
            </button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
    </div>
</form>
<?php } ?>
