const modalEl = document.getElementById('editPromodizerModal');
let branchBrandPairs = [];

// Helper to clean null/nchar
function cleanValue(value) {
    if (!value) return '';
    const trimmed = value.toString().trim();
    return (trimmed.toLowerCase() === 'null' || trimmed === '') ? '' : trimmed;
}

// =========================
// ELEMENT SAFETY CHECKS
// =========================
const reasonSelect = document.getElementById('editReasonUpdate');
const dateSeparatedRow = document.getElementById('rowDateSeparated');
const dateSeparatedInput = document.getElementById('editDateSeparated');

const dateReturnedRow = document.getElementById('rowDateReturned');
const dateReturnedInput = document.getElementById('editDateReturn');
const employmentStatusSelect = document.getElementById('editEmploymentStatus');

const startDateRow = document.getElementById('rowStartDate');
const startDateInput = document.getElementById('editStartDate');

const endDateRow = document.getElementById('rowEndDate');
const endDateInput = document.getElementById('editEndDate');

const editRovingField = document.getElementById('editRovingField');
const editRovingContainer = document.getElementById('editRovingContainer');

const editMultiBrandField = document.getElementById('editMultiBrandField');
const editMultiBrandContainer = document.getElementById('editMultiBrandContainer');

function toggleEmploymentDates() {
    if (!employmentStatusSelect) return;

    const value = (employmentStatusSelect.value || '').trim().toUpperCase();

    const shouldShow = value === "RELIEVER" || value === "SEASONAL";

    if (startDateRow) startDateRow.style.display = shouldShow ? "" : "none";
    if (endDateRow) endDateRow.style.display = shouldShow ? "" : "none";

    if (startDateInput) {
        startDateInput.disabled = !shouldShow;
        startDateInput.required = shouldShow;
        if (!shouldShow) startDateInput.value = '';
    }

    if (endDateInput) {
        endDateInput.disabled = !shouldShow;
        endDateInput.required = shouldShow;
        if (!shouldShow) endDateInput.value = '';
    }
}

// Guard (prevents crashes if modal DOM not loaded yet)
if (!reasonSelect || !dateSeparatedInput || !dateReturnedInput) {
    console.error('Modal elements not found. Check modal HTML.');
}

// =========================
// TOGGLE DATE SEPARATED
// =========================
const showDateSeparatedReasons = [
    "RESIGNED",
    "PULL-OUT / TERMINATED",
    "AWOL",
    "RETRENCHMENT",
    "END OF CONTRACT",
    "BLACKLISTED",
    "TRANSFER",
    "MATERNITY LEAVE"
];

function toggleDateSeparated() {
    if (!reasonSelect) return;

    const value = (reasonSelect.value || '').trim().toUpperCase();
    const shouldShow = showDateSeparatedReasons.includes(value);

    if (dateSeparatedRow) dateSeparatedRow.style.display = shouldShow ? "" : "none";

    if (dateSeparatedInput) {
        dateSeparatedInput.disabled = !shouldShow;
        dateSeparatedInput.required = shouldShow;

        if (!shouldShow) dateSeparatedInput.value = '';
    }
}

function safeArray(value) {
    if (!value) return [];
    if (Array.isArray(value)) return value;

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (Array.isArray(parsed)) return parsed;
        } catch (e) {}

        return value.split(',').map(v => v.trim()).filter(Boolean);
    }

    return [];
}

function syncMultiUI(status) {
    const value = (status || '').toUpperCase();

    if (editRovingField) editRovingField.classList.add('d-none');
    if (editMultiBrandField) editMultiBrandField.classList.add('d-none');

    if (value === 'MULTI BRANCH') {
        if (editRovingField) editRovingField.classList.remove('d-none');
    }

    if (value === 'MULTI BRAND') {
        if (editMultiBrandField) editMultiBrandField.classList.remove('d-none');
    }
}

