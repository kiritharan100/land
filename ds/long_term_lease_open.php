<?php include 'header.php'; ?>
<style>
/* Active tab styling for sidebar menu */
#submenu-list .list-group-item.active,
#submenu-list .list-group-item.active:focus,
#submenu-list .list-group-item.active:hover {
    background-color: #7a7d81 !important;
    border-color: #3c3e40 !important;
    color: #ffffff !important;
}

/* Optional: ensure hover on non-active keeps readable contrast */
#submenu-list .list-group-item:hover {
    color: inherit;
    text-decoration: none;
}

/* Optional: focus ring for accessibility */
#submenu-list .list-group-item:focus {
    box-shadow: 0 0 0 0.15rem rgba(122, 125, 129, 0.35);
    outline: none;
}
</style>


<?php
$md5_ben_id = $_GET['id'] ?? '';
$ben = null; 
$lease = null; 
$land = null;

function valOrPending($v){
    return trim($v) !== "" ? htmlspecialchars($v) : "<span style='color:red;font-weight:bold;'>Pending</span>";
}

// ---------------------------
// FETCH BENEFICIARY
// ---------------------------
if (!empty($md5_ben_id) && isset($con)) {

    $sql = "SELECT 
              b.ben_id,
              b.name,
              b.address,
              b.district,
              COALESCE(cr.client_name, b.ds_division_text) AS ds_division,
              COALESCE(gn.gn_name, b.gn_division_text) AS gn_division,
              b.nic_reg_no,
              b.telephone,
              b.language,
              b.dob
            FROM beneficiaries b
            LEFT JOIN client_registration cr ON b.ds_division_id = cr.c_id
            LEFT JOIN gn_division gn ON b.gn_division_id = gn.gn_id
            WHERE b.md5_ben_id = ?
            LIMIT 1";

    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 's', $md5_ben_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ben = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    if ($ben) {
        $ben_id = $ben['ben_id'];

        // ---------------------------
        // FETCH LATEST LAND RECORD
        // ---------------------------
        $land_q = "SELECT land_id, land_address, gn_id
                   FROM ltl_land_registration
                   WHERE ben_id = ?
                   ORDER BY land_id DESC LIMIT 1"; 

        if ($st2 = mysqli_prepare($con, $land_q)) {
            mysqli_stmt_bind_param($st2, 'i', $ben_id);
            mysqli_stmt_execute($st2);
            $r2 = mysqli_stmt_get_result($st2);
            $land = mysqli_fetch_assoc($r2);
            mysqli_stmt_close($st2);
        }

        // GN NAME
        if ($land && $land['gn_id']) {
            $gn_q = "SELECT gn_name FROM gn_division WHERE gn_id = ?";
            if ($st3 = mysqli_prepare($con, $gn_q)) {
                mysqli_stmt_bind_param($st3, 'i', $land['gn_id']);
                mysqli_stmt_execute($st3);
                $r3 = mysqli_stmt_get_result($st3);
                $gn_row = mysqli_fetch_assoc($r3);
                $land['gn_name'] = $gn_row['gn_name'] ?? null;
                mysqli_stmt_close($st3);
            }
        }

        // ---------------------------
        // FETCH LATEST LEASE RECORD
        // ---------------------------
        $lease_q = "SELECT 
                        lease_number,location_id,lease_id,
                        file_number,
                        type_of_project
                    FROM leases
                    WHERE beneficiary_id = ?
                    ORDER BY lease_id DESC LIMIT 1";



        if ($st4 = mysqli_prepare($con, $lease_q)) {
            mysqli_stmt_bind_param($st4, 'i', $ben_id);
            mysqli_stmt_execute($st4);
            $r4 = mysqli_stmt_get_result($st4);
            $lease = mysqli_fetch_assoc($r4);
            mysqli_stmt_close($st4);
            
        }
    }
}

$lease_id = $lease['lease_id'] ?? '';
            $lease_location_id = $lease['location_id'] ?? '';
