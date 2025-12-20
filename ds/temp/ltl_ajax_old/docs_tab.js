// (function(){
//   'use strict';

//   function toYMD(dt){
//     if (!dt) return '';
//     var m = String(dt).match(/^(\d{4}-\d{2}-\d{2})/);
//     return m ? m[1] : '';
//   }

//   function escapeHtml(s){
//     return String(s)
//       .replace(/&/g,'&amp;')
//       .replace(/</g,'&lt;')
//       .replace(/>/g,'&gt;')
//       .replace(/"/g,'&quot;');
//   }

//   function renderDocsTable(rows, md5){
//     var html = '';
//     html += '<div class="table-responsive">';
//     html += '<table class="table table-bordered table-sm" id="docs-table">';
//     html += '<thead class="thead-light"><tr>'+
//             '<th style="width:60px">No</th>'+
//             '<th style="width:340px">Document</th>'+
//             '<th style="width:280px">File</th>'+
//             '<th style="width:340px">Approval</th>'+
//             '<th style="width:80px">Actions</th>'+
//             '</tr></thead><tbody>';
//     if (!rows || !rows.length){
//       html += '<tr><td colspan="5" class="text-center text-muted">No document types configured.</td></tr>';
//     } else {
//       rows.forEach(function(r, idx){
//         var fileInfo = r.file || null;
//         var hasFile = !!(fileInfo && fileInfo.file_url);
//         var fileLink = hasFile ? '<a href="'+fileInfo.file_url+'" target="_blank">Open</a>' : '<span class="text-muted">No file</span>';
//         var delBtn = hasFile ? '<button type="button" class="btn btn-sm btn-outline-danger docs-del" data-id="'+fileInfo.id+'" data-doc="'+r.doc_type_id+'">Delete</button>' : '';
//         var approvalBlock = '';
//         var approvalRequired = String(r.approval_required) === '1';
//         if (approvalRequired){
//           var val = (fileInfo && fileInfo.approval_status) ? String(fileInfo.approval_status) : 'Pending';
//           var sub = toYMD(fileInfo && fileInfo.submitted_date);
//           var rec = toYMD(fileInfo && fileInfo.received_date);
//           var ref = (fileInfo && fileInfo.referance_no) ? escapeHtml(fileInfo.referance_no) : '';
//           var approvalSel = '<select class="form-control form-control-sm docs-approval" data-doc="'+r.doc_type_id+'">'+
//                             '<option '+(val==='Pending'?'selected':'')+'>Pending</option>'+
//                             '<option '+(val==='Approved'?'selected':'')+'>Approved</option>'+
//                             '<option '+(val==='Rejected'?'selected':'')+'>Rejected</option>'+
//                             '</select>';
//           var saveAllBtn = (hasFile ? ' data-id="'+fileInfo.id+'"' : '');
//           var dates = '<div class="d-flex mt-1 align-items-end" style="gap:8px; flex-wrap: wrap;">'+
//                       '<div><small>Submitted</small><input type="date" class="form-control form-control-sm docs-submitted-date" value="'+(sub||'')+'" /></div>'+
//                       '<div><small>Received</small><input type="date" class="form-control form-control-sm docs-received-date" value="'+(rec||'')+'" /></div>'+
//                       '<div style="min-width:180px"><small>Reference No</small><input type="text" class="form-control form-control-sm docs-ref-no" value="'+ref+'" placeholder="Enter reference" /></div>'+
//                       '<div><button type="button" class="btn btn-sm btn-primary docs-save-all"'+saveAllBtn+' data-doc="'+r.doc_type_id+'">Save</button></div>'+
//                       '</div>';
//           approvalBlock = '<div style="min-width:300px">'+approvalSel+dates+'</div>';
//         } else {
//           var ref2 = (fileInfo && fileInfo.referance_no) ? escapeHtml(fileInfo.referance_no) : '';
//           var saveAllBtn2 = (hasFile ? ' data-id="'+fileInfo.id+'"' : '');
//           var refBlock = '<div class="d-flex mt-1 align-items-end" style="gap:8px; flex-wrap: wrap;">'+
//                          '<div style="min-width:220px"><small>Reference No</small><input type="text" class="form-control form-control-sm docs-ref-no" value="'+ref2+'" placeholder="Enter reference" /></div>'+
//                          '<div><button type="button" class="btn btn-sm btn-primary docs-save-all"'+saveAllBtn2+' data-doc="'+r.doc_type_id+'">Save</button></div>'+
//                          '</div>';
//           approvalBlock = '<div style="min-width:300px">'+refBlock+'</div>';
//         }
//         var fileControl = '';
//         if (!hasFile) {
//           fileControl = '<div class="d-flex align-items-center" style="gap:6px;">'+
//                         '<input type="file" class="form-control form-control-sm docs-file" accept="application/pdf,image/png,image/jpeg" data-doc="'+r.doc_type_id+'" style="max-width:200px;" />'+
//                         '<button type="button" class="btn btn-sm btn-primary docs-upload" data-doc="'+r.doc_type_id+'">Upload</button>'+
//                         '</div>';
//         }
//         var actions = delBtn;
//         if (r.print_url) {
//           var joiner = (r.print_url.indexOf('?') !== -1) ? '&' : '?';
//           var urlTA = r.print_url + joiner + 'id=' + encodeURIComponent(md5) + '&language=TA';
//           var urlSN = r.print_url + joiner + 'id=' + encodeURIComponent(md5) + '&language=SN';
//           actions += (actions? ' ' : '') + '<a class="btn btn-sm btn-success" target="_blank" href="'+urlTA+'"><i class="fa fa-print"></i> TA</a> '+
//                      '<a class="btn btn-sm btn-success" target="_blank" href="'+urlSN+'"><i class="fa fa-print"></i> SN</a>';
//         }
//         // row
//         html += '<tr data-doc="'+r.doc_type_id+'">'+
//                 '<td>'+(r.order_no || (idx+1))+'</td>'+
//                 '<td>'+ (r.doc_name ? escapeHtml(r.doc_name) : '') +'</td>'+
//                 '<td>'+ fileControl + '<div class="small mt-1">'+fileLink+'</div>'+'</td>'+
//                 '<td>'+approvalBlock+'</td>'+
//                 '<td>'+actions+'</td>'+
//                 '</tr>';
//       });
//     }
//     html += '</tbody></table></div>';
//     return html;
//   }