// =========================
// TOGGLE DATE RETURNED
// =========================
function toggleDateReturned() {
    if (!reasonSelect) return;

    const value = (reasonSelect.value || '').trim().toUpperCase();
    const shouldShow = value === "MATERNITY LEAVE";

    if (dateReturnedRow) dateReturnedRow.style.display = shouldShow ? "" : "none";

    if (dateReturnedInput) {
        dateReturnedInput.disabled = !shouldShow;
        dateReturnedInput.required = shouldShow;

        if (!shouldShow) dateReturnedInput.value = '';
    }
}

function populateEditRoving(branches = []) {
    if (!editRovingContainer) return;

    let list = safeArray(branches);
    if (list.length === 0) list = [''];

    const uniqueBranches = [...new Set(branchBrandPairs.map(p => p.branch_name))];

    editRovingContainer.innerHTML = list.map((b, index) => {
        const isExisting = b !== ''; // 🔥 existing value from DB

        return `
        <div class="d-flex gap-2 mb-2 align-items-center roving-row">
            <select class="form-control" ${isExisting ? 'disabled' : ''}>
                <option value="">Select branch</option>
                ${uniqueBranches.map(branch => `
                    <option value="${branch}"
                        ${branch === b ? 'selected' : ''}>
                        ${branch}
                    </option>
                `).join('')}
            </select>

            <button type="button" class="btn btn-success btn-add-branch">+</button>

            <button type="button"
                class="btn btn-danger btn-remove-branch"
                style="${index === 0 ? 'display:none;' : ''}">
                −
            </button>
        </div>
        `;
    }).join('');
}

function populateEditBrands(brands = []) {
    if (!editMultiBrandContainer) return;

    let list = safeArray(brands);
    if (list.length === 0) list = [''];

    const uniqueBrands = [...new Set(branchBrandPairs.map(p => p.brand_name))];

    editMultiBrandContainer.innerHTML = list.map((b, index) => {
        const isExisting = b !== '';

        return `
        <div class="d-flex gap-2 mb-2 align-items-center brand-row">
            <select class="form-control" ${isExisting ? 'disabled' : ''}>
                <option value="">Select brand</option>
                ${uniqueBrands.map(brand => `
                    <option value="${brand}"
                        ${brand === b ? 'selected' : ''}>
                        ${brand}
                    </option>
                `).join('')}
            </select>

            <button type="button" class="btn btn-success btn-add-brand">+</button>

            <button type="button"
                class="btn btn-danger btn-remove-brand"
                style="${index === 0 ? 'display:none;' : ''}">
                −
            </button>
        </div>
        `;
    }).join('');
}

function collectAssignments() {

    const branches = Array.from(
        document.querySelectorAll('#editRovingSelect option:checked')
    ).map(opt => opt.value);

    const brands = Array.from(
        document.querySelectorAll('#editBrandSelect option:checked')
    ).map(opt => opt.value);

    return {
        branches,
        removedBranches: [],
        brands,
        removedBrands: []
    };
}

