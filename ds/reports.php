 <?php include 'header.php';
 checkPermission(16);
  ?>
 <style>
.report-item {
    padding: 12px;
    border-bottom: 1px solid #e5e5e5;
    cursor: pointer;
}

.report-item:hover {
    background: #f7f7f7;
}

.report-title {
    font-size: 16px;
    font-weight: 600;
}

.report-desc {
    font-size: 13px;
    color: #666;
}

.report-form {
    background: #fafafa;
    padding: 15px;
    border-left: 3px solid #007bff;
    display: none;
}
 </style>

 <div class="content-wrapper">
     <div class="container-fluid">

         <div class="row">
             <div class="col-sm-12 p-0">
                 <div class="main-header">
                     <h4><i class="fa fa-file"></i> Reports</h4>
                 </div>
             </div>
         </div>

         <div class="card">
             <div class="card-block">

                 <!-- Report 1 -->
                 <div class="report-item" data-target="#form_lease">
                     <div class="report-title"><b>01.</b> Long Term Lease Payments Summary</div>
                     <div class="report-desc"> List of payments within selected period.</div>
                 </div>
                 <div id="form_lease" class="report-form">
                     <form method="GET" action="reports/long_term_lease_payments.php" target="_blank" rel="noopener">
                         <div class="row">
                             <div class="col-md-4">
                                 <label>From Date</label>
                                 <input type="date" name="from" class="form-control"
                                     value='<?= date("Y-m-01", strtotime("last month")) ?>' required>
                             </div>
                             <div class="col-md-4">
                                 <label>To Date</label>
                                 <input type="date" name="to" class="form-control"
                                     value='<?= date("Y-m-t", strtotime("last month")) ?>' required>
                             </div>
                             <div class="col-md-4">
                                 <label>&nbsp;</label>
                                 <button class="btn btn-primary btn-block">Open Report</button>
                             </div>
                         </div>
                     </form>
                 </div>

                 <!-- Report 2 -->
                 <div class="report-item" data-target="#form_penalties">
                     <div class="report-title">02. Long Term Lease Outstanding</div>
                     <div class="report-desc">Outstanding amounts as at a given date.</div>
                 </div>
                 <div id="form_penalties" class="report-form">
                     <form method="GET" action="reports/long_term_lease_detail_arrears.php" target="_blank"
                         rel="noopener">
                         <div class="row">
                             <div class="col-md-4">
                                 <label>As At Date</label>
                                 <input type="date" name="as_at" class="form-control" value='<?= date("Y-m-d") ?>'
                                     required>
                             </div>
                             <div class="col-md-4">
                                 <label>Lease Type</label>
                                 <select name="lease_type" class="form-control" required>
                                     <option value="All">All</option>
                                     <option value="Agricultural">Agricultural</option>
                                     <option value="Commercial">Commercial</option>
                                     <option value="BOI Zones">BOI Zones</option>
                                     <option value="Religious">Religious</option>
                                     <option value="Educational">Educational</option>
                                     <option value="Charitable">Charitable</option>
                                     <option value="Other Purposes">Other Purposes</option>
                                 </select>
                             </div>
                             <div class="col-md-4 d-flex align-items-end">
                                 <button class="btn btn-primary">Open Report</button>
                             </div>

                         </div>
                     </form>
                 </div>

                 <!-- Report 3 -->
                 <div class="report-item" data-target="#form_ltl_info">
                     <div class="report-title">03. Long Term lease information</div>
                     <div class="report-desc">Detailed information about long term leases.</div>
                 </div>
                 <div id="form_ltl_info" class="report-form">
                     <form method="GET" action="reports/long_term_lease_information.php" target="_blank" rel="noopener">
                         <div class="row">

                             <div class="col-md-8 d-flex align-items-end">
                                 <button class="btn btn-primary">Open Report</button>
                             </div>
                         </div>
                     </form>
                 </div>




                 <!-- Report 4 -->
                 <div class="report-item" data-target="#form_beneficiary">
                     <div class="report-title">04. Payment Remindes</div>
                     <div class="report-desc"> Payment remindes for for the lease </div>
                 </div>
                 <div id="form_beneficiary" class="report-form">
                     <form method="GET" action="reports/payment_remindes.php" target="_blank" rel="noopener">
                         <div class="row">
                             <div class="col-md-4">
                                 <label>Year</label>
                                 <input type="number" name="year" class="form-control" value="<?= date('Y'); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label>&nbsp;</label>
                                 <button class="btn btn-primary btn-block">Open Report</button>
                             </div>
                         </div>
                     </form>
                 </div>




             </div>
         </div>

     </div>
 </div>

 <script>
document.querySelectorAll(".report-item").forEach(item => {
    item.addEventListener("click", function() {
        let target = this.getAttribute("data-target");

        // Hide all forms
        document.querySelectorAll(".report-form").forEach(f => f.style.display = "none");

        // Show selected form
        document.querySelector(target).style.display = "block";

        // Scroll into view for better UX
        document.querySelector(target).scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    });
});
 </script>

 <?php include 'footer.php'; ?>