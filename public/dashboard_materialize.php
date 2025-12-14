<?php
/**
 * PHOENIX Adjudication - Dashboard (Materialize CSS) - FIXED PATHS
 *
 * Main dashboard showing patients list with Materialize CSS framework
 */

require_once __DIR__ . '/../inc/auth.php';
require_login();
$user = current_user();

$pageTitle = 'Dashboard';
$customJS = ['assets/js/dashboard.js'];

require_once __DIR__ . '/../inc/templates/header_light.php';
?>

<!-- Page Header -->
<div class="row">
    <div class="col s12">
        <h4 class="blue-grey-text text-lighten-2">
            <i class="material-icons left">dashboard</i>
            Patient Dashboard
        </h4>
        <p class="grey-text">Manage patients and adjudication cases</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col s12 m6 l3">
        <div class="card hoverable">
            <div class="card-content center-align">
                <i class="material-icons large blue-text">people</i>
                <h5 id="stat-patients" class="grey-text text-lighten-4">-</h5>
                <p class="grey-text">Total Patients</p>
            </div>
        </div>
    </div>
    <div class="col s12 m6 l3">
        <div class="card hoverable">
            <div class="card-content center-align">
                <i class="material-icons large orange-text">assignment</i>
                <h5 id="stat-pending" class="grey-text text-lighten-4">-</h5>
                <p class="grey-text">Pending Cases</p>
            </div>
        </div>
    </div>
    <div class="col s12 m6 l3">
        <div class="card hoverable">
            <div class="card-content center-align">
                <i class="material-icons large green-text">check_circle</i>
                <h5 id="stat-consensus" class="grey-text text-lighten-4">-</h5>
                <p class="grey-text">Consensus Reached</p>
            </div>
        </div>
    </div>
    <div class="col s12 m6 l3">
        <div class="card hoverable">
            <div class="card-content center-align">
                <i class="material-icons large purple-text">description</i>
                <h5 id="stat-my-adjudications" class="grey-text text-lighten-4">-</h5>
                <p class="grey-text">My Adjudications</p>
            </div>
        </div>
    </div>
</div>

<!-- Import Section (Admin/Coordinator only) -->
<?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left">upload_file</i>
                    Import Master Table
                </span>
                <p class="grey-text">Upload CSV file with drug-event dictionary data</p>
                <br>
                <form method="post" action="../api/import_master.php" enctype="multipart/form-data" id="import-form">
                    <div class="file-field input-field">
                        <div class="btn blue">
                            <span><i class="material-icons left">attach_file</i>Choose CSV</span>
                            <input type="file" name="csv" accept=".csv" required>
                        </div>
                        <div class="file-path-wrapper">
                            <input class="file-path validate" type="text" placeholder="PHOENIX_Master_LongTable.csv">
                        </div>
                    </div>
                    <button class="btn waves-effect waves-light blue" type="submit">
                        <i class="material-icons left">cloud_upload</i>
                        Upload & Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Patient Section -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left">person_add</i>
                    Add New Patient
                </span>
                <form id="add-patient-form">
                    <div class="row">
                        <div class="input-field col s12 m4">
                            <i class="material-icons prefix">badge</i>
                            <input id="patient_code" type="text" name="patient_code" required class="validate">
                            <label for="patient_code">Patient ID</label>
                            <span class="helper-text" data-error="Required">Enter unique patient identifier</span>
                        </div>
                        <div class="input-field col s12 m4">
                            <i class="material-icons prefix">event</i>
                            <input id="randomisation_date" type="date" name="randomisation_date" required class="datepicker">
                            <label for="randomisation_date">Randomisation Date</label>
                        </div>
                        <div class="input-field col s12 m4">
                            <i class="material-icons prefix">medication</i>
                            <select id="index_drug_id" name="index_drug_id" required>
                                <option value="" disabled selected>Loading...</option>
                            </select>
                            <label>Index Drug</label>
                        </div>
                    </div>
                    <button class="btn waves-effect waves-light blue" type="submit">
                        <i class="material-icons left">add</i>
                        Add Patient
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Patients List -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left">list</i>
                    Patients
                </span>

                <!-- Search and Filter -->
                <div class="row">
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">search</i>
                        <input type="text" id="search-patients" placeholder="Search patients...">
                        <label for="search-patients">Search</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <select id="filter-drug">
                            <option value="">All Index Drugs</option>
                        </select>
                        <label>Filter by Drug</label>
                    </div>
                </div>
                <p class="grey-text">
                    <i class="material-icons tiny">info</i>
                    <small>Click column headers to sort. Use search box to filter results.</small>
                </p>

                <div id="patients-list">
                    <div class="progress">
                        <div class="indeterminate"></div>
                    </div>
                    <p class="center-align grey-text">Loading patients...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Concomitant Drugs Modal -->