// =========================
// POPULATE MODAL
// =========================
document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', async () => {
        const id = row.dataset.id;
        const modal = new bootstrap.Modal(modalEl);

        try {
            const res = await fetch(`functions/get_employee.php?id=${id}`);
            const p = await res.json();

            if (!p || !p.id) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Employee not found' });
                return;
            }

            const employee = {
                id: p.id,
                first_name: p.first_name,
                last_name: p.last_name,
                branch: p.branch,
                brand: p.brand,
                assignment_date: p.assignment_date,
                last_assigned_by: p.last_assigned_by,
                status: p.status,
                date_of_return: p.date_of_return,
                date_separated: p.date_separated,
                employment_status: p.employment_status,
                sub_status: p.sub_status, // ✅ FIXED (was missing)
                remarks: p.remarks,
                last_updated_by: p.last_updated_by,
                reason_update: p.reason_for_update,
                date_hired: p.date_hired,
                updated_at: p.updated_at,
                start_date: p.start_date,
                end_date: p.end_date
            };

            // =========================
            // SAFE FIELD ASSIGNMENTS
            // =========================
            const el = (id) => document.getElementById(id);

            if (el('editPromodizerId')) el('editPromodizerId').value = employee.id;
            if (el('editFirstName')) el('editFirstName').value = cleanValue(employee.first_name);
            if (el('editLastName')) el('editLastName').value = cleanValue(employee.last_name);
            if (el('editBranch')) el('editBranch').value = cleanValue(employee.branch);
            if (el('editBrand')) el('editBrand').value = cleanValue(employee.brand);

            // ✅ FIXED DATE HANDLING (NO "-")
            if (el('editDateHired')) {
                el('editDateHired').value = employee.date_hired || '';
            }

            if (el('editStatus')) el('editStatus').value = cleanValue(employee.status) || '-';
            if (el('editLastAssignedBy')) el('editLastAssignedBy').value = cleanValue(employee.last_assigned_by);

            if (el('editAssignmentDate')) {
                el('editAssignmentDate').value = employee.assignment_date || '';
            }
            if (el('editStartDate')) el('editStartDate').value = cleanValue(employee.start_date);
            if (el('editEndDate')) el('editEndDate').value = cleanValue(employee.end_date);

            // =========================
            // SELECT FIELDS SAFE
            // =========================
            const employmentSelect = el('editEmploymentStatus');
            if (employmentSelect) {
                const empStatus = cleanValue(employee.employment_status).toUpperCase();
                employmentSelect.value = [...employmentSelect.options].some(opt => opt.value === empStatus)
                    ? empStatus
                    : '';
            }

           // =========================
            // GET SUB STATUS ONCE
            // =========================
            const subStatus = cleanValue(employee.sub_status).toUpperCase();

            // =========================
            // SET SELECT VALUE
            // =========================
            const subStatusSelect = el('editSubStatus');

            if (subStatusSelect) {
                const valid = [...subStatusSelect.options].some(opt => opt.value === subStatus);
                subStatusSelect.value = valid ? subStatus : '';
            }

            // =========================
            // RESET + SYNC UI
            // =========================
            syncMultiUI(subStatus);

            // normalize arrays safely (FIXED)
            const branches = safeArray(employee.roving_branches || p.roving_branches);
            const brands   = safeArray(employee.multi_brands || p.multi_brands);
            
            // render AFTER UI sync
            requestAnimationFrame(() => {
                if (subStatus === 'MULTI BRANCH') {
                    populateEditRoving(branches);
                    updateBranchOptions();
                }

                if (subStatus === 'MULTI BRAND') {
                    populateEditBrands(brands);
                    updateBrandOptions();
                }
            });
            
            if (reasonSelect) {
                const reasonValue = cleanValue(employee.reason_update).toUpperCase();
                reasonSelect.value = [...reasonSelect.options].some(opt => opt.value === reasonValue)
                    ? reasonValue
                    : '';
            }

            // =========================
            // EDITABLE FIELDS SAFE
            // =========================
            if (el('editDateSeparated')) el('editDateSeparated').value = cleanValue(employee.date_separated);
            if (el('editDateReturn')) el('editDateReturn').value = cleanValue(employee.date_of_return);
            if (el('editRemarks')) el('editRemarks').value = cleanValue(employee.remarks);

            // =========================
            // READ ONLY
            // =========================
            if (el('editLastUpdatedBy')) el('editLastUpdatedBy').value = cleanValue(employee.last_updated_by);
            if (el('editDateLastUpdated')) {
                el('editDateLastUpdated').value = employee.updated_at
                    ? employee.updated_at.split(' ')[0]
                    : '';
            }

            // =========================
            // DISABLE LOGIC
            // =========================
            const editable = [
                'editEmploymentStatus',
                'editSubStatus',
                'editReasonUpdate',
                'editDateSeparated',
                'editDateReturn',
                'editRemarks'
            ];

            modalEl.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = !editable.includes(el.id);
            });

            modal.show();
            loadHistory(employee.id);

            // sync toggles
            toggleDateSeparated();
            toggleDateReturned();
            toggleEmploymentDates();

        } catch (err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load employee data' });
        }
    });
});

