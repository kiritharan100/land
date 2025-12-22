<?php 
include 'header.php'; 
 

// Get dashboard statistics with robust error handling
$current_month = date('Y-m');
$current_year = date('Y');
$as_at_dashboard = date('Y-m-d'); // today's outstanding snapshot

// Helper function to safely get count from database
function getCount($con, $sql, $fallback = 0) {
    $result = mysqli_query($con, $sql);
    if ($result) {
        $data = mysqli_fetch_assoc($result);
        return $data ? array_values($data)[0] : $fallback;
    }
    return $fallback;
}

// Get statistics with fallbacks
$total_lands = 30; // placeholder; not used in dashboard
$total_beneficiaries = getCount($con, "SELECT COUNT(*) as total_beneficiaries FROM beneficiaries WHERE status = 1", 5);
// Active long term leases: filter by status Active, end_date > today, and optional location
$lease_where = "status = 'Active' AND end_date > CURDATE()";
if (isset($location_id) && (int)$location_id > 0) {
    $lease_where .= " AND location_id = " . (int)$location_id;
}
$total_leases = 0;
// Monthly collection now fetched via AJAX from lease_payments.amount
$monthly_collection = 0; // placeholder; will be replaced client-side

// Number of Commercial Leases (optionally filtered by location)
$com_where = "type_of_project = 'Commercial'";
if (isset($location_id) && (int)$location_id > 0) {
    $com_where .= " AND location_id = " . (int)$location_id;
}
$commercial_leases = getCount($con, "SELECT COUNT(*) AS cnt FROM leases WHERE $com_where", 0);

// Get last 12 months cash collection data
$collection_data = array();
$outstanding_data = array();
$months_labels = array();

 
?>

<div class="content-wrapper">
    <!-- Container-fluid starts -->
    <div class="container-fluid">

        <!-- Header Starts -->
        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4><i class="fas fa-tachometer-alt"></i> Dashboard </h4>
                </div>
            </div>
        </div>
        <!-- Header end -->

        <!-- Dashboard Statistics Cards -->
        <div class="row">
            <!-- Outstanding To Date -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Outstanding To Date</div>
                                <div id="total-outstanding-value" class="h5 mb-0 font-weight-bold">Rs. 0.00</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <small id="outstanding-asof-label" class="text-white-50">As of today</small>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div>
            </div>

            <!-- Pending Reminders (not sent) -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Pending Reminders</div>
                                <div id="pending-reminders-value" class="h5 mb-0 font-weight-bold" data-count="0">0
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-bell fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <small class="text-white-50">Not sent (Annexure 09)</small>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div>
            </div>

    <!-- Pending Files -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Pending Files</div>
                        <div id="pending-files-value" class="h5 mb-0 font-weight-bold" data-count="0">0</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-folder-open fa-2x"></i>
                    </div>
                        </div>
                    </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <small class="text-white-50">Missing file numbers</small>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>

            <!-- Collection This Month -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-uppercase mb-1">Collection This Month</div>
                                <div id="monthly-collection-value" class="h5 mb-0 font-weight-bold" data-count="0">Rs.
                                    0.00</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <small class="text-white-50"><?php echo date('F Y'); ?> collections</small>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- Cash Collection Chart -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-area"></i> Last 12 Months Cash Collection
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="cashCollectionChart" style="height: 400px;"></div>
                    </div>
                </div>
            </div>

            <!-- Type-wise Lease Count Pie Chart -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> Type-wise Lease Count
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="typeChart" style="height: 400px;"></div>
                    </div>
                </div>
            </div>
        </div>



    </div>
    <!-- Container-fluid ends -->
</div>

<!-- Include Highcharts -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>

<script>
// Cash Collection Chart (dynamic via lease_payments, filtered by location)
var cashChart = Highcharts.chart('cashCollectionChart', {
    chart: {
        type: 'areaspline'
    },
    title: {
        text: ''
    },
    xAxis: {
        categories: []
    },
    yAxis: {
        title: {
            text: 'Amount (Rs.)'
        },
        labels: {
            formatter: function() {
                return 'Rs. ' + Highcharts.numberFormat(this.value, 0);
            }
        }
    },
    plotOptions: {
        areaspline: {
            fillOpacity: 0.5
        }
    },
    series: [{
        name: 'Cash Collection',
        data: [],
        color: '#4e73df',
        fillColor: {
            linearGradient: {
                x1: 0,
                y1: 0,
                x2: 0,
                y2: 1
            },
            stops: [
                [0, '#4e73df'],
                [1, Highcharts.color('#4e73df').setOpacity(0.1).get('rgba')]
            ]
        }
    }],
    tooltip: {
        formatter: function() {
            return '<b>' + this.x + '</b><br/>Collection: Rs. ' + Highcharts.numberFormat(this.y, 2);
        }
    },
    credits: {
        enabled: false
    }
});