<div id="concomitant-modal" class="modal modal-fixed-footer">
    <div class="modal-content">
        <h4>
            <i class="material-icons left">medication</i>
            Concomitant Drugs — <span id="modal-patient-code"></span>
        </h4>
        <p>Index drug: <strong id="modal-index-drug">ABACAVIR</strong></p>

        <div id="concomitant-drugs-list">
            <p class="center-align grey-text">Loading drugs...</p>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-flat waves-effect waves-light" onclick="M.Modal.getInstance(document.getElementById('concomitant-modal')).close()">
            Cancel
        </button>
        <button id="save-concomitants" class="btn waves-effect waves-light green">
            <i class="material-icons left">save</i>
            Save Concomitants
        </button>
    </div>
</div>

<!-- Table Utilities Script -->
<script src="assets/js/table-utils.js"></script>

<script>
let currentPatientId = null;
let allDrugs = [];

document.addEventListener('DOMContentLoaded', () => {
    // Initialize modals
    M.Modal.init(document.querySelectorAll('.modal'));

    loadDrugs();
    loadPatients();
    loadStatistics();
    wireAddPatientForm();
    wireConcomitantModal();
});

async function loadDrugs() {
    const select = document.getElementById('index_drug_id');
    const filter = document.getElementById('filter-drug');

    try {
        const res = await fetch('../api/drugs.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const drugs = await res.json();

        if (!Array.isArray(drugs) || drugs.length === 0) {
            select.innerHTML = '<option value="">No drugs found</option>';
            return;
        }

        drugs.sort((a, b) => a.name.localeCompare(b.name));

        // Store for concomitant modal
        allDrugs = drugs;

        // Populate add patient dropdown
        select.innerHTML = '<option value="" disabled selected>— Select index drug —</option>' +
            drugs.map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');

        // Populate filter dropdown
        filter.innerHTML = '<option value="">All Index Drugs</option>' +
            drugs.map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');

        // Reinitialize Materialize select
        M.FormSelect.init(document.querySelectorAll('select'));

    } catch (err) {
        console.error('Failed to load drugs:', err);
        showToast('Failed to load drugs', 'error');
    }
}

async function loadPatients() {
    const container = document.getElementById('patients-list');

    try {
        const res = await fetch('../api/patients.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const patients = await res.json();

        if (!patients || patients.length === 0) {
            container.innerHTML = '<p class="center-align grey-text">No patients yet. Add your first patient above.</p>';
            return;
        }

        // Build patients table
        let html = `
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Index Drug</th>
                        <th>Randomisation</th>
                        <th>Follow-up End</th>
                        <th>Concomitant Drugs</th>
                        <th class="center-align">Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        patients.forEach(p => {
            const concomitantCount = p.concomitant_count || 0;
            const concomitantDisplay = concomitantCount > 0
                ? `<span class="badge blue white-text">${concomitantCount}</span>`
                : '<span class="grey-text">None</span>';

            html += `
                <tr>
                    <td><strong>${escapeHtml(p.patient_code)}</strong></td>
                    <td>${escapeHtml(p.index_drug_name || 'N/A')}</td>
                    <td>${escapeHtml(p.randomisation_date || '—')}</td>
                    <td>${escapeHtml(p.followup_end_date || '—')}</td>
                    <td>${concomitantDisplay}</td>
                    <td class="center-align">
                        <a href="patient.php?id=${p.id}" class="btn-small waves-effect waves-light blue">
                            <i class="material-icons left tiny">open_in_new</i>
                            OPEN
                        </a>
                        <a href="#concomitant-modal" class="btn-small waves-effect waves-light orange modal-trigger" onclick="openConcomitantModal(${p.id}, '${escapeHtml(p.patient_code)}')">
                            <i class="material-icons left tiny">add</i>
                            ADD CONCOMITANT DRUGS
                        </a>
                    </td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        container.innerHTML = html;

        // Initialize table sorting and searching
        setTimeout(() => {
            initializeTable('#patients-list table', '#search-patients');
        }, 100);

    } catch (err) {
        console.error('Failed to load patients:', err);
        container.innerHTML = '<p class="center-align red-text">Failed to load patients.</p>';
    }
}

async function loadStatistics() {
    try {
        // Load total patients
        const patientsRes = await fetch('../api/patients.php', { credentials: 'same-origin' });
        const patients = await patientsRes.json();
        document.getElementById('stat-patients').textContent = patients.length;

        // For now, set others to 0 (will be implemented in later phases)
        document.getElementById('stat-pending').textContent = '0';
        document.getElementById('stat-consensus').textContent = '0';
        document.getElementById('stat-my-adjudications').textContent = '0';

    } catch (err) {
        console.error('Failed to load statistics:', err);
    }
}

function wireAddPatientForm() {
    const form = document.getElementById('add-patient-form');
    const btn = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        if (payload.index_drug_id) payload.index_drug_id = Number(payload.index_drug_id);

        if (!payload.patient_code || !payload.randomisation_date || !payload.index_drug_id) {
            showToast('Please complete all fields', 'warning');
            return;
        }

        try {
            btn.disabled = true;
            showLoading('Adding patient...');

            const res = await fetch('../api/patients.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!res.ok) {
                const txt = await res.text();
                throw new Error(`Add failed: HTTP ${res.status} ${txt}`);
            }

            const out = await res.json();
            if (!out.ok) throw new Error(out.error || 'Add failed');

            form.reset();
            M.updateTextFields(); // Reset labels
            await loadPatients();
            await loadStatistics();
            showToast('Patient added successfully!', 'success');

        } catch (err) {
            console.error(err);
            showToast('Failed to add patient: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            hideLoading();
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function openConcomitantModal(patientId, patientCode) {
    currentPatientId = patientId;
    document.getElementById('modal-patient-code').textContent = patientCode;

    // Fetch patient details to get index drug
    try {
        const pRes = await fetch(`../api/patients.php?id=${patientId}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        if (pRes.ok) {
            const patient = await pRes.json();
            document.getElementById('modal-index-drug').textContent = patient.index_drug_name || 'N/A';
        }
    } catch (err) {
        console.error('Failed to load patient details:', err);
    }

    // Load existing concomitants for this patient
    await loadConcomitantsForModal(patientId);
}

async function loadConcomitantsForModal(patientId) {
    const container = document.getElementById('concomitant-drugs-list');
    container.innerHTML = '<p class="center-align grey-text">Loading drugs...</p>';

    try {
        // Fetch existing concomitants
        const res = await fetch(`../api/concomitants.php?patient_id=${patientId}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        let existingConcomitants = [];
        if (res.ok) {
            existingConcomitants = await res.json();
        }

        // Build table with all drugs
        let html = `
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">Use</th>
                        <th>Drug</th>
                        <th>Start Date</th>
                        <th>Stop Date</th>
                    </tr>
                </thead>
                <tbody>
        `;

        allDrugs.forEach(drug => {
            const existing = existingConcomitants.find(c => parseInt(c.drug_id) === parseInt(drug.id));
            const isChecked = existing ? 'checked' : '';
            const startDate = existing ? existing.start_date : '';
            const stopDate = existing ? existing.stop_date : '';

            html += `
                <tr>
                    <td>
                        <label>
                            <input type="checkbox" class="drug-checkbox" data-drug-id="${drug.id}" ${isChecked} />
                            <span></span>
                        </label>
                    </td>
                    <td><strong>${escapeHtml(drug.name)}</strong></td>
                    <td>
                        <input type="date" class="drug-start-date" data-drug-id="${drug.id}" value="${startDate}" style="border: 1px solid #ddd; padding: 5px; border-radius: 3px;" />
                    </td>
                    <td>
                        <input type="date" class="drug-stop-date" data-drug-id="${drug.id}" value="${stopDate}" style="border: 1px solid #ddd; padding: 5px; border-radius: 3px;" />
                    </td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        container.innerHTML = html;

    } catch (err) {
        console.error('Failed to load concomitants:', err);
        container.innerHTML = '<p class="center-align red-text">Failed to load drugs</p>';
    }
}

function wireConcomitantModal() {
    const saveBtn = document.getElementById('save-concomitants');

    saveBtn.addEventListener('click', async () => {
        if (!currentPatientId) return;

        // Collect selected drugs with dates
        const checkboxes = document.querySelectorAll('.drug-checkbox:checked');
        const concomitants = [];

        checkboxes.forEach(cb => {
            const drugId = parseInt(cb.dataset.drugId);
            const startDateInput = document.querySelector(`.drug-start-date[data-drug-id="${drugId}"]`);
            const stopDateInput = document.querySelector(`.drug-stop-date[data-drug-id="${drugId}"]`);

            concomitants.push({
                drug_id: drugId,
                start_date: startDateInput.value || '',
                stop_date: stopDateInput.value || ''
            });
        });

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="material-icons left">hourglass_empty</i>Saving...';

        try {
            // The API expects drug_ids array and single start/stop dates
            // So we need to call it once per unique date combination
            // Group drugs by their date combinations
            const groups = new Map();

            concomitants.forEach(c => {
                const key = `${c.start_date}|${c.stop_date}`;
                if (!groups.has(key)) {
                    groups.set(key, {
                        start_date: c.start_date,
                        stop_date: c.stop_date,
                        drug_ids: []
                    });
                }
                groups.get(key).drug_ids.push(c.drug_id);
            });

            // Call API for each group
            let successCount = 0;
            for (const [key, group] of groups) {
                const res = await fetch('../api/concomitants.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        patient_id: currentPatientId,
                        drug_ids: group.drug_ids,
                        start_date: group.start_date,
                        stop_date: group.stop_date
                    })
                });

                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(`HTTP ${res.status}: ${text}`);
                }

                const result = await res.json();
                if (!result.ok) {
                    throw new Error(result.error || 'Failed to save');
                }
                successCount += result.inserted || 0;
            }

            M.toast({ html: `<i class="material-icons left">check_circle</i>Concomitants saved! (${successCount} updated)`, classes: 'green' });
            M.Modal.getInstance(document.getElementById('concomitant-modal')).close();
            await loadPatients(); // Refresh patient list

        } catch (err) {
            console.error('Save error:', err);
            M.toast({ html: '<i class="material-icons left">error</i>Failed to save: ' + err.message, classes: 'red' });
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="material-icons left">save</i>Save Concomitants';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_light.php'; ?>