// =========================
// SAVE BUTTON (UNCHANGED LOGIC)
// =========================
document.getElementById('saveBtn').addEventListener('click', async () => {

    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Save Changes?',
        text: 'Changes to this employee may affect assignments and history. This cannot be easily undone.',
        showCancelButton: true
    });

    if (!confirm.isConfirmed) return;

    const reason = (document.getElementById('editReasonUpdate').value || '').toUpperCase();

    const dateSeparated = document.getElementById('editDateSeparated');
    const dateReturned = document.getElementById('editDateReturn');

    if (dateSeparated?.required && !dateSeparated.value) {
        return Swal.fire({
            icon: 'warning',
            title: 'Date Separated Required'
        });
    }

    if (reason === "MATERNITY LEAVE" && !dateReturned.value) {
        return Swal.fire({
            icon: 'warning',
            title: 'Date Returned Required'
        });
    }

    // 🚨 REQUIRED FIELD CHECK
    if (!reason) {
        return Swal.fire({
            icon: 'warning',
            title: 'Reason Required',
            text: 'Please select a reason for update.'
        });
    }

    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('employment_status', document.getElementById('editEmploymentStatus').value);
    formData.set('sub_status', document.getElementById('editSubStatus').value);
    formData.set('reason_update', reason);
    formData.set('date_separated', dateSeparated.value);
    formData.set('date_returned', dateReturned.value);
    formData.set('last_updated_by', document.getElementById('editLastUpdatedBy').value);
    formData.set('date_last_updated', document.getElementById('editDateLastUpdated').value);
    formData.set('remarks', document.getElementById('editRemarks').value);
    formData.set('start_date', startDateInput.value);
    formData.set('end_date', endDateInput.value);

   fetch('functions/update_promodizer.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Success', data.message, 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Request failed', 'error');
    });
});

async function loadBranchBrandPairs() {
    try {
        const res = await fetch('functions/get_available_branches_brands.php');
        branchBrandPairs = await res.json();
    } catch (err) {
        console.error('Failed to load branch-brand pairs', err);
    }
}

function getSelectedValues(container, selector) {
    return [...container.querySelectorAll(selector)]
        .map(s => s.value)
        .filter(v => v);
}

function updateBranchOptions() {
    const selects = editRovingContainer.querySelectorAll('select');
    const selectedValues = getSelectedValues(editRovingContainer, 'select');

    const uniqueBranches = [...new Set(branchBrandPairs.map(p => p.branch_name))];

    selects.forEach(select => {
        const currentValue = select.value;

        select.innerHTML = `
            <option value="">Select branch</option>
        ` + uniqueBranches
            .filter(branch => {

                // ❌ remove full branches completely
                const combos = branchBrandPairs.filter(p => p.branch_name === branch);

                const hasAvailable = combos.some(c =>
                    c.assigned_count < c.required_count
                );

                return hasAvailable || branch === currentValue;
            })
            .filter(branch =>
                !selectedValues.includes(branch) || branch === currentValue
            )
            .map(branch => `
                <option value="${branch}"
                    ${branch === currentValue ? 'selected' : ''}>
                    ${branch}
                </option>
            `).join('');
    });
}

function updateBrandOptions() {
    const selects = editMultiBrandContainer.querySelectorAll('select');
    const selectedValues = getSelectedValues(editMultiBrandContainer, 'select');

    const uniqueBrands = [...new Set(branchBrandPairs.map(p => p.brand_name))];

    selects.forEach(select => {
        const currentValue = select.value;

        select.innerHTML = `
            <option value="">Select brand</option>
        ` + uniqueBrands
            .filter(brand => {

                const combos = branchBrandPairs.filter(p => p.brand_name === brand);

                const hasAvailable = combos.some(c =>
                    c.assigned_count < c.required_count
                );

                return hasAvailable || brand === currentValue;
            })
            .filter(brand =>
                !selectedValues.includes(brand) || brand === currentValue
            )
            .map(brand => `
                <option value="${brand}"
                    ${brand === currentValue ? 'selected' : ''}>
                    ${brand}
                </option>
            `).join('');
    });
}