if( $lease_location_id == $location_id ) {
    // ok
} else {
  if($lease_id > 0 ) {
    echo "<br><br><br><br><div align='center' class='alert alert-danger'>You do not have permission to view this lease.</div>";
     exit;
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <br>
        <div class="col-md-12 bg-white" style="padding-top:5px;">

            <h5 class="font-weight-bold" style="margin-bottom:5px;">Long Term Lease > Overview </h5>

            <?php if ($ben): ?>

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Beneficiary Name</th>
                            <th>Land Address</th>
                            <th>Land GN Division</th>
                            <th>Lease Number</th>
                            <th>File Number</th>
                            <th>Type of Project</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><?= valOrPending($ben['name'] ?? '') ?>
                                <?php    ?></td>
                            <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                var lease_id = <?= json_encode($lease_id) ?>; // your dynamic ID

                                fetch("cal_panalty.php?lease_id=" + lease_id)
                                    .then(response => response.text())
                                    .then(data => {
                                        console.log("Penalty Script Executed:", data);
                                    })
                                    .catch(error => console.error("Error:", error));
                            });
                            </script>

                            <td><?= valOrPending($land['land_address'] ?? '') ?></td>
                            <td><?= valOrPending($land['gn_name'] ?? '') ?></td>
                            <td><?= valOrPending($lease['lease_number'] ?? '') ?></td>
                            <td><?= valOrPending($lease['file_number'] ?? '') ?></td>
                            <td><?= valOrPending($lease['type_of_project'] ?? '') ?></td>
                        </tr>
                    </tbody>

                </table>
            </div>

            <?php else: ?>
            <div class="alert alert-warning mb-0">Beneficiary not found or invalid link.</div>
            <?php endif; ?>

        </div>






        <!-- Main Content with Menu + Tab -->
        <div class="row no-gutters" style="margin-right:2px;">

            <!-- Sidebar Menu -->
            <div class="col-md-3 col-lg-2 bg-light border-right" style='margin-top:20px;'>

                <div class="p-3">
                    <!-- <h6 class="text-uppercase text-secondary font-weight-bold mb-3">Menu</h6> -->
                    <div class="list-group" id="submenu-list">
                        <a href="#" class="list-group-item list-group-item-action active"
                            data-target="#land-dashboard">Lease Dashboard</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#land-tab">Land
                            Information</a>
                        <a href="#" class="list-group-item list-group-item-action"
                            data-target="#request_letter">Documents</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#create_leases">
                            <?php if($lease_id > 0){ echo "Manage";} else { echo "Create"; } ?> Leases</a>
                        <a href="#" class="list-group-item list-group-item-action"
                            data-target="#ltl_schedule">Schedule</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#payment">Payment</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#field_visits">Field
                            Visits</a>
                        <a href="#" class="list-group-item list-group-item-action"
                            data-target="#write-off">Write-Off</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#tab3">Reminders</a>
                    </div>


                </div>
                <div style='width:100%;text-align:center;'>
                    <a href="long_term_lease.php"> <button class="btn btn-success">
                            <i class="fa fa-arrow-left" aria-hidden="true"></i> Back to List
                        </button></a>
                </div>
            </div>
            <!-- <h6 class="text-uppercase text-secondary font-weight-bold mb-3">Menu</h6> -->
            <!-- Tab Content -->


            <div class="col-md-9 col-lg-10 bg-white" style='margin-top:20px;padding-top:5px;'>


                <div class="p-4" id="submenu-content">

                    <!-- Tab 1 -->
                    <div id="land-tab" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Land Information</h5>
                        <hr>
                        <div id="land-tab-container" data-loaded="0"></div>
                    </div>

                    <div id="request_letter" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Documents</h5>
                        <hr>
                        <div id="docs-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="create_leases" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Create Leases</h5>
                        <hr>
                        <div id="ltl-create-lease-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="write-off" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Write-Off</h5>
                        <hr>
                        <div id="ltl-write-off-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="ltl_schedule" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Schedule</h5>
                        <hr>
                        <div id="ltl-schedule-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2 -->
                    <div id="land-dashboard" class="submenu-section">
                        <h5 class="font-weight-bold">Lease Dashboard</h5>
                        <hr>
                        <div id="lease-dashboard-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>


                    <div id="schedule" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Lease Schedule</h5>
                        <hr>
                        <p>Maecenas mattis. Sed convallis tristique sem. Proin ut ligula vel nunc egestas porttitor.
                            Morbi lectus risus, iaculis vel, suscipit quis.</p>
                        <p>Fusce ac turpis quis ligula lacinia aliquet. Mauris ipsum. Nulla metus metus, ullamcorper
                            vel, tincidunt sed.</p>
                    </div>

                    <div id="payment" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Payments</h5>
                        <hr>
                        <div id="ltl-payment-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>
                    <div id="field_visits" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Field Visits</h5>
                        <hr>
                        <div id="ltl-field-visit-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="tab3" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Reminders</h5>
                        <hr>
                        <div id="ltl-reminders-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!-- /Main Content -->

    </div>
