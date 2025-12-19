<?php
require_once dirname(__DIR__, 2) . '/auth.php';
// Partial: Land Information form (embedded in long_term_lease_open.php)
?>
<form id="ltl_land_form" class="mb-3">
    <input type="hidden" id="ltl_ben_id" name="ben_id" value="<?php echo isset($ben_id) ? (int)$ben_id : 0; ?>">
    <input type="hidden" id="ltl_land_id" name="land_id" value="">
    <div class="row">
        <!-- Column 1 -->
        <div class="col-md-3">
            <div class="form-group">
                <label for="ltl_ds_id">DS Division</label>
                <select id="ltl_ds_id" name="ds_id" class="form-control" required>
                    <?php 
            $dsdivs = mysqli_query($con, "SELECT c_id, client_name FROM client_registration WHERE c_id='$location_id' ");
            while($ds = mysqli_fetch_assoc($dsdivs)) {
              echo '<option value="'.htmlspecialchars($ds['c_id']).'">'.htmlspecialchars($ds['client_name']).'</option>';
            }
          ?>
                </select>
            </div>
        </div>
        <!-- Column 2 -->
        <div class="col-md-3">
            <div class="form-group">
                <label for="ltl_gn_id">GN Division</label>
                <select id="ltl_gn_id" name="gn_id" class="form-control" required>
                    <option value="">Select GN Division</option>
                    <?php 
            $gns = mysqli_query($con, "SELECT gn_id, gn_name, gn_no FROM gn_division Where c_id='$location_id' ORDER BY gn_name");
            while($gn = mysqli_fetch_assoc($gns)) {
              echo '<option value="'.htmlspecialchars($gn['gn_id']).'">'.htmlspecialchars($gn['gn_name']).' ('.htmlspecialchars($gn['gn_no']).')</option>';
            }
          ?>
                </select>
            </div>
        </div>
        <!-- Column 4 -->
        <div class="col-md-3">
            <div class="form-group">
                <label for="ltl_land_address">Land Address</label>
                <input type="text" id="ltl_land_address" name="land_address" class="form-control"
                    placeholder="Enter address" required>
            </div>
        </div>
        <!-- Column 5: Development Status -->
        <div class="col-md-3">
            <div class="form-group">
                <label for="ltl_developed_status">Development Status</label>
                <select id="ltl_developed_status" name="developed_status" class="form-control">
                    <option value="Not Developed">Not Developed</option>
                    <option value="Partially Developed">Partially Developed</option>
                    <option value="Developed">Developed</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Sketch Plan No -->
        <div class="col-md-2">
            <div class="form-group">
                <label for="ltl_sketch_plan_no">Sketch Plan No</label>
                <input type="text" id="ltl_sketch_plan_no" name="sketch_plan_no" class="form-control">
            </div>
        </div>
        <!-- PLC Plan No -->
        <div class="col-md-2">
            <div class="form-group">
                <label for="ltl_plc_plan_no">PLC Plan No</label>
                <input type="text" id="ltl_plc_plan_no" name="plc_plan_no" class="form-control">
            </div>
        </div>
        <!-- Survey Plan No -->
        <div class="col-md-2">
            <div class="form-group">
                <label for="ltl_survey_plan_no">Survey Plan No</label>
                <input type="text" id="ltl_survey_plan_no" name="survey_plan_no" class="form-control">
            </div>
        </div>
        <!-- Extent (moved here) -->
        <div class="col-md-2">
            <div class="form-group">
                <label for="ltl_extent">Extent</label>
                <input type="number" step="any" id="ltl_extent" name="extent" class="form-control"
                    placeholder="Enter extent">
            </div>
        </div>
        <!-- Extent Unit -->
        <div class="col-md-2">
            <div class="form-group">
                <label for="ltl_extent_unit">Unit</label>
                <select class="form-control" id="ltl_extent_unit" name="extent_unit">
                    <option value="hectares">Hectares</option>
                    <option value="sqft">Square feet</option>
                    <option value="sqyd">Square yards</option>
                    <option value="perch">Perch</option>
                    <option value="rood">Rood</option>
                    <option value="acre">Acre</option>
                    <option value="cent">Cent</option>
                    <option value="ground">Ground</option>
                    <option value="sqm">Square meters</option>
                </select>
            </div>
        </div>
        <!-- Extent in Hectares (readonly) -->
        <div class="col-md-2">
            <div class="form-group">
                <label for="ltl_extent_ha">Hectares</label>
                <input type="text" id="ltl_extent_ha" name="extent_ha" class="form-control" placeholder="Ha" readonly>
            </div>
        </div>
        <!-- Hidden boundary JSON holder (filled on submit) -->
        <input type="hidden" id="ltl_landBoundary" name="landBoundary" value="">
    </div>

    <div class="row">
        <div class="col-md-6">
            <label class="d-block">Boundary (Lat / Lng)</label>
            <div id="ltl_boundary_list"></div>
            <button type="button" id="ltl_add_line" class="btn btn-outline-secondary btn-sm mt-2"><i
                    class="fa fa-plus"></i> Add Line</button>
        </div>
        <div class="col-md-6">
            <div id="ltl_map" style="height:300px; border:1px solid #ddd;"></div>
            <small class="text-muted">Click on the map to append a coordinate pair. Use the list to edit values. Polygon
                preview updates automatically.</small>
        </div>
    </div>

    <div class="text-right mt-3">
        <?php if (hasPermission(21)): ?>
        <button type="button" class="btn btn-outline-primary mr-2" id="ltl_edit_btn"><i class="fa fa-edit"></i>
            Edit</button>
        <?php endif; ?>
        <button type="submit" class="btn btn-success" id="ltl_save_btn"><i class="fa fa-save"></i> Save Land
            Information</button>
    </div>
