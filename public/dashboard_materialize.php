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

require_once __DIR__ . '/../inc/templates/header_fixed.php';
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
                        <input type="text" id="search-patients" placeholder="Search by patient code...">
                        <label for="search-patients">Search</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <select id="filter-drug">
                            <option value="">All Index Drugs</option>
                        </select>
                        <label>Filter by Drug</label>
                    </div>
                </div>

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

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadDrugs();
    loadPatients();
    loadStatistics();
    wireAddPatientForm();
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

        // Build patient cards
        let html = '<div class="collection">';
        patients.forEach(p => {
            html += `
                <a href="patient.php?id=${p.id}" class="collection-item avatar">
                    <i class="material-icons circle blue">person</i>
                    <span class="title"><strong>${escapeHtml(p.patient_code)}</strong></span>
                    <p>
                        Index Drug: ${escapeHtml(p.index_drug_name || 'N/A')}<br>
                        Randomised: ${escapeHtml(p.randomisation_date || 'N/A')}<br>
                        Follow-up End: ${escapeHtml(p.followup_end_date || 'N/A')}
                    </p>
                    <span class="secondary-content">
                        <i class="material-icons blue-text">chevron_right</i>
                    </span>
                </a>
            `;
        });
        html += '</div>';

        container.innerHTML = html;

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
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_fixed.php'; ?>