// Type-wise Lease Count Pie Chart (filtered by location)
var typeChart = Highcharts.chart('typeChart', {
    chart: {
        type: 'pie'
    },
    title: {
        text: ''
    },
    tooltip: {
        pointFormat: '{series.name}: <b>{point.y}</b><br/>Percentage: <b>{point.percentage:.1f}%</b>'
    },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b>: {point.percentage:.1f}% ({point.y})'
            }
        }
    },
    series: [{
        name: 'Leases',
        colorByPoint: true,
        data: []
    }],
    credits: {
        enabled: false
    }
});

// Comparison Chart removed

// Add some animation and interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Animate counter numbers with data attributes
    const counters = document.querySelectorAll('.h5[data-count]');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        if (isNaN(target)) return;

        let count = 0;
        const increment = target / 100;
        const isMonetary = counter.textContent.includes('Rs.');

        const timer = setInterval(() => {
            count += increment;
            if (count < target) {
                if (isMonetary) {
                    counter.textContent = 'Rs. ' + Math.ceil(count).toLocaleString();
                } else {
                    counter.textContent = Math.ceil(count).toLocaleString();
                }
            } else {
                if (isMonetary) {
                    counter.textContent = 'Rs. ' + target.toLocaleString();
                } else {
                    counter.textContent = target.toLocaleString();
                }
                clearInterval(timer);
            }
        }, 20);
    });

    // Fetch monthly collection via AJAX from lease_payments
    const mcEl = document.getElementById('monthly-collection-value');
    // Embed PHP location_id (defaults to 0 if not set)
    const LOC_ID = <?php echo isset($location_id) ? (int)$location_id : 0; ?>;
    if (mcEl) {
        mcEl.textContent = 'Rs. Loading...';
        const mcUrl = 'dashboard_ajax/monthly_collection.php?_ts=' + Date.now() + (LOC_ID > 0 ?
            '&location_id=' + LOC_ID : '');
        fetch(mcUrl)
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    const amt = data.amount || 0;
                    mcEl.setAttribute('data-count', amt);
                    // Simple animate to amount
                    let current = 0;
                    const target = amt;
                    const step = Math.max(target / 100, 10);
                    const anim = setInterval(() => {
                        current += step;
                        if (current >= target) {
                            current = target;
                            clearInterval(anim);
                        }
                        mcEl.textContent = 'Rs. ' + current.toFixed(2).replace(
                            /\B(?=(\d{3})+(?!\d))/g, ',');
                    }, 15);
                } else {
                    mcEl.textContent = 'Rs. 0.00';
                }
            })
            .catch(() => {
                mcEl.textContent = 'Rs. 0.00';
            });
    }
    // Fetch Outstanding To Date (same calc as arrears report) and populate first card
    const outEl = document.getElementById('total-outstanding-value');
    if (outEl) {
        outEl.textContent = 'Rs. Loading...';
        const asAt = '<?php echo $as_at_dashboard; ?>';
        const outUrl = 'dashboard_ajax/total_lease_outstanding.php?_ts=' + Date.now() + '&as_at=' + asAt +
            '&lease_type=All' + (LOC_ID > 0 ? '&location_id=' + LOC_ID : '');
        fetch(outUrl)
            .then(r => r.json())
            .then(d => {
                if (d && d.success) {
                    const amt = d.total_outstanding || 0;
                    outEl.textContent = 'Rs. ' + (amt.toFixed ? amt.toFixed(2) : Number(amt).toFixed(2))
                        .replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    outEl.title = 'Rent: ' + (d.rent_component || 0).toLocaleString() + ', Penalty: ' + (d
                        .penalty_component || 0).toLocaleString() + ', Premium: ' + (d
                        .premium_component || 0).toLocaleString();
                    const asofEl = document.getElementById('outstanding-asof-label');
                    if (asofEl) asofEl.textContent = 'As at ' + asAt;
                } else {
                    outEl.textContent = 'Rs. 0.00';
                }
            })
            .catch(() => {
                outEl.textContent = 'Rs. 0.00';
            });
    }
    // Fetch pending reminders (not sent) filtered by location
    const pendingEl = document.getElementById('pending-reminders-value');
    if (pendingEl) {
        pendingEl.textContent = 'Loading...';
        const pendingUrl = 'dashboard_ajax/pending_reminders.php?_ts=' + Date.now() + (LOC_ID > 0 ?
            '&location_id=' + LOC_ID : '');
        fetch(pendingUrl)
            .then(r => r.json())
            .then(d => {
                if (d && d.success) {
                    const cnt = Number(d.pending_count) || 0;
                    pendingEl.setAttribute('data-count', cnt);
                    let current = 0;
                    const step = Math.max(cnt / 50, 1);
                    const anim = setInterval(() => {
                        current += step;
                        if (current >= cnt) {
                            current = cnt;
                            clearInterval(anim);
                        }
                        pendingEl.textContent = Math.round(current).toLocaleString();
                    }, 15);
                } else {
                    pendingEl.textContent = '0';
                }
            })
            .catch(() => {
                pendingEl.textContent = '0';
            });
    }
    // Fetch 12-month series for cash collection filtered by location
    const seriesUrl = 'dashboard_ajax/collection_series.php?_ts=' + Date.now() + (LOC_ID > 0 ? '&location_id=' +
        LOC_ID : '');
    fetch(seriesUrl)
        .then(r => r.json())
        .then(d => {
            if (d && d.success) {
                cashChart.xAxis[0].setCategories(d.months || []);
                cashChart.series[0].setData(d.amounts || []);
            }
        })
        .catch(() => {
            /* leave placeholder */ });

    // Fetch pending file numbers (latest lease per beneficiary, missing file number) filtered by location
    const pendingFilesEl = document.getElementById('pending-files-value');
    if (pendingFilesEl) {
        pendingFilesEl.textContent = 'Loading...';
        const pfUrl = 'dashboard_ajax/pending_files.php?_ts=' + Date.now() + (LOC_ID > 0 ? '&location_id=' +
            LOC_ID : '');
        fetch(pfUrl)
            .then(r => r.json())
            .then(d => {
                if (d && d.success) {
                    const cnt = Number(d.pending_count) || 0;
                    pendingFilesEl.setAttribute('data-count', cnt);
                    let current = 0;
                    const step = Math.max(cnt / 50, 1);
                    const anim = setInterval(() => {
                        current += step;
                        if (current >= cnt) {
                            current = cnt;
                            clearInterval(anim);
                        }
                        pendingFilesEl.textContent = Math.round(current).toLocaleString();
                    }, 15);
                } else {
                    pendingFilesEl.textContent = '0';
                }
            })
            .catch(() => {
                pendingFilesEl.textContent = '0';
            });
    }

    // Fetch type-wise lease counts filtered by location
    const typeUrl = 'dashboard_ajax/collection_breakdown.php?_ts=' + Date.now() + (LOC_ID > 0 ?
        '&location_id=' + LOC_ID : '');
    fetch(typeUrl)
        .then(r => r.json())
        .then(d => {
            if (d && d.success) {
                const data = (d.types || []).map(t => ({
                    name: t.name || 'Unknown',
                    y: Number(t.count) || 0
                })).filter(pt => pt.y > 0);
                typeChart.series[0].setData(data, true);
            } else {
                typeChart.series[0].setData([], true);
            }
        })
        .catch(() => {
            typeChart.series[0].setData([], true);
        });

    // Add hover effects to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