function isComboAvailable(branch, brand) {
    const combo = branchBrandPairs.find(p =>
        p.branch_name === branch &&
        p.brand_name === brand
    );

    if (!combo) return false;

    return combo.assigned_count < combo.required_count;
}
// =========================
// INIT LISTENERS
// =========================
document.addEventListener('DOMContentLoaded', async function () {

    await loadBranchBrandPairs(); // 🔥 ADD THIS

    if (reasonSelect) {
        reasonSelect.addEventListener('change', toggleDateSeparated);
        reasonSelect.addEventListener('change', toggleDateReturned);
    }

    if (employmentStatusSelect) {
        employmentStatusSelect.addEventListener('change', toggleEmploymentDates);
    }

    editRovingContainer.addEventListener('click', (e) => {

        // ADD
        if (e.target.classList.contains('btn-add-branch')) {
            const row = e.target.closest('.roving-row');
            const clone = row.cloneNode(true);

            const select = clone.querySelector('select');
            if (select) {
                select.value = '';
                select.disabled = false;
            }

            const removeBtn = clone.querySelector('.btn-remove-branch');
            if (removeBtn) removeBtn.style.display = 'inline-block';

            editRovingContainer.appendChild(clone);
            updateBranchOptions();
        }

        // REMOVE
        if (e.target.classList.contains('btn-remove-branch')) {
            e.target.closest('.roving-row').remove();
            updateBranchOptions();
        }
    });

    editMultiBrandContainer.addEventListener('click', (e) => {

        // ADD
        if (e.target.classList.contains('btn-add-brand')) {
            const row = e.target.closest('.brand-row');
            const clone = row.cloneNode(true);

            const select = clone.querySelector('select');
            if (select) {
                select.value = '';
                select.disabled = false;
            }

            const removeBtn = clone.querySelector('.btn-remove-brand');
            if (removeBtn) removeBtn.style.display = 'inline-block';

            editMultiBrandContainer.appendChild(clone);
            updateBrandOptions();
        }

        // REMOVE
        if (e.target.classList.contains('btn-remove-brand')) {
            const allRows = editMultiBrandContainer.querySelectorAll('.brand-row');

            if (allRows.length > 1) {
                e.target.closest('.brand-row').remove();
                updateBrandOptions();
            } else {
                e.target.closest('.brand-row').querySelector('select').value = '';
            }
        }
    });

    const editSubStatus = document.getElementById('editSubStatus');

    if (editSubStatus) {
        editSubStatus.addEventListener('change', () => {
            const value = (editSubStatus.value || '').toUpperCase();

            syncMultiUI(value);

            if (value === 'MULTI BRANCH') {
                populateEditRoving(['']);
            }

            if (value === 'MULTI BRAND') {
                populateEditBrands(['']);
            }
        });
    }
    // 🔥 PREVENT DUPLICATES ON CHANGE
    editRovingContainer.addEventListener('change', (e) => {
        if (e.target.tagName !== 'SELECT') return;

        const branch = e.target.value;
        const brand = document.getElementById('editBrand')?.value;

        if (branch && brand && !isComboAvailable(branch, brand)) {
            Swal.fire('Not Available', 'This branch is full.', 'warning');
            e.target.value = '';
            return;
        }

        updateBranchOptions();
    });

    editMultiBrandContainer.addEventListener('change', (e) => {
        if (e.target.tagName !== 'SELECT') return;

        const brand = e.target.value;
        const branch = document.getElementById('editBranch')?.value;

        if (branch && brand && !isComboAvailable(branch, brand)) {
            Swal.fire('Not Available', 'This brand is full.', 'warning');
            e.target.value = '';
            return;
        }

        updateBrandOptions();
    });
});