//   function wireDocsHandlers(container, md5){
//     // Upload handler
//     container.querySelectorAll('.docs-upload').forEach(function(btn){
//       btn.addEventListener('click', function(){
//         var docType = this.getAttribute('data-doc');
//         var row = this.closest('tr');
//         var fileInput = row.querySelector('.docs-file');
//         var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
//         var approvalEl = row.querySelector('.docs-approval');
//         var approval = approvalEl ? approvalEl.value : '';
//         var submitted = row.querySelector('.docs-submitted-date');
//         var received = row.querySelector('.docs-received-date');
//         var refEl = row.querySelector('.docs-ref-no');
//         var submittedVal = submitted ? submitted.value : '';
//         var receivedVal = received ? received.value : '';
//         var refVal = refEl ? refEl.value : '';
//         if (!file){ Swal.fire({ icon:'warning', title:'Choose a file', timer:1300, showConfirmButton:false}); return; }
//         var allowed = ['application/pdf','image/png','image/jpeg'];
//         if (allowed.indexOf(file.type) === -1){ Swal.fire({ icon:'error', title:'Invalid file type', text:'Only PDF, PNG, JPG are allowed.'}); return; }
//         var fd = new FormData();
//         fd.append('doc_type_id', docType);
//         fd.append('approval_status', approval);
//         if (submitted) fd.append('submitted_date', submittedVal);
//         if (received) fd.append('received_date', receivedVal);
//         if (refEl) fd.append('referance_no', refVal);
//         fd.append('file', file);
//         fd.append('id', md5);
//         var uploadBtn = this; uploadBtn.disabled = true; uploadBtn.textContent = 'Uploading...';
//         fetch('ltl_ajax/upload_document.php', { method:'POST', body: fd })
//           .then(function(r){ return r.json(); })
//           .then(function(resp){
//             if (resp && resp.success){
//               Swal.fire({ icon:'success', title:'Uploaded', timer:1400, showConfirmButton:false });
//               refreshSingleDocRow(container, md5, docType);
//             } else {
//               Swal.fire({ icon:'error', title:'Upload failed', text: (resp && resp.message) || 'Server error' });
//             }
//           })
//           .catch(function(){ Swal.fire({ icon:'error', title:'Upload failed', text:'Network error' }); })
//           .finally(function(){ uploadBtn.disabled = false; uploadBtn.textContent = 'Upload'; });
//       });
//     });