</form>

<script>
(function() {
    function start() {
        var CURRENT_BEN_ID = <?php echo isset($ben_id) ? (int)$ben_id : 0; ?>;
        // DS boundary from header.php ($coordinates JSON for current area)
        var CLIENT_NAME = <?php echo json_encode(isset($client_name)?$client_name:''); ?>;
        var COORDS = <?php echo isset($coordinates) ? $coordinates : '[]'; ?>;
        // Unit conversions to hectares (same factors as land_registration.php)


        var unitToSqm = {
            hectares: 10000, // 1 ha = 10,000 m²
            sqm: 1, // 1 m²
            sqft: 0.09290304, // 1 ft² = 0.09290304 m²
            sqyd: 0.83612736, // 1 yd² = 0.83612736 m²
            perch: 25.29285264, // 1 perch = 25.29285264 m²  ✅ (your code had 10×)
            rood: 1011.7141056, // 1 rood = 1011.7141056 m²
            acre: 4046.8564224, // 1 acre = 4046.8564224 m²
            cent: 40.468564224, // 1 cent = 40.468564224 m²
            ground: 222.967296 // 1 ground = 2400 ft² = 222.967296 m² ✅ (yours was way off)
        };

        function updateExtentHectares() {
            var area = parseFloat(document.getElementById('ltl_extent')?.value || '') || 0;
            var unit = document.getElementById('ltl_extent_unit')?.value || 'hectares';

            var sqm = area * (unitToSqm[unit] || 0);
            var ha = sqm / 10000;

            var out = document.getElementById('ltl_extent_ha');
            if (out) out.value = area ? ha.toFixed(6) : '';
        }


        // var unitToHectares = {
        //   hectares: 1,
        //   sqft: 0.0000092903,
        //   sqyd: 0.0000836127,
        //   perch: 0.0252929,
        //   rood: 0.1011714,
        //   acre: 0.4046856,
        //   cent: 0.00404686,
        //   ground: 0.0023237,
        //   sqm: 0.0001
        // };
        // function updateExtentHectares(){
        //   var area = parseFloat(document.getElementById('ltl_extent')?.value || '') || 0;
        //   var unit = document.getElementById('ltl_extent_unit')?.value || 'hectares';
        //   var ha = area * (unitToHectares[unit] || 1);
        //   var out = document.getElementById('ltl_extent_ha');
        //   if (out) out.value = area ? ha.toFixed(6) : '';
        // }
        // Select2 for DS/GN
        if (window.jQuery) {
            $('#ltl_ds_id').select2({
                width: '100%'
            });
            $('#ltl_gn_id').select2({
                width: '100%'
            });

            $('#ltl_ds_id').on('change', function() {
                var ds = $(this).val();
                if (ds) {
                    $.get('ajax/get_gn_divisions.php', {
                        c_id: ds
                    }, function(html) {
                        $('#ltl_gn_id').html(html).val('').trigger('change');
                    });
                }
            });
        }

        // Edit/view mode toggle
        var EDITABLE = true;

        function setEditable(flag) {
            EDITABLE = !!flag;
            var $form = $('#ltl_land_form');
            // Disable/enable all inputs/selects/textareas except hidden ids
            $form.find('input:not([type=hidden]), select, textarea').prop('disabled', !EDITABLE);
            // Keep hidden ids always enabled
            $('#ltl_ben_id, #ltl_land_id').prop('disabled', false);
            // Buttons
            $('#ltl_save_btn').prop('disabled', !EDITABLE);
            $('#ltl_edit_btn').prop('disabled', false);
            // Boundary list input fields and Add Line
            $('#ltl_boundary_list input').prop('disabled', !EDITABLE);
            $('#ltl_add_line').prop('disabled', !EDITABLE);
            // Select2 controls honor the select disabled state, but force refresh
            $('#ltl_ds_id, #ltl_gn_id').trigger('change.select2');
        }

        // Boundary handling
        var boundaryPoints = [];

        function ensureMinPoints(n) {
            while (boundaryPoints.length < n) {
                boundaryPoints.push({
                    lat: '',
                    lng: ''
                });
            }
        }

        function renderBoundaryList() {
            var c = document.getElementById('ltl_boundary_list');
            if (!c) return;
            // Build a small table with two columns: Latitude and Longitude
            var html = '<div class="table-responsive"><table class="table table-sm mb-2">' +
                '<thead><tr><th style="width:50%">Latitude</th><th style="width:50%">Longitude</th></tr></thead><tbody>';
            boundaryPoints.forEach(function(p, idx) {
                html += '<tr>' +
                    '<td><input type="text" class="form-control form-control-sm ltl-lat" placeholder="Latitude" data-index="' +
                    idx + '" value="' + (p.lat || '') + '"></td>' +
                    '<td><input type="text" class="form-control form-control-sm ltl-lng" placeholder="Longitude" data-index="' +
                    idx + '" value="' + (p.lng || '') + '"></td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>';
            c.innerHTML = html;
            // Attach change handlers
            document.querySelectorAll('#ltl_boundary_list .ltl-lat').forEach(function(inp) {
                inp.addEventListener('input', function() {
                    var i = parseInt(this.getAttribute('data-index'), 10);
                    if (!boundaryPoints[i]) boundaryPoints[i] = {
                        lat: '',
                        lng: ''
                    };
                    boundaryPoints[i].lat = this.value.trim();
                    drawPolygon();
                });
            });
            document.querySelectorAll('#ltl_boundary_list .ltl-lng').forEach(function(inp) {
                inp.addEventListener('input', function() {
                    var i = parseInt(this.getAttribute('data-index'), 10);
                    if (!boundaryPoints[i]) boundaryPoints[i] = {
                        lat: '',
                        lng: ''
                    };
                    boundaryPoints[i].lng = this.value.trim();
                    drawPolygon();
                });
            });
        }

        document.getElementById('ltl_add_line').addEventListener('click', function() {
            if (!EDITABLE) return;
            boundaryPoints.push({
                lat: '',
                lng: ''
            });
            renderBoundaryList();
            // Re-apply disabled state to new inputs if needed
            $('#ltl_boundary_list input').prop('disabled', !EDITABLE);
        });

        // Leaflet map init
        var map, polygonLayer, dsLayer;

        function initMap() {
            if (map) return;
            map = L.map('ltl_map').setView([8.55, 81.2], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: ''
            }).addTo(map);
            // Draw DS boundary in red if coordinates are available
            try {
                var dsGeo = {
                    type: 'Feature',
                    properties: {
                        NAME_2: CLIENT_NAME,
                        TYPE_2: 'Division'
                    },
                    geometry: {
                        type: 'MultiPolygon',
                        coordinates: COORDS
                    }
                };
                dsLayer = L.geoJSON(dsGeo, {
                    style: {
                        color: '#d32f2f',
                        weight: 2,
                        fillColor: '#ef5350',
                        fillOpacity: 0.05
                    }
                }).addTo(map);
                if (dsLayer && dsLayer.getBounds && dsLayer.getBounds().isValid()) {
                    map.fitBounds(dsLayer.getBounds());
                }
            } catch (e) {
                /* ignore if malformed */
            }
            map.on('click', function(e) {
                if (!EDITABLE) return;
                var lat = e.latlng.lat.toFixed(6);
                var lng = e.latlng.lng.toFixed(6);
                // Fill the first incomplete row (prefer within the first 4). Set both lat and lng for that row.
                var indexToFill = -1;
                var limit = Math.max(4, boundaryPoints.length);
                for (var i = 0; i < limit; i++) {
                    if (!boundaryPoints[i] || !boundaryPoints[i].lat || !boundaryPoints[i].lng) {
                        indexToFill = i;
                        break;
                    }
                }
                if (indexToFill === -1) {
                    // All existing rows complete: append a new one
                    boundaryPoints.push({
                        lat: lat,
                        lng: lng
                    });
                } else {
                    if (!boundaryPoints[indexToFill]) boundaryPoints[indexToFill] = {
                        lat: '',
                        lng: ''
                    };
                    boundaryPoints[indexToFill].lat = lat;
                    boundaryPoints[indexToFill].lng = lng;
                }
                renderBoundaryList();
                drawPolygon();
            });
            setTimeout(function() {
                map.invalidateSize();
            }, 200);
        }

        function drawPolygon() {
            if (!map) return;
            var pts = boundaryPoints
                .map(function(p) {
                    var lat = parseFloat(p.lat),
                        lng = parseFloat(p.lng);
                    return (isFinite(lat) && isFinite(lng)) ? [lat, lng] : null;
                })
                .filter(Boolean);
            if (polygonLayer) {
                polygonLayer.remove();
                polygonLayer = null;
            }
            if (pts.length >= 3) {
                polygonLayer = L.polygon(pts, {
                    color: '#1976d2',
                    weight: 2,
                    fillColor: '#64b5f6',
                    fillOpacity: 0.25
                }).addTo(map);
                try {
                    map.fitBounds(polygonLayer.getBounds(), {
                        padding: [10, 10]
                    });
                } catch (e) {}
            } else if (pts.length >= 2) {
                polygonLayer = L.polyline(pts, {
                    color: '#1976d2',
                    weight: 2
                }).addTo(map);
            }
        }

        // Serialize boundary to hidden input
        function serializeBoundary() {
            var pts = boundaryPoints
                .map(function(p) {
                    var lat = parseFloat(p.lat),
                        lng = parseFloat(p.lng);
                    return (isFinite(lat) && isFinite(lng)) ? {
                        lat: lat,
                        lng: lng
                    } : null;
                })
                .filter(Boolean);
            if (pts.length === 0) {
                // store 4 editable placeholders when nothing valid entered yet
                var placeholders = Array.from({
                    length: 4
                }).map(function() {
                    return {
                        lat: '',
                        lng: ''
                    };
                });
                document.getElementById('ltl_landBoundary').value = JSON.stringify(placeholders);
                return placeholders;
            }
            document.getElementById('ltl_landBoundary').value = JSON.stringify(pts);
            return pts;
        }

        // Try preload via URL land_id parameter
        function getQueryParam(k) {
            var params = new URLSearchParams(window.location.search);
            return params.get(k);
        }

        function preloadIfAny() {
            var lid = getQueryParam('land_id');
            var url = '';
            if (lid) {
                url = 'ltl_ajax/load_land.php?land_id=' + encodeURIComponent(lid);
            } else if (CURRENT_BEN_ID) {
                url = 'ltl_ajax/load_land.php?ben_id=' + encodeURIComponent(CURRENT_BEN_ID);
            } else {
                return;
            }
            fetch(url)
                .then(r => r.json())
                .then(function(resp) {
                    if (!resp || !resp.success) return;
                    var d = resp.data || {};
                    $('#ltl_land_id').val(d.land_id || '');
                    if (d.ben_id) {
                        $('#ltl_ben_id').val(d.ben_id);
                    }
                    $('#ltl_ds_id').val(d.ds_id || '').trigger('change');
                    // Load GN list then set
                    $.get('ajax/get_gn_divisions.php', {
                        c_id: d.ds_id
                    }, function(html) {
                        $('#ltl_gn_id').html(html);
                        $('#ltl_gn_id').val(d.gn_id || '').trigger('change');
                    });
                    $('#ltl_land_address').val(d.land_address || '');
                    // Developed status (default to Not Developed)
                    $('#ltl_developed_status').val(d.developed_status || 'Not Developed');
                    // Extent + unit + hectares
                    $('#ltl_extent').val(d.extent || '');
                    $('#ltl_extent_unit').val(d.extent_unit || 'hectares');
                    $('#ltl_extent_ha').val(d.extent_ha || '');
                    $('#ltl_sketch_plan_no').val(d.sketch_plan_no || '');
                    $('#ltl_plc_plan_no').val(d.plc_plan_no || '');
                    $('#ltl_survey_plan_no').val(d.survey_plan_no || '');
                    boundaryPoints = [];
                    try {
                        var arr = JSON.parse(d.landBoundary || '[]');
                        if (Array.isArray(arr)) {
                            arr.forEach(function(pt) {
                                boundaryPoints.push({
                                    lat: pt.lat,
                                    lng: pt.lng
                                });
                            });
                        }
                    } catch (e) {}
                    ensureMinPoints(4);
                    renderBoundaryList();
                    // If this is an existing record, default to read-only mode
                    if (d.land_id) {
                        setEditable(false);
                    }
                    $('#ltl_boundary_list input').prop('disabled', !EDITABLE);
                    updateExtentHectares();
                    initMap();
                    drawPolygon();
                });
        }

        // Submit handler
        document.getElementById('ltl_land_form').addEventListener('submit', function(e) {
            e.preventDefault();
            initMap();
            updateExtentHectares(); // ensure latest before serialize
            serializeBoundary();
            var form = $(this);
            var data = form.serialize();
            $('#ltl_save_btn').prop('disabled', true);
            $.post('ltl_ajax/save_land.php', data)
                .done(function(resp) {
                    if (resp && resp.success) {
                        if (resp.land_id) {
                            $('#ltl_land_id').val(resp.land_id);
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: resp.message || 'Land information saved',
                            timer: 1800,
                            showConfirmButton: false
                        });
                    } else {
                        var msg = (resp && resp.message) ? resp.message : 'Save failed';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: msg
                        });
                    }
                })
                .fail(function(xhr) {
                    var msg = 'Server error';
                    try {
                        msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                            xhr.statusText;
                    } catch (e) {}
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: msg
                    });
                })
                .always(function() {
                    $('#ltl_save_btn').prop('disabled', false);
                });
        });

        // Initialize on ready
        ensureMinPoints(4);
        renderBoundaryList();
        initMap();
        // Hook up extent conversions
        var ext = document.getElementById('ltl_extent');
        var unitSel = document.getElementById('ltl_extent_unit');
        if (ext) ext.addEventListener('input', updateExtentHectares);
        if (unitSel) unitSel.addEventListener('change', updateExtentHectares);
        updateExtentHectares();
        preloadIfAny();

        // Edit button toggles edit mode
        document.getElementById('ltl_edit_btn').addEventListener('click', function() {
            setEditable(true);
        });

        // Recompute map size when switching to Land tab
        document.querySelectorAll('#submenu-list a').forEach(function(a) {
            a.addEventListener('click', function() {
                var target = this.getAttribute('data-target');
                if (target === '#land-tab') {
                    setTimeout(function() {
                        if (map) {
                            map.invalidateSize();
                        }
                    }, 200);
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>


<!-- Leaflet for map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<style>
/* Use arrow pointer instead of hand/grab on the map for precise clicking */
.leaflet-container {
    cursor: default !important;
}

.leaflet-grab,
.leaflet-dragging .leaflet-grab {
    cursor: default !important;
}

.leaflet-marker-icon {
    cursor: default !important;
}

/* Optional: increase click target tolerance slightly */
#ltl_map {
    touch-action: manipulation;
}

/* Keep the map visible with a subtle border */
#ltl_map {
    border: 1px solid #ddd;
}

/* Two-column boundary table tidy spacing */
#ltl_boundary_list table td {
    padding: .25rem .5rem;
}

#ltl_boundary_list input {
    height: 30px;
}

#ltl_boundary_list thead th {
    font-weight: 600;
}
</style>