</div>

<!-- JS for switching tabs -->
<script>
// Tab switching and lazy-load for Land Information
(function() {
    var MD5_BEN_ID = <?php echo json_encode($md5_ben_id ?? ''); ?>;

    function ensureLeafletLoaded(cb) {
        if (window.L && typeof window.L.map === 'function') {
            cb && cb();
            return;
        }
        // Add CSS if not present
        var haveCss = !!document.querySelector('link[href*="leaflet.css"]');
        if (!haveCss) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet/dist/leaflet.css';
            document.head.appendChild(link);
        }
        // Add JS
        var script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet/dist/leaflet.js';
        script.onload = function() {
            cb && cb();
        };
        script.onerror = function() {
            cb && cb();
        };
        document.head.appendChild(script);
    }

    function executeScripts(container) {
        var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
        scripts.forEach(function(old) {
            var s = document.createElement('script');
            if (old.src) {
                // Avoid re-loading Leaflet if already present
                if (window.L && /leaflet\.js/i.test(old.src)) {
                    return;
                }
                s.src = old.src;
                s.async = false;
            } else {
                s.text = old.text || old.textContent || '';
            }
            document.body.appendChild(s);
        });
    }

    function loadPaymentTab() {
        var pc = document.getElementById('ltl-payment-container');
        if (!pc) return;
        pc.innerHTML = '<div style="text-align:center;padding:16px">\
        <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\
      </div>';
        var urlP =
            'ltl_ajax/payment_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
            Date.now();
        fetch(urlP)
            .then(function(r) {
                return r.text();
            })
            .then(function(html) {
                pc.innerHTML = html;
                try {
                    executeScripts(pc);
                } catch (e) {}
            })
            .catch(function() {
                pc.innerHTML = '<div class="text-danger">Failed to load payments.</div>';
            });
    }

    // Load Lease Dashboard (AJAX) with optional force reload
    window.loadLeaseDashboard = function(force) {
        var cont = document.getElementById('lease-dashboard-container');
        if (!cont) return;
        if (cont.getAttribute('data-loaded') === '1' && !force) return;
        cont.innerHTML = '<div style="text-align:center;padding:16px">\
        <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\
      </div>';
        var url =
            'ltl_ajax/lease_dashboard_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
            Date.now();
        fetch(url)
            .then(function(r) {
                return r.text();
            })
            .then(function(html) {
                cont.innerHTML = html;
                try {
                    executeScripts(cont);
                } catch (e) {}
                cont.setAttribute('data-loaded', '1');
            })
            .catch(function() {
                cont.innerHTML = '<div class="text-danger">Failed to load dashboard.</div>';
            });
    };

    function loadLandTabOnce() {
        var container = document.getElementById('land-tab-container');
        if (!container || container.getAttribute('data-loaded') === '1') return;
        // show loader while preparing and fetching content
        container.innerHTML = '<div class="land-tab-loader" style="text-align:center;padding:16px">\
        <img src="../img/Loading_icon.gif" alt="Loading..." style="width:248px;height:auto" />\
      </div>';
        var url =
            'ltl_ajax/tab_land_infomation_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
            Date.now();

        // Ensure Leaflet is ready before content init
        ensureLeafletLoaded(function() {
            if (window.jQuery && typeof jQuery.fn.load === 'function') {
                jQuery('#land-tab-container').load(url, function(responseText, status) {
                    if (status === 'success') {
                        container.setAttribute('data-loaded', '1');
                    } else {
                        container.innerHTML =
                            '<div class="text-danger">Failed to load content.</div>';
                    }
                });
            } else {
                fetch(url)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        container.innerHTML = html;
                        executeScripts(container);
                        container.setAttribute('data-loaded', '1');
                    })
                    .catch(function() {
                        container.innerHTML = '<div class="text-danger">Failed to load content.</div>';
                    });
            }
        });
    }

    // Lazy-load the Documents tab script file and initialize it
    function ensureDocsScriptLoaded(cb) {
        if (window.LTLDocs && typeof window.LTLDocs.init === 'function') {
            cb && cb();
            return;
        }
        var s = document.createElement('script');
        s.src = 'ltl_ajax/docs_tab.js?_ts=' + Date.now();
        s.onload = function() {
            cb && cb();
        };
        s.onerror = function() {
            cb && cb();
        };
        document.head.appendChild(s);
    }

    document.querySelectorAll('#submenu-list a').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            // deactivate all menu links
            document.querySelectorAll('#submenu-list a').forEach(function(item) {
                item.classList.remove('active');
            });
            // hide all tab sections
            document.querySelectorAll('.submenu-section').forEach(function(section) {
                section.classList.add('d-none');
            });
            // activate current link and show corresponding section
            this.classList.add('active');
            var target = this.getAttribute('data-target');
            var sec = document.querySelector(target);
            if (sec) {
                sec.classList.remove('d-none');
            }
            if (target === '#land-tab') {
                loadLandTabOnce();
            }
            if (target === '#land-dashboard') {
                window.loadLeaseDashboard(true);
            }
            if (target === '#write-off') {
                // Always reload write-off tab when opened to reflect latest actions
                var woc = document.getElementById('ltl-write-off-container');
                if (woc) {
                    woc.innerHTML =
                        '<div style="text-align:center;padding:16px">\n              <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\n            </div>';
                    var urlWO =
                        'ltl_ajax/write_off_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                        Date.now();
                    fetch(urlWO)
                        .then(function(r) {
                            return r.text();
                        })
                        .then(function(html) {
                            woc.innerHTML = html;
                        })
                        .catch(function() {
                            woc.innerHTML =
                                '<div class="text-danger">Failed to load write-offs.</div>';
                        });
                }
            }
            if (target === '#request_letter') {
                ensureDocsScriptLoaded(function() {
                    if (window.LTLDocs) {
                        window.LTLDocs.init(MD5_BEN_ID);
                    }
                });
            }
            if (target === '#create_leases') {
                var cont = document.getElementById('ltl-create-lease-container');
                if (cont && cont.getAttribute('data-loaded') !== '1') {
                    var url =
                        'ltl_ajax/create_lease_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&prefix=<?php echo htmlspecialchars($client_prefix ?? "", ENT_QUOTES); ?>&_ts=' +
                        Date.now();
                    fetch(url)
                        .then(function(r) {
                            return r.text();
                        })
                        .then(function(html) {
                            cont.innerHTML = html;
                            // ensure any inline scripts inside the fetched HTML execute (loads JS + init)
                            try {
                                executeScripts(cont);
                            } catch (e) {}
                            cont.setAttribute('data-loaded', '1');
                        })
                        .catch(function() {
                            cont.innerHTML = '<div class="text-danger">Failed to load.</div>';
                        });
                }
            }
            if (target === '#ltl_schedule') {
                var sc = document.getElementById('ltl-schedule-container');
                if (sc) {
                    // always reload schedule on tab open
                    sc.innerHTML = '<div style="text-align:center;padding:16px">\
              <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\
            </div>';
                    var urlS =
                        'ltl_ajax/lease_schedule_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                        Date.now();
                    fetch(urlS)
                        .then(function(r) {
                            return r.text();
                        })
                        .then(function(html) {
                            sc.innerHTML = html;
                            try {
                                executeScripts(sc);
                            } catch (e) {}
                        })
                        .catch(function() {
                            sc.innerHTML =
                                '<div class="text-danger">Failed to load schedule.</div>';
                        });
                }
            }
            if (target === '#payment') {
                loadPaymentTab();
            }
            if (target === '#tab3') {
                var rem = document.getElementById('ltl-reminders-container');
                if (rem && rem.getAttribute('data-loaded') !== '1') {
                    rem.innerHTML =
                        '<div style="text-align:center;padding:16px">\n                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\n              </div>';
                    var urlR =
                        'ltl_ajax/reminders_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                        Date.now();
                    fetch(urlR)
                        .then(function(r) {
                            return r.text();
                        })
                        .then(function(html) {
                            rem.innerHTML = html;
                            try {
                                executeScripts(rem);
                            } catch (e) {}
                            rem.setAttribute('data-loaded', '1');
                        })
                        .catch(function() {
                            rem.innerHTML =
                                '<div class="text-danger">Failed to load reminders.</div>';
                        });
                }
            }

            if (target === '#field_visits') {

                var fv = document.getElementById('ltl-field-visit-container');

                if (fv) {
                    fv.innerHTML = `
            <div style="text-align:center;padding:16px">
                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
            </div>
        `;

                    var urlFV =
                        'ltl_ajax/field_visits_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                        Date.now();

                    fetch(urlFV)
                        .then(r => r.text())
                        .then(html => {

                            fv.innerHTML = html;

                            // 🔥 FIX 1 — enable Add button again  
                            const addBtn = fv.querySelector('#fv-add-btn');
                            if (addBtn) {
                                addBtn.disabled = false;
                                addBtn.title = 'Add';
                                addBtn.innerHTML = '<i class="fa fa-plus"></i> Add';
                            }

                            // 🔥 FIX 2 — re-bind table scripts
                            try {
                                executeScripts(fv);
                            } catch (e) {}

                        })
                        .catch(() => {
                            fv.innerHTML =
                                '<div class="text-danger">Failed to load field visits.</div>';
                        });
                }
            }



        });
    });

    // Delegated event handler for write-off cancel button (fixes dynamic reload issue)
    document.addEventListener('click', function(ev) {
        var btn = ev.target.closest && ev.target.closest('.wo-cancel-btn');
        if (btn) {
            var id = btn.getAttribute('data-id');
            var sid = btn.getAttribute('data-schedule-id');
            var lid = btn.getAttribute('data-lease-id');
            var amt = btn.getAttribute('data-amount');
            var proceed = function() {
                fetch('ltl_ajax/cancel_write_off.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            id: id
                        }).toString()
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp && resp.success) {
                            if (typeof Swal !== 'undefined' && Swal.fire) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Cancelled',
                                    text: 'Write-off reversed.'
                                });
                            } else {
                                alert('Write-off reversed.');
                            }
                            // Directly reload write-off tab
                            var woc = document.getElementById('ltl-write-off-container');
                            if (woc) {
                                woc.innerHTML =
                                    '<div style="text-align:center;padding:16px">\n              <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\n            </div>';
                                var urlWO =
                                    'ltl_ajax/write_off_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                                    Date.now();
                                fetch(urlWO)
                                    .then(function(r) {
                                        return r.text();
                                    })
                                    .then(function(html) {
                                        woc.innerHTML = html;
                                    })
                                    .catch(function() {
                                        woc.innerHTML =
                                            '<div class="text-danger">Failed to load write-offs.</div>';
                                    });
                            }
                        } else {
                            if (typeof Swal !== 'undefined' && Swal.fire) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed',
                                    text: (resp && resp.message) || 'Error'
                                });
                            } else {
                                alert('Failed: ' + ((resp && resp.message) || 'Error'));
                            }
                        }
                    })
                    .catch(e => {
                        if (typeof Swal !== 'undefined' && Swal.fire) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Network',
                                text: 'Network error'
                            });
                        } else {
                            alert('Network error');
                        }
                    });
            };
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cancel Write-Off',
                    html: 'Write-Off ID <b>' + id + '</b><br>Amount <b>' + amt +
                        '</b><br>Reinstate this penalty amount?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Cancel',
                    cancelButtonText: 'No'
                }).then(function(r) {
                    if (r.isConfirmed) proceed();
                });
            } else {
                if (confirm('Cancel write-off ' + id + ' amount ' + amt + '?')) proceed();
            }
            return;
        }
        // Premium change cancel button
        var pcBtn = ev.target.closest && ev.target.closest('.pc-cancel-btn');
        if (!pcBtn) return;
        var id = pcBtn.getAttribute('data-id');
        var sid = pcBtn.getAttribute('data-schedule-id');
        var lid = pcBtn.getAttribute('data-lease-id');
        var old = pcBtn.getAttribute('data-old');
        var proceed = function() {
            fetch('ltl_ajax/cancel_premium_change.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        id: id
                    }).toString()
                })
                .then(r => r.json())
                .then(resp => {
                    if (resp && resp.success) {
                        if (typeof Swal !== 'undefined' && Swal.fire) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Cancelled',
                                text: 'Premium change cancelled.'
                            });
                        } else {
                            alert('Premium change cancelled.');
                        }
                        // Directly reload write-off tab
                        var woc = document.getElementById('ltl-write-off-container');
                        if (woc) {
                            woc.innerHTML =
                                '<div style="text-align:center;padding:16px">\n              <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\n            </div>';
                            var urlWO =
                                'ltl_ajax/write_off_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                                Date.now();
                            fetch(urlWO)
                                .then(function(r) {
                                    return r.text();
                                })
                                .then(function(html) {
                                    woc.innerHTML = html;
                                })
                                .catch(function() {
                                    woc.innerHTML =
                                        '<div class="text-danger">Failed to load write-offs.</div>';
                                });
                        }
                    } else {
                        if (typeof Swal !== 'undefined' && Swal.fire) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: (resp && resp.message) || 'Error'
                            });
                        } else {
                            alert('Failed: ' + ((resp && resp.message) || 'Error'));
                        }
                    }
                })
                .catch(e => {
                    if (typeof Swal !== 'undefined' && Swal.fire) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Network',
                            text: 'Network error'
                        });
                    } else {
                        alert('Network error');
                    }
                });
        };
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                icon: 'warning',
                title: 'Cancel Premium Change',
                html: 'Change ID <b>' + id + '</b><br>Old Amount <b>' + old +
                    '</b><br>Revert to old premium?',
                showCancelButton: true,
                confirmButtonText: 'Yes, Cancel',
                cancelButtonText: 'No'
            }).then(function(r) {
                if (r.isConfirmed) proceed();
            });
        } else {
            if (confirm('Cancel premium change ' + id + '?')) proceed();
        }
    });

    // If Dashboard is default, nothing to load initially. If Land is default in some flows, pre-load once.
    var active = document.querySelector('#submenu-list a.active');
    if (active) {
        if (active.getAttribute('data-target') === '#land-tab') {
            loadLandTabOnce();
        }
        if (active.getAttribute('data-target') === '#land-dashboard') {
            window.loadLeaseDashboard(true);
        }
        if (active.getAttribute('data-target') === '#write-off') {
            var woc = document.getElementById('ltl-write-off-container');
            if (woc) {
                woc.innerHTML =
                    '<div style="text-align:center;padding:16px">\n            <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\n          </div>';
                var urlWO =
                    'ltl_ajax/write_off_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                    Date.now();
                fetch(urlWO)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        woc.innerHTML = html;
                    })
                    .catch(function() {
                        woc.innerHTML = '<div class="text-danger">Failed to load write-offs.</div>';
                    });
            }
        }
        if (active.getAttribute('data-target') === '#request_letter') {
            ensureDocsScriptLoaded(function() {
                if (window.LTLDocs) {
                    window.LTLDocs.init(MD5_BEN_ID);
                }
            });
        }
        if (active.getAttribute('data-target') === '#create_leases') {
            var cont = document.getElementById('ltl-create-lease-container');
            if (cont && cont.getAttribute('data-loaded') !== '1') {
                var url =
                    'ltl_ajax/create_lease_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                    Date.now();
                fetch(url)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        cont.innerHTML = html;
                        // ensure any inline scripts inside the fetched HTML execute (loads JS + init)
                        try {
                            executeScripts(cont);
                        } catch (e) {}
                        cont.setAttribute('data-loaded', '1');
                    })
                    .catch(function() {
                        cont.innerHTML = '<div class="text-danger">Failed to load.</div>';
                    });
            }
        }
        if (active.getAttribute('data-target') === '#ltl_schedule') {
            var sc = document.getElementById('ltl-schedule-container');
            if (sc) {
                sc.innerHTML = '<div style="text-align:center;padding:16px">\
            <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\
          </div>';
                var urlS =
                    'ltl_ajax/lease_schedule_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                    Date.now();
                fetch(urlS)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        sc.innerHTML = html;
                        try {
                            executeScripts(sc);
                        } catch (e) {}
                    })
                    .catch(function() {
                        sc.innerHTML = '<div class="text-danger">Failed to load schedule.</div>';
                    });
            }
        }
        if (active.getAttribute('data-target') === '#payment') {
            loadPaymentTab();
        }
        if (active.getAttribute('data-target') === '#field_visits') {
            var fv = document.getElementById('ltl-field-visit-container');
            if (fv) {
                var urlFV =
                    'ltl_ajax/field_visits_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                    Date.now();
                fetch(urlFV)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        fv.innerHTML = html;
                    })
                    .catch(function() {
                        fv.innerHTML = '<div class="text-danger">Failed to load field visits.</div>';
                    });
            }
        }
        if (active.getAttribute('data-target') === '#tab3') {
            var rem = document.getElementById('ltl-reminders-container');
            if (rem) {
                var urlR =
                    'ltl_ajax/reminders_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                    Date.now();
                fetch(urlR)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        rem.innerHTML = html;
                    })
                    .catch(function() {
                        rem.innerHTML = '<div class="text-danger">Failed to load reminders.</div>';
                    });
            }
        }

        // Global listener: after recording a payment, reload the Payment tab content
        window.addEventListener('ltl:payments-updated', function() {
            loadPaymentTab();
            // Also refresh dashboard to reflect new outstanding
            if (document.querySelector('#land-dashboard') && !document.querySelector('#land-dashboard')
                .classList.contains('d-none')) {
                window.loadLeaseDashboard(true);
            } else {
                // mark not loaded so next open refreshes
                var cont = document.getElementById('lease-dashboard-container');
                if (cont) cont.setAttribute('data-loaded', '0');
            }
        });

        // When schedule updated (premium edit or penalty write-off), reload schedule tab if visible
        window.addEventListener('ltl:schedule-updated', function(ev) {
            var sc = document.getElementById('ltl-schedule-container');
            if (sc && !document.getElementById('ltl_schedule').classList.contains('d-none')) {
                sc.innerHTML =
                    '<div style="text-align:center;padding:16px">\n            <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\n          </div>';
                var urlS =
                    'ltl_ajax/lease_schedule_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                    Date.now();
                fetch(urlS)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        sc.innerHTML = html;
                        try {
                            executeScripts(sc);
                        } catch (e) {}
                    })
                    .catch(function() {
                        sc.innerHTML = '<div class="text-danger">Failed to load schedule.</div>';
                    });
            } else {
                // Mark for reload on next open
                if (sc) sc.setAttribute('data-loaded', '0');
            }
            // Dashboard might need refresh to reflect totals
            if (document.querySelector('#land-dashboard') && !document.querySelector('#land-dashboard')
                .classList.contains('d-none')) {
                window.loadLeaseDashboard(true);
            } else {
                var cont = document.getElementById('lease-dashboard-container');
                if (cont) cont.setAttribute('data-loaded', '0');
            }
        });

        // When write-off entries updated (new write-off or cancellation) reload write-off tab if visible
        window.addEventListener('ltl:writeoff-updated', function(ev) {
            var woc = document.getElementById('ltl-write-off-container');
            if (woc && !document.getElementById('write-off').classList.contains('d-none')) {
                woc.innerHTML =
                    '<div style="text-align:center;padding:16px">\n            <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\n          </div>';
                var urlWO =
                    'ltl_ajax/write_off_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' +
                    Date.now();
                fetch(urlWO)
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(html) {
                        woc.innerHTML = html;
                    })
                    .catch(function() {
                        woc.innerHTML = '<div class="text-danger">Failed to load write-offs.</div>';
                    });
            } else {
                if (woc) woc.setAttribute('data-loaded', '0');
            }
            // Also sync dashboard for penalty totals
            if (document.querySelector('#land-dashboard') && !document.querySelector('#land-dashboard')
                .classList.contains('d-none')) {
                window.loadLeaseDashboard(true);
            } else {
                var cont = document.getElementById('lease-dashboard-container');
                if (cont) cont.setAttribute('data-loaded', '0');
            }
        });
    }
})();
</script>


<!-- Select2 (optional enhancement like other pages) -->
<link href="/assets/plugins/select2/dist/css/select2.min.css" rel="stylesheet" />
<script src="/assets/plugins/select2/dist/js/select2.full.min.js"></script>
<!-- SweetAlert2 -->


<?php include 'footer.php'; ?>