//     // Delete handler
//     container.querySelectorAll('.docs-del').forEach(function(btn){
//       btn.addEventListener('click', function(){
//         var id = this.getAttribute('data-id');
//         var docType = this.getAttribute('data-doc');
//         var self = this;
//         Swal.fire({ icon:'warning', title:'Delete file?', showCancelButton:true }).then(function(res){
//           if (!res.isConfirmed) return;
//           self.disabled = true; self.textContent = 'Deleting...';
//           fetch('ltl_ajax/delete_document.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'id='+encodeURIComponent(id) })
//             .then(function(r){ return r.json(); })
//             .then(function(resp){
//               if (resp && resp.success){
//                 Swal.fire({ icon:'success', title:'Deleted', timer:1100, showConfirmButton:false });
//                 refreshSingleDocRow(container, md5, docType);
//               } else {
//                 Swal.fire({ icon:'error', title:'Delete failed', text:(resp && resp.message)||'Server error' });
//               }
//             })
//             .catch(function(){ Swal.fire({ icon:'error', title:'Delete failed', text:'Network error' }); })
//             .finally(function(){ self.disabled = false; self.textContent = 'Delete'; });
//         });
//       });
//     });

//     // Save-all handler (status+dates+reference)
//     container.querySelectorAll('.docs-save-all').forEach(function(btn){
//       btn.addEventListener('click', function(){
//         var idAttr = this.getAttribute('data-id');
//         var id = idAttr && /\d+/.test(idAttr) ? idAttr : '0';
//         var docType = this.getAttribute('data-doc');
//         var row = this.closest('tr');
//         var approvalEl = row.querySelector('.docs-approval');
//         var submitted = row.querySelector('.docs-submitted-date');
//         var received = row.querySelector('.docs-received-date');
//         var refEl = row.querySelector('.docs-ref-no');
//         var approval = approvalEl ? approvalEl.value : '';
//         var submittedVal = submitted ? submitted.value : '';
//         var receivedVal = received ? received.value : '';
//         var refVal = refEl ? refEl.value : '';
//         var self = this; self.disabled = true; self.textContent = 'Saving...';
//         var fd = new URLSearchParams();
//         fd.set('id', id);
//         fd.set('doc_type_id', docType);
//         fd.set('approval_status', approval);
//         fd.set('submitted_date', submittedVal);
//         fd.set('received_date', receivedVal);
//         fd.set('referance_no', refVal);
//         fd.set('md5', md5);
//         fetch('ltl_ajax/update_document_meta.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
//           .then(function(r){ return r.json(); })
//           .then(function(resp){
//             if (resp && resp.success){
//               Swal.fire({ icon:'success', title:'Saved', timer:1100, showConfirmButton:false });
//               if (resp.data && resp.data.id) { self.setAttribute('data-id', String(resp.data.id)); }
//               if (docType) { refreshSingleDocRow(container, md5, docType); }
//             } else {
//               Swal.fire({ icon:'error', title:'Save failed', text:(resp && resp.message)||'Server error' });
//             }
//           })
//           .catch(function(){ Swal.fire({ icon:'error', title:'Save failed', text:'Network error' }); })
//           .finally(function(){ self.disabled = false; self.textContent = 'Save'; });
//       });
//     });
//   }

//   function refreshSingleDocRow(container, md5, docType){
//     var url = 'ltl_ajax/list_documents.php?id=' + encodeURIComponent(md5) + '&_one=' + encodeURIComponent(docType) + '&_ts=' + Date.now();
//     fetch(url)
//       .then(function(r){ return r.json(); })
//       .then(function(resp){
//         if (!resp || !resp.success || !resp.data || !resp.data.length) return;
//         var rowData = resp.data[0];
//         var tbody = container.querySelector('#docs-table tbody');
//         var row = tbody.querySelector('tr[data-doc="'+docType+'"]');
//         if (!row) return;
//         var tmp = document.createElement('tbody');
//         tmp.innerHTML = renderDocsTable([rowData], md5).replace(/^[\s\S]*<tbody>|<\/tbody>[\s\S]*$/g,'');
//         var newRow = tmp.firstElementChild;
//         row.parentNode.replaceChild(newRow, row);
//         wireDocsHandlers(container, md5);
//       })
//       .catch(function(){ /* ignore */ });
//   }