.card {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.text-primary {
    color: #4e73df !important;
}

.text-success {
    color: #1cc88a !important;
}

.text-info {
    color: #36b9cc !important;
}

.text-warning {
    color: #f6c23e !important;
}

.bg-primary {
    background: linear-gradient(45deg, #4e73df, #224abe);
}

.bg-success {
    background: linear-gradient(45deg, #1cc88a, #169b6b);
}

.bg-info {
    background: linear-gradient(45deg, #36b9cc, #2c9faf);
}

.bg-warning {
    background: linear-gradient(45deg, #f6c23e, #dda20a);
}

.card-footer {
    background-color: rgba(0, 0, 0, 0.1);
    border-top: none;
}

.main-header h4 {
    color: #5a5c69;
    font-weight: 700;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

/* Additional improvements from simple dashboard */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2) !important;
}

.content-wrapper {
    background-color: #f8f9fc;
}

/* Ensure charts are responsive */
#cashCollectionChart,
#typeChart,
#comparisonChart {
    min-height: 300px;
}

/* Loading animation for charts */
.chart-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 400px;
    color: #858796;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .h5 {
        font-size: 1.1rem;
    }

    .text-xs {
        font-size: 0.7rem;
    }

    #cashCollectionChart,
    #typeChart,
    #comparisonChart {
        height: 300px !important;
    }
}

/* Custom no-gutters styling */
.no-gutters {
    margin-left: 11px;
}
</style>

<?php include 'footer.php'; ?>