//   function loadDocsOnce(md5){
//     var container = document.getElementById('docs-container');
//     if (!container || container.getAttribute('data-loaded') === '1') return;
//     container.innerHTML = '<div style="text-align:center;padding:16px">\
//       <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />\
//     </div>';
//     var url = 'ltl_ajax/list_documents.php?id=' + encodeURIComponent(md5) + '&_ts=' + Date.now();
//     fetch(url)
//       .then(function(r){ return r.json(); })
//       .then(function(resp){
//         if (!resp || !resp.success){ container.innerHTML = '<div class="text-danger">Failed to load documents.</div>'; return; }
//         container.innerHTML = renderDocsTable(resp.data || [], md5);
//         container.setAttribute('data-loaded','1');
//         wireDocsHandlers(container, md5);
//       })
//       .catch(function(){ container.innerHTML = '<div class="text-danger">Failed to load documents.</div>'; });
//   }

//   window.LTLDocs = {
//     init: function(md5){ loadDocsOnce(md5); }
//   };
// })();


(function(){
  'use strict';

  function toYMD(dt){
    if (!dt) return '';
    var m = String(dt).match(/^(\d{4}-\d{2}-\d{2})/);
    return m ? m[1] : '';
  }

  function escapeHtml(s){
    return String(s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
  }

  /* -----------------------------------------------------
     RENDER TABLE – WITHOUT APPROVAL COLUMN
  ----------------------------------------------------- */
  function renderDocsTable(rows, md5){
    var html = '';
    html += '<div class="table-responsive">';
    html += '<table class="table table-bordered table-sm" id="docs-table">';
    html += '<thead class="thead-light"><tr>'+
            '<th style="width:60px">No</th>'+
            '<th style="width:340px">Document</th>'+
            '<th style="width:280px">File</th>'+
            '<th style="width:80px">Actions</th>'+
            '</tr></thead><tbody>';

    if (!rows || !rows.length){
      html += '<tr><td colspan="4" class="text-center text-muted">No document types configured.</td></tr>';
    } else {
      rows.forEach(function(r, idx){
        var fileInfo = r.file || null;
        var hasFile = !!(fileInfo && fileInfo.file_url);

        var fileLink = hasFile
          ? '<a href="'+fileInfo.file_url+'" target="_blank">Open</a>'
          : '<span class="text-muted">No file</span>';

        var delBtn = hasFile
          ? '<button type="button" class="btn btn-sm btn-outline-danger docs-del" data-id="'+fileInfo.id+'" data-doc="'+r.doc_type_id+'">Delete</button>'
          : '';

        // File Upload UI
        var fileControl = '';
        if (!hasFile) {
          fileControl =
            '<div class="d-flex align-items-center" style="gap:6px;">'+
            '<input type="file" class="form-control form-control-sm docs-file" accept="application/pdf,image/png,image/jpeg" data-doc="'+r.doc_type_id+'" style="max-width:200px;" />'+
            '<button type="button" class="btn btn-sm btn-primary docs-upload" data-doc="'+r.doc_type_id+'">Upload</button>'+
            '</div>';
        }

        var actions = delBtn;

        // PRINT buttons if available
        if (r.print_url) {
          var joiner = (r.print_url.indexOf('?') !== -1) ? '&' : '?';
          var urlTA = r.print_url + joiner + 'id=' + encodeURIComponent(md5) + '&language=TA';
          var urlSN = r.print_url + joiner + 'id=' + encodeURIComponent(md5) + '&language=SN';
          actions += (actions? ' ' : '') +
                     '<a class="btn btn-sm btn-success" target="_blank" href="'+urlTA+'"><i class="fa fa-print"></i> TA</a> '+
                     '<a class="btn btn-sm btn-success" target="_blank" href="'+urlSN+'"><i class="fa fa-print"></i> SN</a>';
        }

        // Row (NO APPROVAL COLUMN)
        html += '<tr data-doc="'+r.doc_type_id+'">'+
                '<td>'+(r.order_no || (idx+1))+'</td>'+
                '<td>'+ (r.doc_name ? escapeHtml(r.doc_name) : '') +'</td>'+
                '<td>'+ fileControl + '<div class="small mt-1">'+fileLink+'</div>'+'</td>'+
                '<td>'+actions+'</td>'+
                '</tr>';
      });
    }

    html += '</tbody></table></div>';
    return html;
  }

  /* -----------------------------------------------------
     EVENT HANDLERS – UPLOAD & DELETE ONLY (NO SAVE)
  ----------------------------------------------------- */
  function wireDocsHandlers(container, md5){

    // Upload
    container.querySelectorAll('.docs-upload').forEach(function(btn){
      btn.addEventListener('click', function(){
        var docType = this.getAttribute('data-doc');
        var row = this.closest('tr');
        var fileInput = row.querySelector('.docs-file');
        var file = fileInput && fileInput.files[0] ? fileInput.files[0] : null;

        if (!file){
          Swal.fire({ icon:'warning', title:'Choose a file', timer:1300, showConfirmButton:false});
          return;
        }

        var allowed = ['application/pdf','image/png','image/jpeg'];
        if (allowed.indexOf(file.type) === -1){
          Swal.fire({ icon:'error', title:'Invalid file type', text:'Only PDF, PNG, JPG allowed.'});
          return;
        }

        var fd = new FormData();
        fd.append('doc_type_id', docType);
        fd.append('file', file);
        fd.append('id', md5);

        var uploadBtn = this;
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading...';

        fetch('ltl_ajax/upload_document.php', { method:'POST', body: fd })
          .then(r => r.json())
          .then(resp => {
            if (resp && resp.success){
              Swal.fire({ icon:'success', title:'Uploaded', timer:1400, showConfirmButton:false });
              refreshSingleDocRow(container, md5, docType);
            } else {
              Swal.fire({ icon:'error', title:'Upload failed', text: resp.message || 'Server error' });
            }
          })
          .catch(() => Swal.fire({ icon:'error', title:'Upload failed', text:'Network error' }))
          .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload';
          });
      });
    });

    // Delete
    container.querySelectorAll('.docs-del').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = this.getAttribute('data-id');
        var docType = this.getAttribute('data-doc');
        var self = this;

        Swal.fire({ icon:'warning', title:'Delete file?', showCancelButton:true })
          .then(res => {
            if (!res.isConfirmed) return;

            self.disabled = true;
            self.textContent = 'Deleting...';

            fetch('ltl_ajax/delete_document.php', {
              method:'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body: 'id='+encodeURIComponent(id)
            })
              .then(r => r.json())
              .then(resp => {
                if (resp && resp.success){
                  Swal.fire({ icon:'success', title:'Deleted', timer:1100, showConfirmButton:false });
                  refreshSingleDocRow(container, md5, docType);
                } else {
                  Swal.fire({ icon:'error', title:'Delete failed', text: resp.message || 'Server error' });
                }
              })
              .catch(() => Swal.fire({ icon:'error', title:'Delete failed', text:'Network error' }))
              .finally(() => {
                self.disabled = false;
                self.textContent = 'Delete';
              });
          });
      });
    });

  }

  /* -----------------------------------------------------
     REFRESH A SINGLE DOC ROW
  ----------------------------------------------------- */
  function refreshSingleDocRow(container, md5, docType){
    var url = 'ltl_ajax/list_documents.php?id=' + encodeURIComponent(md5) +
              '&_one=' + encodeURIComponent(docType) +
              '&_ts=' + Date.now();

    fetch(url)
      .then(r => r.json())
      .then(resp => {
        if (!resp || !resp.success || !resp.data || !resp.data.length) return;

        var rowData = resp.data[0];
        var tbody = container.querySelector('#docs-table tbody');
        var row = tbody.querySelector('tr[data-doc="'+docType+'"]');
        if (!row) return;

        // Generate single row from table builder
        var tmp = document.createElement('tbody');
        tmp.innerHTML = renderDocsTable([rowData], md5).replace(/^[\s\S]*<tbody>|<\/tbody>[\s\S]*$/g,'');

        var newRow = tmp.firstElementChild;
        row.parentNode.replaceChild(newRow, row);

        wireDocsHandlers(container, md5);
      });
  }

  /* -----------------------------------------------------
     INITIAL LOAD
  ----------------------------------------------------- */
  function loadDocsOnce(md5){
    var container = document.getElementById('docs-container');
    if (!container || container.getAttribute('data-loaded') === '1') return;

    container.innerHTML =
      '<div style="text-align:center;padding:16px">'+
      '<img src="../img/Loading_icon.gif" style="width:96px;height:auto" />'+
      '</div>';

    fetch('ltl_ajax/list_documents.php?id=' + encodeURIComponent(md5) + '&_ts=' + Date.now())
      .then(r => r.json())
      .then(resp => {
        if (!resp || !resp.success){
          container.innerHTML = '<div class="text-danger">Failed to load documents.</div>';
          return;
        }

        container.innerHTML = renderDocsTable(resp.data || [], md5);
        container.setAttribute('data-loaded','1');
        wireDocsHandlers(container, md5);
      })
      .catch(() => {
        container.innerHTML = '<div class="text-danger">Failed to load documents.</div>';
      });
  }

  window.LTLDocs = {
    init: function(md5){ loadDocsOnce(md5); }
  };

})();

